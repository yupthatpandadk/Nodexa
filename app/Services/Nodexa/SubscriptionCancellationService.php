<?php

namespace Pterodactyl\Services\Nodexa;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\StoreOrder;
use Pterodactyl\Services\Servers\SuspensionService;
use RuntimeException;

class SubscriptionCancellationService
{
    public function __construct(
        private FlaxResellerService $flax,
        private CfxEupService $eup,
        private SuspensionService $suspension,
        private NodexaEventService $events,
    ) {
    }

    /**
     * Stop future invoicing immediately, and end the service only when the
     * customer's already-paid billing period has expired.
     */
    public function requestCancellation(int $subscriptionId, int $userId, ?string $reason = null): Carbon
    {
        $end = DB::transaction(function () use ($subscriptionId, $userId, $reason) {
            $subscription = DB::table('nodexa_subscriptions')
                ->where('id', $subscriptionId)->where('user_id', $userId)
                ->lockForUpdate()->first();

            if (!$subscription) {
                throw new RuntimeException('Abonnementet blev ikke fundet på denne konto.');
            }

            if ($subscription->status === 'cancel_pending' && $subscription->cancel_at) {
                return Carbon::parse($subscription->cancel_at);
            }

            if ($subscription->status !== 'active') {
                throw new RuntimeException('Kun aktive abonnementer kan opsiges. Kontakt support ved en afventende ordre.');
            }

            $this->assertServiceOwned($subscription);

            if (!$subscription->next_invoice_at) {
                throw new RuntimeException('Næste fakturadato mangler. Kontakt support, så din betalte periode kan verificeres.');
            }

            $end = Carbon::parse($subscription->next_invoice_at);

            if (($subscription->service_type ?? null) === 'flax_vps') {
                $vps = DB::table('nodexa_flax_services')
                    ->where('id', $subscription->service_reference)->first();
                if ($vps?->expires_at) {
                    // Do not end a VPS earlier than the upstream period
                    // already paid for at Flax.
                    $upstreamEnd = Carbon::parse($vps->expires_at);
                    if ($upstreamEnd->greaterThan($end)) {
                        $end = $upstreamEnd;
                    }
                }
            }

            if ($end->lessThanOrEqualTo(now())) {
                throw new RuntimeException('Faktureringsperioden er allerede udløbet. Kontakt support for manuel opsigelse.');
            }

            DB::table('nodexa_subscriptions')->where('id', $subscriptionId)->update([
                'status' => 'cancel_pending',
                'cancel_at' => $end,
                'cancellation_requested_at' => now(),
                'cancellation_completed_at' => null,
                'cancellation_reason' => trim((string) $reason) ?: null,
                'cancellation_last_error' => null,
                'updated_at' => now(),
            ]);

            return $end;
        }, 3);

        $this->events->notify(
            $userId,
            'Opsigelse registreret',
            'Abonnement #' . $subscriptionId . ' er opsagt til ' . $end->format('d.m.Y H:i') .
                '. Der oprettes ikke nye fornyelsesfakturaer. Du kan fortryde frem til udløbet.',
            'billing',
            '/client/billing'
        );
        $this->events->audit($userId, 'billing', 'subscription.cancel_requested',
            'Customer requested cancellation at paid-through period end',
            'subscription', $subscriptionId, ['cancel_at' => $end->toIso8601String()]);

        return $end;
    }

    public function undoCancellation(int $subscriptionId, int $userId): void
    {
        DB::transaction(function () use ($subscriptionId, $userId) {
            $subscription = DB::table('nodexa_subscriptions')
                ->where('id', $subscriptionId)->where('user_id', $userId)
                ->lockForUpdate()->first();

            if (!$subscription || $subscription->status !== 'cancel_pending'
                || !$subscription->cancel_at || Carbon::parse($subscription->cancel_at)->lessThanOrEqualTo(now())
            ) {
                throw new RuntimeException('Opsigelsen kan ikke længere fortrydes. Kontakt support.');
            }

            $this->assertServiceOwned($subscription);

            DB::table('nodexa_subscriptions')->where('id', $subscriptionId)->update([
                'status' => 'active',
                'cancel_at' => null,
                'cancellation_requested_at' => null,
                'cancellation_reason' => null,
                'cancellation_last_error' => null,
                'updated_at' => now(),
            ]);
        }, 3);

        $this->events->notify($userId, 'Opsigelse fortrudt',
            'Abonnement #' . $subscriptionId . ' fornyes igen efter den nuværende periode.',
            'billing', '/client/billing');
        $this->events->audit($userId, 'billing', 'subscription.cancel_undone',
            'Customer restored subscription renewal', 'subscription', $subscriptionId);
    }

