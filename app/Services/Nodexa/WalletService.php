<?php

namespace Pterodactyl\Services\Nodexa;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class WalletService
{
    public function __construct(private NodexaCartService $cart)
    {
    }

    public function balanceCents(int $userId): int
    {
        return (int) (DB::table('nodexa_wallets')->where('user_id', $userId)->value('balance_cents') ?? 0);
    }

    public function transactions(int $userId, int $limit = 50)
    {
        return DB::table('nodexa_wallet_transactions')
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->limit(max(1, min(100, $limit)))
            ->get();
    }

    /**
     * An administrator grant or an approved refund. Caller supplies a unique
     * idempotency key: the same refund/order must never create two credits.
     */
    public function credit(
        int $userId,
        int $amountCents,
        string $type,
        string $key,
        string $description,
        ?int $actorUserId = null,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): void {
        if ($amountCents <= 0 || $amountCents > 100000000
            || !in_array($type, ['admin_credit', 'refund_credit'], true)
        ) {
            throw new RuntimeException('Ugyldigt Wallet-kreditbeløb eller transaktionstype.');
        }

        DB::transaction(function () use ($userId, $amountCents, $type, $key, $description, $actorUserId, $referenceType, $referenceId) {
            $wallet = $this->walletForUpdate($userId);
            $this->post($wallet, $amountCents, $type, $key, $description, $actorUserId, $referenceType, $referenceId);
        }, 3);
    }

