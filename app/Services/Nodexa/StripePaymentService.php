<?php

namespace Pterodactyl\Services\Nodexa;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class StripePaymentService
{
    private const API_BASE = 'https://api.stripe.com/v1';
    private const WEBHOOK_TOLERANCE = 300;

    public function __construct(private NodexaCartService $cart)
    {
    }

    public function settingsForAdmin(): object
    {
        $row = DB::table('nodexa_stripe_settings')->where('id', 1)->first();

        return (object) [
            'enabled' => (bool) ($row->enabled ?? false),
            'has_secret_key' => !empty($row->secret_key_encrypted),
            'has_webhook_secret' => !empty($row->webhook_secret_encrypted),
            'last_tested_at' => $row->last_tested_at ?? null,
            'last_test_status' => $row->last_test_status ?? null,
            'last_test_message' => $row->last_test_message ?? null,
        ];
    }

    public function saveSettings(bool $enabled, ?string $secretKey, ?string $webhookSecret): void
    {
        $existing = DB::table('nodexa_stripe_settings')->where('id', 1)->first();

        $values = [
            'enabled' => $enabled,
            'updated_at' => now(),
        ];

        if ($secretKey !== null && trim($secretKey) !== '') {
            $secretKey = trim($secretKey);
            if (!str_starts_with($secretKey, 'sk_')) {
                throw new RuntimeException('Stripe Secret Key skal starte med sk_.');
            }
            $values['secret_key_encrypted'] = Crypt::encryptString($secretKey);
        }

        if ($webhookSecret !== null && trim($webhookSecret) !== '') {
            $webhookSecret = trim($webhookSecret);
            if (!str_starts_with($webhookSecret, 'whsec_')) {
                throw new RuntimeException('Stripe Webhook Secret skal starte med whsec_.');
            }
            $values['webhook_secret_encrypted'] = Crypt::encryptString($webhookSecret);
        }

        if ($existing) {
            DB::table('nodexa_stripe_settings')->where('id', 1)->update($values);
        } else {
            DB::table('nodexa_stripe_settings')->insert(array_merge([
                'id' => 1,
                'secret_key_encrypted' => null,
                'webhook_secret_encrypted' => null,
                'last_tested_at' => null,
                'last_test_status' => null,
                'last_test_message' => null,
                'created_at' => now(),
            ], $values));
        }
    }

    public function isEnabled(): bool
    {
        $row = DB::table('nodexa_stripe_settings')->where('id', 1)->first();

        return (bool) ($row?->enabled)
            && !empty($row?->secret_key_encrypted);
    }

    public function testConnection(): array
    {
        try {
            $response = $this->request('GET', '/account');

            if (!$response->successful()) {
                throw new RuntimeException($this->stripeError($response));
            }

            $json = $response->json();
            $message = 'Stripe forbindelse OK'
                . (!empty($json['id']) ? ' · ' . $json['id'] : '')
                . (!empty($json['country']) ? ' · ' . $json['country'] : '');

            $this->recordTest('success', $message);

            return ['success' => true, 'message' => $message];
        } catch (\Throwable $exception) {
            $message = substr($exception->getMessage(), 0, 1000);
            $this->recordTest('failed', $message);

            return ['success' => false, 'message' => $message];
        }
    }