    /**
     * Process subscriptions whose paid-through period has expired.
     * Provider failures remain pending for the next scheduled retry.
     */
    public function processDue(int $limit = 50): int
    {
        $subscriptions = DB::table('nodexa_subscriptions')
            ->where('status', 'cancel_pending')
            ->whereNotNull('cancel_at')
            ->where('cancel_at', '<=', now())
            ->orderBy('cancel_at')->limit(max(1, min($limit, 100)))->get();

        $done = 0;
        foreach ($subscriptions as $subscription) {
            try {
                if (!$this->finalize($subscription)) {
                    continue;
                }

                $done++;
                $this->events->notify((int) $subscription->user_id,
                    'Abonnement afsluttet',
                    'Abonnement #' . $subscription->id . ' er afsluttet ved udløbet af den betalte periode.',
                    'billing', '/client/billing');
                $this->events->audit(null, 'billing', 'subscription.cancel_completed',
                    'Subscription cancelled at period end by automation',
                    'subscription', (int) $subscription->id);
            } catch (\Throwable $exception) {
                report($exception);
                DB::table('nodexa_subscriptions')
                    ->where('id', $subscription->id)
                    ->where('status', 'cancel_pending')
                    ->update([
                        'cancellation_last_error' => substr($exception->getMessage(), 0, 2000),
                        'updated_at' => now(),
                    ]);
            }
        }

        return $done;
    }

    private function finalize(object $snapshot): bool
    {
        $subscription = DB::table('nodexa_subscriptions')
            ->where('id', $snapshot->id)
            ->where('status', 'cancel_pending')
            ->where('cancel_at', '<=', now())
            ->first();

        if (!$subscription) {
            return false;
        }

        $type = (string) ($subscription->service_type ?? '');
        if ($type === 'flax_vps') {
            if (!$subscription->service_reference) {
                throw new RuntimeException('VPS-reference mangler. Automatisk ophør er blokeret.');
            }
            $vps = DB::table('nodexa_flax_services')->where('id', $subscription->service_reference)
                ->where('user_id', $subscription->user_id)
                ->where('subscription_id', $subscription->id)->first();

            if (!$vps) {
                throw new RuntimeException('VPS-reference kunne ikke verificeres.');
            }

            // Never mark the cancellation finished if provider deletion failed.
            $this->flax->terminateService((int) $vps->id, false);
        } elseif ($type === 'cfx_eup') {
            if (!$subscription->service_reference) {
                throw new RuntimeException('EUP-reference mangler. Automatisk ophør er blokeret.');
            }

            $eup = DB::table('nodexa_cfx_eup_orders')
                ->where('id', $subscription->service_reference)
                ->where('user_id', $subscription->user_id)
                ->where('subscription_id', $subscription->id)->first();

            if (!$eup) {
                throw new RuntimeException('EUP-reference kunne ikke verificeres.');
            }

            $this->eup->cancelOrder((int) $eup->id);
        } elseif ($type === '' || $type === 'game_server') {
            if (!$subscription->server_id || !$subscription->order_id) {
                throw new RuntimeException('Gameserver-reference mangler. Kontakt support.');
            }

            $order = StoreOrder::query()
                ->where('id', $subscription->order_id)
                ->where('user_id', $subscription->user_id)
                ->where('server_id', $subscription->server_id)
                ->first();

            if (!$order) {
                throw new RuntimeException('Gameserver-ordren kan ikke verificeres.');
            }

            $server = Server::query()->find($subscription->server_id);
            if ($server && !$server->isSuspended()) {
                // Preserve files for administrative cleanup; never erase
                // customer data as an unannounced side effect.
                $this->suspension->toggle($server, SuspensionService::ACTION_SUSPEND);
            }

            DB::table('nodexa_store_orders')->where('id', $order->id)->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            throw new RuntimeException('Ukendt servicetype: ' . $type);
        }

        DB::table('nodexa_subscriptions')
            ->where('id', $subscription->id)
            ->whereIn('status', ['cancel_pending', 'cancelled'])
            ->update([
                'status' => 'cancelled',
                'next_invoice_at' => null,
                'cancellation_completed_at' => now(),
                'cancellation_last_error' => null,
                'updated_at' => now(),
            ]);

        return true;
    }

    private function assertServiceOwned(object $subscription): void
    {
        $type = (string) ($subscription->service_type ?? '');

        if ($type === 'flax_vps') {
            $valid = DB::table('nodexa_flax_services')
                ->where('id', $subscription->service_reference)
                ->where('subscription_id', $subscription->id)
                ->where('user_id', $subscription->user_id)
                ->whereIn('status', ['active', 'suspended'])
                ->exists();
        } elseif ($type === 'cfx_eup') {
            $valid = DB::table('nodexa_cfx_eup_orders')
                ->where('id', $subscription->service_reference)
                ->where('subscription_id', $subscription->id)
                ->where('user_id', $subscription->user_id)
                ->whereIn('status', ['active', 'suspended'])
                ->exists();
        } elseif ($type === '' || $type === 'game_server') {
            $valid = (bool) ($subscription->server_id && $subscription->order_id)
                && StoreOrder::query()
                    ->where('id', $subscription->order_id)
                    ->where('server_id', $subscription->server_id)
                    ->where('user_id', $subscription->user_id)->exists();
        } else {
            throw new RuntimeException('Ukendt abonnementstype. Kontakt support.');
        }

        if (!$valid) {
            throw new RuntimeException('Produktet er ikke længere tilgængeligt på dette abonnement. Kontakt support.');
        }
    }
}