    /**
     * Full-wallet checkout. No Stripe session may exist for the order.
     * Debit and payment claim are atomic; fulfillment is retried separately.
     */
    public function payCheckout(int $checkoutId, int $userId): array
    {
        $reference = 'wallet-checkout-' . $checkoutId;

        DB::transaction(function () use ($checkoutId, $userId, $reference) {
            $checkout = DB::table('nodexa_cart_checkouts')
                ->where('id', $checkoutId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if (!$checkout || (bool) ($checkout->hidden_from_client ?? false)) {
                throw new RuntimeException('Ordren findes ikke eller er blevet annulleret.');
            }

            $previousDebit = DB::table('nodexa_wallet_transactions')
                ->where('idempotency_key', $reference)->first();

            if ($previousDebit) {
                if ($checkout->payment_method !== 'wallet'
                    || (int) $previousDebit->user_id !== $userId
                    || (int) $previousDebit->amount_cents !== -((int) round((float) $checkout->total * 100))
                    || !in_array($checkout->status, ['wallet_paid', 'processing', 'partial', 'completed'], true)
                ) {
                    throw new RuntimeException('Wallet-transaktionen kan ikke matches med ordren.');
                }
                return;
            }

            if ($checkout->status !== 'awaiting_payment'
                || $checkout->paid_at
                || $checkout->payment_method
                || $checkout->payment_reference
                || $checkout->stripe_session_id
                || (($checkout->stripe_attempt ?? 0) > 0
                    && !($checkout->stripe_status === 'expired'
                        && in_array((string) ($checkout->wallet_hold_state ?? ''), ['none', 'released'], true)))
                || $checkout->stripe_payment_intent_id
            ) {
                throw new RuntimeException('Ordren behandles allerede eller har en Stripe-session. Wallet kan ikke anvendes på denne ordre.');
            }

            $amount = (int) round((float) $checkout->total * 100);
            if ($amount <= 0 || strtoupper((string) $checkout->currency) !== 'DKK') {
                throw new RuntimeException('Wallet kan kun betale positive beløb i DKK.');
            }

            $wallet = $this->walletForUpdate($userId);
            if ((int) $wallet->balance_cents < $amount) {
                throw new RuntimeException('Der er ikke nok kredit på Wallet-saldoen.');
            }

            // The coupon reservation joins this transaction. If it fails,
            // both the wallet debit and the order transition are rolled back.
            $this->cart->reserveCoupon($checkoutId);
            $this->post($wallet, -$amount, 'wallet_payment', $reference,
                'Wallet-betaling for ordre ' . $checkout->number,
                $userId, 'cart_checkout', $checkoutId);

            DB::table('nodexa_cart_checkouts')->where('id', $checkoutId)->update([
                'status' => 'wallet_paid',
                'paid_at' => now(),
                'payment_method' => 'wallet',
                'payment_reference' => $reference,
                'updated_at' => now(),
            ]);
        }, 3);

        // Provisioning can fail. Never charge twice when a paid checkout is
        // retried: a successful debit is keyed to the immutable checkout ID.
        return $this->cart->markPaid($checkoutId, 'wallet', $reference);
    }

    /**
     * Automatically hold as much DKK credit as possible for the Stripe portion.
     * Keep Stripe remainder >= 2.50 DKK (Stripe's DKK minimum).
     * Persist the attempt BEFORE the external API call so an ambiguous network
     * error can retry the same Stripe idempotency key without another debit.
     */
    public function prepareStripeSplit(int $checkoutId, int $userId): array
    {
        return DB::transaction(function () use ($checkoutId, $userId) {
            $checkout = DB::table('nodexa_cart_checkouts')->where('id', $checkoutId)
                ->where('user_id', $userId)->lockForUpdate()->first();

            if (!$checkout || $checkout->status !== 'awaiting_payment'
                || $checkout->paid_at || (bool) ($checkout->hidden_from_client ?? false)
                || $checkout->stripe_session_id
                || $checkout->payment_method
            ) {
                throw new RuntimeException('Ordren kan ikke starte en ny Stripe-betaling.');
            }

            if (strtoupper((string) $checkout->currency) !== 'DKK') {
                throw new RuntimeException('Kombineret betaling understøtter kun DKK.');
            }

            $total = (int) round((float) $checkout->total * 100);
            if ($total < 250) {
                throw new RuntimeException('Stripe kræver mindst 2,50 DKK for en restbetaling.');
            }

            $attempt = (int) ($checkout->stripe_attempt ?? 0);
            $holdState = (string) ($checkout->wallet_hold_state ?? 'none');
            if ($checkout->stripe_status === 'creating') {
                if ($holdState !== 'held' && $holdState !== 'none') {
                    throw new RuntimeException('Wallet-reservationen er ukendt. Kontakt support.');
                }
                return ['attempt' => $attempt, 'wallet_cents' => (int) $checkout->wallet_applied_cents,
                    'stripe_cents' => $total - (int) $checkout->wallet_applied_cents];
            }

            $attempt++;
            $wallet = $this->walletForUpdate($userId);
            $walletCents = min((int) $wallet->balance_cents, max(0, $total - 250));
            if ($walletCents > 0) {
                $this->post($wallet, -$walletCents, 'wallet_hold',
                    'stripe-wallet-hold-' . $checkoutId . '-' . $attempt,
                    'Reserveret til Stripe-ordre ' . $checkout->number,
                    $userId, 'cart_checkout', $checkoutId);
            }

            DB::table('nodexa_cart_checkouts')->where('id', $checkoutId)->update([
                'stripe_attempt' => $attempt,
                'stripe_status' => 'creating',
                'wallet_applied_cents' => $walletCents,
                'wallet_hold_state' => $walletCents > 0 ? 'held' : 'none',
                'updated_at' => now(),
            ]);

            return ['attempt' => $attempt, 'wallet_cents' => $walletCents,
                'stripe_cents' => $total - $walletCents];
        }, 3);
    }

    /**
     * Release an unused hold once Stripe has confirmed the session expired,
     * or when Stripe definitively rejected creating the session.
     */
    public function releaseStripeSplit(int $checkoutId, string $sessionId = '', bool $noSessionCreated = false): void
    {
        DB::transaction(function () use ($checkoutId, $sessionId, $noSessionCreated) {
            $checkout = DB::table('nodexa_cart_checkouts')->where('id', $checkoutId)
                ->lockForUpdate()->first();

            if (!$checkout || $checkout->paid_at || $checkout->status !== 'awaiting_payment') {
                return;
            }

            if ($noSessionCreated && $checkout->stripe_session_id) {
                throw new RuntimeException('Stripe-sessionen er oprettet. Wallet kan ikke frigives uden bekræftet annullering.');
            }

            if (!$noSessionCreated && ($sessionId === ''
                || !hash_equals((string) $checkout->stripe_session_id, $sessionId))) {
                return;
            }

            $amount = (int) ($checkout->wallet_applied_cents ?? 0);
            if ($amount > 0 && $checkout->wallet_hold_state === 'held') {
                $wallet = $this->walletForUpdate((int) $checkout->user_id);
                $this->post($wallet, $amount, 'wallet_release',
                    'stripe-wallet-release-' . $checkoutId . '-' . (int) $checkout->stripe_attempt,
                    'Frigivet reservation for ordre ' . $checkout->number,
                    (int) $checkout->user_id, 'cart_checkout', $checkoutId);
            } elseif ($amount > 0 && $checkout->wallet_hold_state !== 'released') {
                throw new RuntimeException('Wallet-reservationens tilstand er ugyldig.');
            }

            DB::table('nodexa_cart_checkouts')->where('id', $checkoutId)->update([
                'wallet_applied_cents' => 0,
                'wallet_hold_state' => 'released',
                'stripe_session_id' => null,
                'stripe_status' => 'expired',
                'updated_at' => now(),
            ]);
        }, 3);
    }

    /**
     * Record that a successful Stripe payment consumed the existing wallet
     * hold. No further debit occurs here.
     */
    public function settleStripeSplit(int $checkoutId, string $sessionId): void
    {
        DB::transaction(function () use ($checkoutId, $sessionId) {
            $checkout = DB::table('nodexa_cart_checkouts')->where('id', $checkoutId)
                ->lockForUpdate()->first();

            if (!$checkout || !hash_equals((string) $checkout->stripe_session_id, $sessionId)) {
                throw new RuntimeException('Stripe-sessionen passer ikke til Wallet-reservationen.');
            }

            if ($checkout->wallet_hold_state === 'settled'
                || ((int) $checkout->wallet_applied_cents === 0 && $checkout->wallet_hold_state === 'none')) {
                return;
            }

            if ($checkout->wallet_hold_state !== 'held') {
                throw new RuntimeException('Wallet-reservationen er ikke aktiv ved Stripe-betalingen.');
            }

            DB::table('nodexa_cart_checkouts')->where('id', $checkoutId)->update([
                'wallet_hold_state' => 'settled',
                'updated_at' => now(),
            ]);
        }, 3);
    }

    private function walletForUpdate(int $userId): object
    {
        if ($userId < 1 || !DB::table('users')->where('id', $userId)->exists()) {
            throw new RuntimeException('Kunden findes ikke.');
        }

        DB::table('nodexa_wallets')->insertOrIgnore([
            'user_id' => $userId,
            'balance_cents' => 0,
            'currency' => 'DKK',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $wallet = DB::table('nodexa_wallets')->where('user_id', $userId)
            ->lockForUpdate()->first();

        if (!$wallet || $wallet->currency !== 'DKK' || (int) $wallet->balance_cents < 0) {
            throw new RuntimeException('Wallet er ikke tilgængelig i DKK.');
        }

        return $wallet;
    }

    private function post(
        object $wallet,
        int $amountCents,
        string $type,
        string $key,
        string $description,
        ?int $actor,
        ?string $referenceType,
        ?int $referenceId
    ): void {
        $existing = DB::table('nodexa_wallet_transactions')->where('idempotency_key', $key)->first();
        if ($existing) {
            if ((int) $existing->user_id !== (int) $wallet->user_id
                || (int) $existing->amount_cents !== $amountCents
                || (string) $existing->type !== $type
            ) {
                throw new RuntimeException('Wallet-reference eksisterer allerede med andre oplysninger.');
            }
            return;
        }

        $balance = (int) $wallet->balance_cents + $amountCents;
        if ($balance < 0) {
            throw new RuntimeException('Wallet-saldoen er utilstrækkelig.');
        }

        DB::table('nodexa_wallet_transactions')->insert([
            'wallet_id' => $wallet->id,
            'user_id' => $wallet->user_id,
            'amount_cents' => $amountCents,
            'balance_after_cents' => $balance,
            'currency' => 'DKK',
            'type' => $type,
            'idempotency_key' => $key,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'description' => mb_substr(trim($description), 0, 500),
            'actor_user_id' => $actor,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('nodexa_wallets')->where('id', $wallet->id)->update([
            'balance_cents' => $balance,
            'updated_at' => now(),
        ]);
    }
}