    public function createCheckoutSession(int $checkoutId, int $userId): array
    {
        if (!$this->isEnabled()) {
            throw new RuntimeException('Stripe betaling er ikke aktiveret.');
        }

        $checkout = DB::table('nodexa_cart_checkouts')
            ->where('id', $checkoutId)
            ->where('user_id', $userId)
            ->first();

        if (!$checkout) {
            throw new RuntimeException('Ordren blev ikke fundet.');
        }

        if ($checkout->payment_method === 'wallet' || ($checkout->paid_at && $checkout->status !== 'completed')) {
            throw new RuntimeException('Ordren er allerede betalt via en anden metode og kan ikke betales igen.');
        }

        if (in_array($checkout->status, ['cancelled', 'wallet_refunded', 'refunded'], true)
            || (bool) ($checkout->hidden_from_client ?? false)
        ) {
            throw new RuntimeException('Ordren er annulleret eller refunderet.');
        }

        if (in_array($checkout->status, ['paid', 'completed'], true)) {
            return [
                'url' => $this->orderUrl($checkoutId),
                'paid' => true,
            ];
        }

        $expectedCents = $this->moneyToCents($checkout->total);

        if ($expectedCents <= 0) {
            $this->cart->reserveCoupon($checkoutId);
            $result = $this->cart->markPaid($checkoutId, 'discount', 'zero-total');

            DB::table('nodexa_cart_checkouts')->where('id', $checkoutId)->update([
                'stripe_status' => 'not_required',
                'stripe_amount_total' => 0,
                'stripe_currency' => strtolower($checkout->currency ?: 'DKK'),
                'updated_at' => now(),
            ]);

            return [
                'url' => $this->orderUrl($checkoutId),
                'paid' => true,
                'result' => $result,
            ];
        }

        if ($checkout->stripe_session_id) {
            $existing = $this->retrieveSession((string) $checkout->stripe_session_id);

            if (($existing['payment_status'] ?? null) === 'paid') {
                $this->fulfillPaidSession($existing);

                return [
                    'url' => $this->orderUrl($checkoutId),
                    'paid' => true,
                ];
            }

            if (($existing['status'] ?? null) === 'open' && !empty($existing['url'])) {
                return [
                    'url' => $existing['url'],
                    'paid' => false,
                ];
            }

            if (($existing['status'] ?? null) === 'expired') {
                $this->cart->releaseCoupon($checkoutId);

                DB::table('nodexa_cart_checkouts')->where('id', $checkoutId)->update([
                    'stripe_session_id' => null,
                    'stripe_status' => 'expired',
                    'updated_at' => now(),
                ]);

                $checkout = DB::table('nodexa_cart_checkouts')->where('id', $checkoutId)->first();
            }
        }

        $this->cart->reserveCoupon($checkoutId);

        $checkout = DB::table('nodexa_cart_checkouts')->where('id', $checkoutId)->first();
        $attempt = ((int) ($checkout->stripe_attempt ?? 0)) + 1;
        $idempotencyKey = 'nodexa-checkout-' . $checkoutId . '-attempt-' . $attempt;

        $user = DB::table('users')->where('id', $userId)->first();
        if (!$user) {
            throw new RuntimeException('Kunden blev ikke fundet.');
        }

        $description = DB::table('nodexa_cart_checkout_items')
            ->where('checkout_id', $checkoutId)
            ->orderBy('id')
            ->pluck('name')
            ->take(4)
            ->implode(', ');

        if ($checkout->coupon_code) {
            $description .= ($description !== '' ? ' · ' : '') . 'Rabatkode ' . $checkout->coupon_code;
        }

        $params = [
            'mode' => 'payment',
            'success_url' => $this->successUrl($checkoutId),
            'cancel_url' => $this->orderUrl($checkoutId) . '?stripe=cancelled',
            'client_reference_id' => $checkout->number,
            'customer_email' => $user->email,
            'billing_address_collection' => 'auto',
            'line_items[0][price_data][currency]' => strtolower($checkout->currency ?: 'DKK'),
            'line_items[0][price_data][unit_amount]' => $expectedCents,
            'line_items[0][price_data][product_data][name]' => 'Nodexa ordre ' . $checkout->number,
            'line_items[0][price_data][product_data][description]' => mb_substr($description ?: 'Nodexa hosting services', 0, 500),
            'line_items[0][quantity]' => 1,
            'metadata[checkout_id]' => (string) $checkoutId,
            'metadata[order_number]' => (string) $checkout->number,
            'payment_intent_data[metadata][checkout_id]' => (string) $checkoutId,
            'payment_intent_data[metadata][order_number]' => (string) $checkout->number,
        ];

        try {
            $response = $this->request('POST', '/checkout/sessions', $params, $idempotencyKey);
        } catch (\Throwable $exception) {
            // Keep the coupon reservation: the network may have failed after
            // Stripe created the idempotent Session. Retrying uses the same
            // attempt number and therefore the same idempotency key.
            throw $exception;
        }

        if (!$response->successful()) {
            $this->cart->releaseCoupon($checkoutId);
            throw new RuntimeException($this->stripeError($response));
        }

        $session = $response->json();
        if (!is_array($session) || empty($session['id']) || empty($session['url'])) {
            $this->cart->releaseCoupon($checkoutId);
            throw new RuntimeException('Stripe returnerede ikke en gyldig Checkout Session.');
        }

        try {
            $this->assertSessionAmount($session, $checkout);
        } catch (\Throwable $exception) {
            $this->cart->releaseCoupon($checkoutId);

            if (!empty($session['id'])) {
                try {
                    $this->request('POST', '/checkout/sessions/' . rawurlencode($session['id']) . '/expire');
                } catch (\Throwable $ignored) {
                    report($ignored);
                }
            }

            throw $exception;
        }

        $currentCheckout = DB::table('nodexa_cart_checkouts')->where('id', $checkoutId)->first();

        DB::table('nodexa_cart_checkouts')->where('id', $checkoutId)->update([
            'stripe_attempt' => $attempt,
            'stripe_session_id' => $session['id'],
            'stripe_status' => $currentCheckout?->paid_at ? 'paid' : ($session['status'] ?? 'open'),
            'stripe_amount_total' => (int) ($session['amount_total'] ?? $expectedCents),
            'stripe_currency' => strtolower((string) ($session['currency'] ?? $checkout->currency ?? 'dkk')),
            'updated_at' => now(),
        ]);

        return [
            'url' => $session['url'],
            'paid' => false,
        ];
    }

