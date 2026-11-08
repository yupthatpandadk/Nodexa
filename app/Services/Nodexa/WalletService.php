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
                || ($checkout->stripe_attempt ?? 0) > 0
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
