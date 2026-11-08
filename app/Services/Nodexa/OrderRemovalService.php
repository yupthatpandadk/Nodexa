<?php

namespace Pterodactyl\Services\Nodexa;

use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\StoreOrder;
use RuntimeException;

/**
 * Customer-facing "delete" means cancel + archive. Never erase payment or
 * provisioning records, and never cancel a Stripe session without expiring it.
 */
class OrderRemovalService
{
    public function __construct(
        private StripePaymentService $stripe,
        private NodexaCartService $cart,
        private WalletService $wallet,
    ) {
    }

    public function removeCheckout(int $checkoutId, int $userId): string
    {
        $checkout = DB::table('nodexa_cart_checkouts')
            ->where('id', $checkoutId)
            ->where('user_id', $userId)
            ->first();

        if (!$checkout) {
            throw new RuntimeException('Ordren blev ikke fundet.');
        }

        $this->assertUnpaidCheckout($checkout);

        // A late, unconfirmed Stripe Checkout Session may still take money.
        // Expire it before we archive the order or release any reserved coupon.
        if ($checkout->stripe_session_id) {
            $sessionId = (string) $checkout->stripe_session_id;
            $this->stripe->expireUnpaidCheckoutSession($sessionId);
            $this->wallet->releaseStripeSplit($checkoutId, $sessionId);
        } elseif ((int) ($checkout->stripe_attempt ?? 0) > 0
            && ($checkout->stripe_status !== 'expired' || $checkout->wallet_hold_state === 'held')
        ) {
            throw new RuntimeException('Stripe-sessionens status er ukendt. Kontakt support før ordren fjernes.');
        }

        DB::transaction(function () use ($checkoutId, $userId) {
            $locked = DB::table('nodexa_cart_checkouts')
                ->where('id', $checkoutId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if (!$locked) {
                throw new RuntimeException('Ordren blev ikke fundet.');
            }

            $this->assertUnpaidCheckout($locked);

            // Coupon reservations are reversed only if nothing was paid.
            $this->cart->releaseCoupon($checkoutId);

            DB::table('nodexa_cart_checkouts')->where('id', $checkoutId)->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'hidden_from_client' => true,
                'stripe_status' => $locked->stripe_session_id ? 'expired' : $locked->stripe_status,
                'updated_at' => now(),
            ]);
        });