    public function confirmCheckoutSession(int $checkoutId, int $userId, string $sessionId): array
    {
        $checkout = DB::table('nodexa_cart_checkouts')
            ->where('id', $checkoutId)
            ->where('user_id', $userId)
            ->first();

        if (!$checkout || !$checkout->stripe_session_id || !hash_equals((string) $checkout->stripe_session_id, $sessionId)) {
            throw new RuntimeException('Stripe-sessionen matcher ikke denne ordre.');
        }

        $session = $this->retrieveSession($sessionId);

        if (($session['payment_status'] ?? null) !== 'paid') {
            DB::table('nodexa_cart_checkouts')->where('id', $checkoutId)->update([
                'stripe_status' => $session['payment_status'] ?? $session['status'] ?? 'unpaid',
                'updated_at' => now(),
            ]);

            return [
                'paid' => false,
                'message' => 'Stripe har endnu ikke bekræftet betalingen.',
            ];
        }

        $result = $this->fulfillPaidSession($session);

        return [
            'paid' => true,
            'message' => 'Betalingen er bekræftet af Stripe.',
            'result' => $result,
        ];
    }

    public function handleWebhook(string $payload, string $signatureHeader): void
    {
        $event = $this->verifyWebhook($payload, $signatureHeader);
        $eventId = (string) ($event['id'] ?? '');
        $type = (string) ($event['type'] ?? '');

        if ($eventId === '' || $type === '') {
            throw new RuntimeException('Stripe webhook mangler event id eller type.');
        }

        $existing = DB::table('nodexa_stripe_events')->where('stripe_event_id', $eventId)->first();
        if ($existing && $existing->status === 'completed') {
            return;
        }

        $object = $event['data']['object'] ?? null;
        if (!is_array($object)) {
            throw new RuntimeException('Stripe webhook mangler event object.');
        }

        $checkoutId = (int) ($object['metadata']['checkout_id'] ?? 0);

        DB::table('nodexa_stripe_events')->updateOrInsert(
            ['stripe_event_id' => $eventId],
            [
                'type' => $type,
                'checkout_id' => $checkoutId ?: null,
                'status' => 'processing',
                'error' => null,
                'updated_at' => now(),
                'created_at' => $existing?->created_at ?? now(),
            ]
        );

        try {
            if (in_array($type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
                if (($object['payment_status'] ?? null) === 'paid') {
                    $this->fulfillPaidSession($object);
                } elseif ($checkoutId > 0) {
                    DB::table('nodexa_cart_checkouts')->where('id', $checkoutId)->update([
                        'stripe_status' => $object['payment_status'] ?? $object['status'] ?? 'unpaid',
                        'updated_at' => now(),
                    ]);
                }
            } elseif ($type === 'checkout.session.expired' && $checkoutId > 0) {
                $checkout = DB::table('nodexa_cart_checkouts')->where('id', $checkoutId)->first();

                if ($checkout && !$checkout->paid_at) {
                    $this->cart->releaseCoupon($checkoutId);

                    DB::table('nodexa_cart_checkouts')->where('id', $checkoutId)->update([
                        'stripe_status' => 'expired',
                        'updated_at' => now(),
                    ]);
                }
            }

            DB::table('nodexa_stripe_events')->where('stripe_event_id', $eventId)->update([
                'status' => 'completed',
                'processed_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            DB::table('nodexa_stripe_events')->where('stripe_event_id', $eventId)->update([
                'status' => 'failed',
                'error' => substr($exception->getMessage(), 0, 2000),
                'updated_at' => now(),
            ]);

            throw $exception;
        }
    }

    private function fulfillPaidSession(array $session): array
    {
        $checkoutId = (int) ($session['metadata']['checkout_id'] ?? 0);

        if ($checkoutId < 1) {
            throw new RuntimeException('Stripe-sessionen mangler checkout_id.');
        }

        $checkout = DB::table('nodexa_cart_checkouts')->where('id', $checkoutId)->first();
        if (!$checkout) {
            throw new RuntimeException('Stripe-sessionens Nodexa-ordre findes ikke.');
        }

        if ($checkout->payment_method === 'wallet'
            || in_array($checkout->status, ['cancelled', 'wallet_refunded', 'refunded'], true)
        ) {
            throw new RuntimeException('Ordren er allerede behandlet via Wallet eller er annulleret.');
        }

        if ($checkout->stripe_session_id && !hash_equals((string) $checkout->stripe_session_id, (string) ($session['id'] ?? ''))) {
            throw new RuntimeException('Stripe-session-id matcher ikke Nodexa-ordren.');
        }

        if (($session['payment_status'] ?? null) !== 'paid') {
            throw new RuntimeException('Stripe-sessionen er ikke markeret som betalt.');
        }

        $this->assertSessionAmount($session, $checkout);

        $paymentReference = (string) ($session['payment_intent'] ?? $session['id'] ?? '');

        DB::table('nodexa_cart_checkouts')->where('id', $checkoutId)->update([
            'stripe_session_id' => $session['id'] ?? $checkout->stripe_session_id,
            'stripe_payment_intent_id' => $session['payment_intent'] ?? null,
            'stripe_status' => 'paid',
            'stripe_amount_total' => (int) ($session['amount_total'] ?? 0),
            'stripe_currency' => strtolower((string) ($session['currency'] ?? '')),
            'updated_at' => now(),
        ]);

        return $this->cart->markPaid(
            $checkoutId,
            'stripe',
            $paymentReference !== '' ? $paymentReference : null
        );
    }

    private function assertSessionAmount(array $session, object $checkout): void
    {
        $expectedCents = $this->moneyToCents($checkout->total);
        $actualCents = (int) ($session['amount_total'] ?? -1);
        $expectedCurrency = strtolower((string) ($checkout->currency ?: 'DKK'));
        $actualCurrency = strtolower((string) ($session['currency'] ?? ''));
        $reference = (string) ($session['client_reference_id'] ?? '');
        $metadataOrder = (string) ($session['metadata']['order_number'] ?? '');

        if ($actualCents !== $expectedCents) {
            throw new RuntimeException(
                'Stripe-beløbet matcher ikke Nodexa-totalen. Forventet '
                . number_format($expectedCents / 100, 2, ',', '.')
                . ' ' . strtoupper($expectedCurrency)
                . ', Stripe: ' . number_format(max(0, $actualCents) / 100, 2, ',', '.')
                . ' ' . strtoupper($actualCurrency) . '.'
            );
        }

        if ($actualCurrency !== $expectedCurrency) {
            throw new RuntimeException('Stripe-valutaen matcher ikke Nodexa-ordren.');
        }

        if ($reference !== '' && !hash_equals((string) $checkout->number, $reference)) {
            throw new RuntimeException('Stripe client_reference_id matcher ikke Nodexa-ordren.');
        }

        if ($metadataOrder !== '' && !hash_equals((string) $checkout->number, $metadataOrder)) {
            throw new RuntimeException('Stripe metadata matcher ikke Nodexa-ordren.');
        }
    }

    /**
     * Expire an unpaid Checkout Session before allowing an order to be hidden.
     * Do not assume the session is unpaid from Nodexa's local status alone.
     */
    /**
     * Verify a paid Stripe order has not been refunded/disputed before staff
     * exchanges that payment for Nodexa Wallet credit.
     */
    public function assertEligibleForWalletCredit(object $checkout): void
    {
        if ((string) $checkout->payment_method !== 'stripe' || !$checkout->stripe_session_id) {
            throw new RuntimeException('Kun Stripe-betalinger med verificerbar session kan krediteres via Stripe.');
        }

        $session = $this->retrieveSession((string) $checkout->stripe_session_id);
        if (($session['payment_status'] ?? '') !== 'paid'
            || ($session['metadata']['checkout_id'] ?? '') != (string) $checkout->id
            || ($session['client_reference_id'] ?? '') !== (string) $checkout->number
            || (int) ($session['amount_total'] ?? -1) !== $this->moneyToCents($checkout->total)
            || strtolower((string) ($session['currency'] ?? '')) !== 'dkk'
        ) {
            throw new RuntimeException('Stripe-betalingen kunne ikke verificeres for denne ordre.');
        }

        $paymentIntent = $session['payment_intent'] ?? null;
        if (!is_string($paymentIntent) || !preg_match('/^pi_[A-Za-z0-9_]+$/', $paymentIntent)) {
            throw new RuntimeException('Stripe PaymentIntent mangler.');
        }

        $charges = $this->request('GET', '/charges', ['payment_intent' => $paymentIntent, 'limit' => 100]);
        if (!$charges->successful()) {
            throw new RuntimeException($this->stripeError($charges));
        }

        $rows = $charges->json();
        if (!is_array($rows) || !isset($rows['data']) || !is_array($rows['data']) || !empty($rows['has_more'])) {
            throw new RuntimeException('Stripe-betalingens chargehistorik kunne ikke verificeres.');
        }

        $capturedCents = 0;
        foreach ($rows['data'] as $charge) {
            if (!is_array($charge) || empty($charge['paid'])) {
                continue;
            }

            if (!empty($charge['disputed']) || !empty($charge['refunded'])
                || (int) ($charge['amount_refunded'] ?? 0) > 0
                || strtolower((string) ($charge['currency'] ?? '')) !== 'dkk'
            ) {
                throw new RuntimeException('Betalingen er allerede refunderet eller omtvistet i Stripe.');
            }

            $capturedCents += (int) ($charge['amount_captured'] ?? 0);
        }

        if ($capturedCents !== $this->moneyToCents($checkout->total)) {
            throw new RuntimeException('Det faktiske Stripe-beløb matcher ikke ordren.');
        }

        $refunds = $this->request('GET', '/refunds', ['payment_intent' => $paymentIntent, 'limit' => 100]);
        if (!$refunds->successful()) {
            throw new RuntimeException($this->stripeError($refunds));
        }

        $refundData = $refunds->json();
        if (!is_array($refundData) || !isset($refundData['data'])
            || !is_array($refundData['data']) || !empty($refundData['has_more'])
            || count($refundData['data']) > 0
        ) {
            throw new RuntimeException('Der er allerede en Stripe-refundering eller en uafklaret refundering.');
        }
    }

    public function expireUnpaidCheckoutSession(string $sessionId): void
    {
        $session = $this->retrieveSession($sessionId);
        $status = (string) ($session['status'] ?? '');
        $paymentStatus = (string) ($session['payment_status'] ?? '');

        if ($status === 'expired') {
            return;
        }

        if ($status !== 'open' || $paymentStatus === 'paid') {
            throw new RuntimeException('Stripe Checkout er allerede gennemført eller behandles. Ordren kan ikke slettes.');
        }

        $response = $this->request('POST', '/checkout/sessions/' . rawurlencode($sessionId) . '/expire');
        if (!$response->successful() || ($response->json('status') !== 'expired')) {
            throw new RuntimeException('Stripe Checkout kunne ikke annulleres sikkert. Prøv igen eller kontakt support.');
        }
    }

    /**
     * Ensure Stripe reports that the complete captured amount has been refunded.
     * This is a read-only check; the refund itself must be issued in Stripe.
     */
    public function assertCheckoutFullyRefunded(object $checkout): void
    {
        if (!$checkout->stripe_session_id || $checkout->payment_method !== 'stripe') {
            throw new RuntimeException('Denne ordre har ikke en verificerbar Stripe-betaling.');
        }

        $session = $this->retrieveSession((string) $checkout->stripe_session_id);
        if ((string) ($session['metadata']['checkout_id'] ?? '') !== (string) $checkout->id
            || (string) ($session['client_reference_id'] ?? '') !== (string) $checkout->number
        ) {
            throw new RuntimeException('Stripe-sessionen matcher ikke ordren.');
        }

        $paymentIntent = $session['payment_intent'] ?? null;
        if (!is_string($paymentIntent) || !preg_match('/^pi_[A-Za-z0-9_]+$/', $paymentIntent)) {
            throw new RuntimeException('Stripe PaymentIntent mangler. Refusionen kan ikke verificeres.');
        }

        $response = $this->request('GET', '/charges', ['payment_intent' => $paymentIntent, 'limit' => 100]);
        if (!$response->successful()) {
            throw new RuntimeException($this->stripeError($response));
        }

        $json = $response->json();
        if (!is_array($json) || !isset($json['data']) || !is_array($json['data']) || !empty($json['has_more'])) {
            throw new RuntimeException('Stripe kunne ikke bekræfte en komplet refusion.');
        }

        $capturedCents = 0;
        foreach ($json['data'] as $charge) {
            if (!is_array($charge) || empty($charge['paid'])) {
                continue;
            }

            $captured = (int) ($charge['amount_captured'] ?? 0);
            $refunded = (int) ($charge['amount_refunded'] ?? 0);
            if ($captured > 0 && $refunded < $captured) {
                throw new RuntimeException('Betalingen er endnu ikke fuldt refunderet i Stripe.');
            }

            $capturedCents += $captured;
        }

        $expected = (int) round(((float) $checkout->total) * 100);
        if ($expected <= 0 || $capturedCents < $expected) {
            throw new RuntimeException('Stripe kunne ikke bekræfte ordrens fulde betalingsbeløb.');
        }

        // amount_refunded can change before a refund has actually succeeded.
        // Require succeeded Refund objects, not pending/failed refund attempts.
        $refundResponse = $this->request('GET', '/refunds', ['payment_intent' => $paymentIntent, 'limit' => 100]);
        if (!$refundResponse->successful()) {
            throw new RuntimeException($this->stripeError($refundResponse));
        }

        $refunds = $refundResponse->json();
        if (!is_array($refunds) || !isset($refunds['data']) || !is_array($refunds['data']) || !empty($refunds['has_more'])) {
            throw new RuntimeException('Stripe kunne ikke bekræfte alle refunderinger.');
        }

        $succeeded = 0;
        foreach ($refunds['data'] as $refund) {
            if (!is_array($refund) || ($refund['status'] ?? null) !== 'succeeded'
                || ($refund['payment_intent'] ?? null) !== $paymentIntent
                || strtolower((string) ($refund['currency'] ?? '')) !== strtolower((string) $checkout->currency)
            ) {
                continue;
            }
            $succeeded += (int) ($refund['amount'] ?? 0);
        }

        if ($succeeded < $expected) {
            throw new RuntimeException('Betalingen er ikke fuldt refunderet med status succeeded i Stripe.');
        }
    }

    private function retrieveSession(string $sessionId): array
    {
        if (!preg_match('/^cs_[A-Za-z0-9_]+$/', $sessionId)) {
            throw new RuntimeException('Ugyldigt Stripe Checkout Session ID.');
        }

        $response = $this->request('GET', '/checkout/sessions/' . rawurlencode($sessionId));

        if (!$response->successful()) {
            throw new RuntimeException($this->stripeError($response));
        }

        $json = $response->json();
        if (!is_array($json)) {
            throw new RuntimeException('Stripe returnerede ugyldigt JSON.');
        }

        return $json;
    }

    private function request(string $method, string $path, array $params = [], ?string $idempotencyKey = null): Response
    {
        $secret = $this->secretKey();

        $client = Http::timeout(25)
            ->acceptJson()
            ->withToken($secret);

        if ($idempotencyKey) {
            $client = $client->withHeaders(['Idempotency-Key' => $idempotencyKey]);
        }

        try {
            if (strtoupper($method) === 'GET') {
                return $client->get(self::API_BASE . $path, $params);
            }

            return $client->asForm()->post(self::API_BASE . $path, $params);
        } catch (\Throwable $exception) {
            $message = str_replace($secret, '[redacted]', $exception->getMessage());
            throw new RuntimeException('Kunne ikke kontakte Stripe: ' . $message, 0, $exception);
        }
    }

    private function verifyWebhook(string $payload, string $signatureHeader): array
    {
        $secret = $this->webhookSecret();

        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $signatureHeader) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

            if ($key === 't') {
                $timestamp = ctype_digit((string) $value) ? (int) $value : null;
            } elseif ($key === 'v1' && $value) {
                $signatures[] = $value;
            }
        }

