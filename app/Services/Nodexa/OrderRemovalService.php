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
            $this->stripe->expireUnpaidCheckoutSession((string) $checkout->stripe_session_id);
        } elseif ((int) ($checkout->stripe_attempt ?? 0) > 0) {
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