        return (string) $checkout->number;
    }

    /**
     * Archive a failed, already-paid Stripe order only after Stripe confirms a
     * complete refund. Financial and Stripe records are retained for audit.
     */
    public function archiveRefundedCheckout(int $checkoutId, int $userId): string
    {
        $checkout = DB::table('nodexa_cart_checkouts')
            ->where('id', $checkoutId)->where('user_id', $userId)->first();

        if (!$checkout) {
            throw new RuntimeException('Ordren blev ikke fundet.');
        }

        $this->assertFailedWithoutActiveServices($checkoutId, $checkout);
        $this->stripe->assertCheckoutFullyRefunded($checkout);

        DB::transaction(function () use ($checkoutId, $userId) {
            $locked = DB::table('nodexa_cart_checkouts')
                ->where('id', $checkoutId)->where('user_id', $userId)
                ->lockForUpdate()->first();

            if (!$locked) {
                throw new RuntimeException('Ordren blev ikke fundet.');
            }

            $this->assertFailedWithoutActiveServices($checkoutId, $locked);

            $items = DB::table('nodexa_cart_checkout_items')->where('checkout_id', $checkoutId)->get();

            foreach ($items as $item) {
                if (!$item->reference_id) {
                    continue;
                }

                if ($item->type === 'flax_vps') {
                    $service = DB::table('nodexa_flax_services')
                        ->where('id', $item->reference_id)->lockForUpdate()->first();

                    if (!$service || $service->upstream_server_id || $service->processing_invoice_id
                        || !in_array($service->status, ['awaiting_payment', 'provisioning_failed'], true)
                    ) {
                        throw new RuntimeException('En VPS er muligvis oprettet. Kontrollér Flax før arkivering.');
                    }

                    DB::table('nodexa_flax_services')->where('id', $service->id)->update([
                        'status' => 'cancelled', 'updated_at' => now(),
                    ]);
                    if ($service->first_invoice_id) {
                        DB::table('nodexa_invoices')->where('id', $service->first_invoice_id)->update([
                            'status' => 'refunded', 'updated_at' => now(),
                        ]);
                    }
                    if ($service->subscription_id) {
                        DB::table('nodexa_subscriptions')->where('id', $service->subscription_id)->update([
                            'status' => 'cancelled', 'next_invoice_at' => null, 'updated_at' => now(),
                        ]);
                    }
                } elseif ($item->type === 'game_server') {
                    $order = StoreOrder::query()->where('id', $item->reference_id)->lockForUpdate()->first();
                    if (!$order || $order->server_id || $order->provisioned_at) {
                        throw new RuntimeException('En gameserver er muligvis oprettet og skal undersøges.');
                    }

                    $order->status = 'refunded';
                    $order->save();

                    DB::table('nodexa_subscriptions')->where('order_id', $order->id)->update([
                        'status' => 'cancelled', 'next_invoice_at' => null, 'updated_at' => now(),
                    ]);
                } else {
                    throw new RuntimeException('Ukendt varetype. Ordren kan ikke arkiveres automatisk.');
                }
            }

            DB::table('nodexa_cart_checkouts')->where('id', $checkoutId)->update([
                'status' => 'refunded',
                'cancelled_at' => now(),
                'hidden_from_client' => true,
                'updated_at' => now(),
            ]);
        });

        return (string) $checkout->number;
    }

    /**
     * Exchange a fully paid, entirely failed order for site credit.
     * This is NOT a Stripe refund; staff must have customer consent and
     * verify that the upstream provider has delivered nothing.
     */
    public function refundFailedCheckoutToWallet(int $checkoutId, int $actorUserId): string
    {
        $checkout = DB::table('nodexa_cart_checkouts')->where('id', $checkoutId)->first();
        if (!$checkout) {
            throw new RuntimeException('Ordren findes ikke.');
        }

        $this->assertFailedWithoutActiveServices($checkoutId, $checkout);

        if (!in_array((string) $checkout->payment_method, ['stripe', 'wallet'], true)) {
            throw new RuntimeException('Kun betalinger via Stripe eller Wallet kan konverteres til kredit.');
        }

        if (strtoupper((string) $checkout->currency) !== 'DKK'
            || (int) round((float) $checkout->total * 100) <= 0
        ) {
            throw new RuntimeException('Wallet-kredit kræver en positiv betaling i DKK.');
        }

        if ($checkout->payment_method === 'stripe') {
            $this->stripe->assertEligibleForWalletCredit($checkout);
        } else {
            $debit = DB::table('nodexa_wallet_transactions')
                ->where('idempotency_key', 'wallet-checkout-' . $checkoutId)
                ->where('user_id', $checkout->user_id)
                ->where('type', 'wallet_payment')->first();

            if (!$debit || (int) $debit->amount_cents !== -((int) round((float) $checkout->total * 100))) {
                throw new RuntimeException('Wallet-betalingen kan ikke verificeres.');
            }
        }

        DB::transaction(function () use ($checkoutId, $actorUserId) {
            $locked = DB::table('nodexa_cart_checkouts')->where('id', $checkoutId)
                ->lockForUpdate()->first();

            if (!$locked) {
                throw new RuntimeException('Ordren blev ikke fundet.');
            }

            $this->assertFailedWithoutActiveServices($checkoutId, $locked);

            $amountCents = (int) round((float) $locked->total * 100);
            $items = DB::table('nodexa_cart_checkout_items')
                ->where('checkout_id', $checkoutId)->get();

            // Cancel only LOCAL failed records. An operator must first confirm
            // there is no server at Flax/Pterodactyl that could bill again.
            foreach ($items as $item) {
                if (!$item->reference_id) {
                    continue;
                }

                if ($item->type === 'flax_vps') {
                    $service = DB::table('nodexa_flax_services')
                        ->where('id', $item->reference_id)->lockForUpdate()->first();

                    if (!$service || $service->upstream_server_id || $service->processing_invoice_id
                        || !in_array($service->status, ['awaiting_payment', 'provisioning_failed'], true)
                    ) {
                        throw new RuntimeException('VPS findes muligvis hos Flax. Kredit er blokeret.');
                    }

                    DB::table('nodexa_flax_services')->where('id', $service->id)->update([
                        'status' => 'cancelled', 'updated_at' => now(),
                    ]);

                    if ($service->first_invoice_id) {
                        DB::table('nodexa_invoices')->where('id', $service->first_invoice_id)->update([
                            'status' => 'wallet_credited', 'updated_at' => now(),
                        ]);
                    }

                    if ($service->subscription_id) {
                        DB::table('nodexa_subscriptions')->where('id', $service->subscription_id)->update([
                            'status' => 'cancelled', 'next_invoice_at' => null, 'updated_at' => now(),
                        ]);
                    }
                } elseif ($item->type === 'game_server') {
                    $order = StoreOrder::query()->where('id', $item->reference_id)
                        ->lockForUpdate()->first();

                    if (!$order || $order->server_id || $order->provisioned_at) {
                        throw new RuntimeException('Gameserver findes muligvis. Kredit er blokeret.');
                    }

                    $order->status = 'wallet_credited';
                    $order->save();

                    DB::table('nodexa_subscriptions')->where('order_id', $order->id)->update([
                        'status' => 'cancelled', 'next_invoice_at' => null, 'updated_at' => now(),
                    ]);
                } else {
                    throw new RuntimeException('Ukendt servicerække.');
                }
            }

            $this->wallet->credit(
                (int) $locked->user_id,
                $amountCents,
                'refund_credit',
                'wallet-refund-checkout-' . $checkoutId,
                'Kredit for ikke-leveret ordre ' . $locked->number,
                $actorUserId,
                'cart_checkout',
                $checkoutId
            );

            DB::table('nodexa_cart_checkouts')->where('id', $checkoutId)->update([
                'status' => 'wallet_refunded',
                'cancelled_at' => now(),
                'hidden_from_client' => true,
                'updated_at' => now(),
            ]);
        }, 3);

        return (string) $checkout->number;
    }

    private function assertFailedWithoutActiveServices(int $checkoutId, object $checkout): void
    {
        if ($checkout->status !== 'partial' || !$checkout->paid_at
            || (bool) ($checkout->hidden_from_client ?? false)
        ) {
            throw new RuntimeException('Kun fejlede, betalte ordrer kan arkiveres efter refundering.');
        }

        $items = DB::table('nodexa_cart_checkout_items')->where('checkout_id', $checkoutId)->get();
        if ($items->isEmpty()) {
            throw new RuntimeException('Ordren har ingen linjer at verificere.');
        }

        foreach ($items as $item) {
            if (in_array($item->status, ['completed', 'processing'], true)) {
                throw new RuntimeException('Ordren indeholder en igangværende eller leveret service.');
            }

            if ($item->reference_id && $item->type === 'flax_vps') {
                $service = DB::table('nodexa_flax_services')->where('id', $item->reference_id)->first();
                if (!$service || $service->upstream_server_id || $service->processing_invoice_id
                    || !in_array($service->status, ['awaiting_payment', 'provisioning_failed'], true)
                ) {
                    throw new RuntimeException('VPS-servicen skal kontrolleres, før ordren kan arkiveres.');
                }
            } elseif ($item->reference_id && $item->type === 'game_server') {
                $order = StoreOrder::query()->find($item->reference_id);
                if (!$order || $order->server_id || $order->provisioned_at) {
                    throw new RuntimeException('Gameserveren skal kontrolleres, før ordren kan arkiveres.');
                }
            } elseif ($item->reference_id) {
                throw new RuntimeException('Ukendt service-reference i ordren.');
            }
        }
    }

    public function removeLegacyOrder(int $orderId, int $userId): void
    {
        DB::transaction(function () use ($orderId, $userId) {
            $order = StoreOrder::query()
                ->where('id', $orderId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if (!$order) {
                throw new RuntimeException('Ordren blev ikke fundet.');
            }

            if (!in_array($order->status, ['awaiting_payment', 'failed'], true)
                || $order->paid_at
                || $order->server_id
                || $order->provisioned_at
                || $order->payment_reference
                || $order->payment_method
                || DB::table('nodexa_subscriptions')->where('order_id', $order->id)->exists()
                || DB::table('nodexa_cart_checkout_items')
                    ->where('type', 'game_server')
                    ->where('reference_id', $order->id)->exists()
            ) {
                throw new RuntimeException('Kun ubetalte ordrer uden aktive services kan slettes.');
            }

            $order->status = 'cancelled';
            $order->cancelled_at = now();
            $order->hidden_from_client = true;
            $order->save();
        });
    }

    private function assertUnpaidCheckout(object $checkout): void
    {
        if ($checkout->status !== 'awaiting_payment'
            || $checkout->paid_at
            || $checkout->payment_reference
            || $checkout->payment_method
            || $checkout->stripe_payment_intent_id
            || in_array((string) ($checkout->stripe_status ?? ''), ['paid', 'complete', 'completed'], true)
        ) {
            throw new RuntimeException('Ordren kan ikke slettes, fordi betaling eller levering kan være startet. Betalte fejlordrer kræver refundering og manuel behandling.');
        }

        $hasLinkedService = DB::table('nodexa_cart_checkout_items')
            ->where('checkout_id', $checkout->id)
            ->where(function ($query) {
                $query->whereNotNull('reference_id')
                    ->orWhereIn('status', ['processing', 'completed']);
            })
            ->exists();

        if ($hasLinkedService) {
            throw new RuntimeException('Ordren har en tilknyttet service eller igangværende levering og kan ikke slettes.');
        }
    }
}