        if (!$timestamp || $signatures === []) {
            throw new RuntimeException('Stripe-Signature header er ugyldig.');
        }

        if (abs(time() - $timestamp) > self::WEBHOOK_TOLERANCE) {
            throw new RuntimeException('Stripe webhook-signaturen er for gammel.');
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        $valid = false;

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                $valid = true;
                break;
            }
        }

        if (!$valid) {
            throw new RuntimeException('Stripe webhook-signaturen kunne ikke verificeres.');
        }

        $event = json_decode($payload, true);
        if (!is_array($event)) {
            throw new RuntimeException('Stripe webhook indeholder ugyldigt JSON.');
        }

        return $event;
    }

    private function secretKey(): string
    {
        $row = DB::table('nodexa_stripe_settings')->where('id', 1)->first();

        if (!$row?->secret_key_encrypted) {
            throw new RuntimeException('Stripe Secret Key er ikke konfigureret.');
        }

        try {
            return Crypt::decryptString($row->secret_key_encrypted);
        } catch (\Throwable $exception) {
            throw new RuntimeException('Stripe Secret Key kunne ikke dekrypteres.');
        }
    }

    private function webhookSecret(): string
    {
        $row = DB::table('nodexa_stripe_settings')->where('id', 1)->first();

        if (!$row?->webhook_secret_encrypted) {
            throw new RuntimeException('Stripe Webhook Secret er ikke konfigureret.');
        }

        try {
            return Crypt::decryptString($row->webhook_secret_encrypted);
        } catch (\Throwable $exception) {
            throw new RuntimeException('Stripe Webhook Secret kunne ikke dekrypteres.');
        }
    }

    private function stripeError(Response $response): string
    {
        $json = $response->json();
        $message = is_array($json)
            ? ($json['error']['message'] ?? $json['message'] ?? null)
            : null;

        return 'Stripe API fejl (HTTP ' . $response->status() . '): '
            . ($message ?: 'Ukendt Stripe-fejl.');
    }

    private function recordTest(string $status, string $message): void
    {
        $existing = DB::table('nodexa_stripe_settings')->where('id', 1)->exists();

        $values = [
            'last_tested_at' => now(),
            'last_test_status' => $status,
            'last_test_message' => substr($message, 0, 2000),
            'updated_at' => now(),
        ];

        if ($existing) {
            DB::table('nodexa_stripe_settings')->where('id', 1)->update($values);
        } else {
            DB::table('nodexa_stripe_settings')->insert(array_merge([
                'id' => 1,
                'enabled' => false,
                'secret_key_encrypted' => null,
                'webhook_secret_encrypted' => null,
                'created_at' => now(),
            ], $values));
        }
    }

    private function successUrl(int $checkoutId): string
    {
        return rtrim((string) config('app.url'), '/')
            . '/cart/orders/' . $checkoutId
            . '/stripe/success?session_id={CHECKOUT_SESSION_ID}';
    }

    private function orderUrl(int $checkoutId): string
    {
        return rtrim((string) config('app.url'), '/') . '/cart/orders/' . $checkoutId;
    }

    private function moneyToCents(float|int|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
