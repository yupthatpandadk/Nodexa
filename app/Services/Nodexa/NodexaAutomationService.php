<?php

namespace Pterodactyl\Services\Nodexa;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Pterodactyl\Models\Node;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Backups\DeleteBackupService;
use Pterodactyl\Services\Backups\InitiateBackupService;
use Pterodactyl\Services\Servers\SuspensionService;

class NodexaAutomationService
{
    public function __construct(
        private InitiateBackupService $backups,
        private DeleteBackupService $deleteBackups,
        private SuspensionService $suspension,
        private NodexaEventService $events,
        private CfxEupService $cfxEup,
        private FlaxResellerService $flaxReseller,
    ) {
    }

    public function run(): array
    {
        $health = $this->runHealthChecks();
        $backups = $this->runBackupPolicies();
        $billing = $this->runBilling();
        $flax = Schema::hasTable('nodexa_flax_services') ? $this->flaxReseller->syncStaleServices() : 0;

        return compact('health', 'backups', 'billing', 'flax');
    }

    public function runHealthChecks(): int
    {
        $count = 0;

        foreach (Node::query()->orderBy('id')->get() as $node) {
            $started = microtime(true);
            $errno = 0;
            $error = '';

            $socket = @fsockopen($node->fqdn, $node->daemonListen, $errno, $error, 2.0);
            $latency = (int) round((microtime(true) - $started) * 1000);
            $status = $socket ? 'online' : 'offline';

            if (is_resource($socket)) {
                fclose($socket);
            }

            DB::table('nodexa_health_checks')->insert([
                'node_id' => $node->id,
                'status' => $status,
                'latency_ms' => $latency,
                'message' => $status === 'online' ? null : substr($error ?: ('Connection error ' . $errno), 0, 1000),
                'checked_at' => now(),
            ]);

            DB::table('nodexa_health_checks')
                ->where('node_id', $node->id)
                ->where('checked_at', '<', now()->subDays(30))
                ->delete();

            $count++;
        }

        $latest = DB::table('nodexa_health_checks as h')
            ->joinSub(
                DB::table('nodexa_health_checks')
                    ->selectRaw('node_id, MAX(checked_at) as checked_at')
                    ->groupBy('node_id'),
                'latest',
                function ($join) {
                    $join->on('h.node_id', '=', 'latest.node_id')
                        ->on('h.checked_at', '=', 'latest.checked_at');
                }
            )
            ->pluck('h.status');

        if ($latest->isNotEmpty()) {
            $offline = $latest->filter(fn ($status) => $status !== 'online')->count();
            $status = $offline === 0
                ? 'operational'
                : ($offline === $latest->count() ? 'major_outage' : 'degraded');

            DB::table('nodexa_status_components')->where('slug', 'game-nodes')->update([
                'status' => $status,
                'updated_at' => now(),
            ]);
        }

        return $count;
    }

    public function runBackupPolicies(): int
    {
        $policies = DB::table('nodexa_backup_policies')
            ->where('enabled', true)
            ->where(function ($query) {
                $query->whereNull('next_run_at')->orWhere('next_run_at', '<=', now());
            })
            ->get();

        $count = 0;

        foreach ($policies as $policy) {
            $server = Server::query()->find($policy->server_id);

            if (!$server || $server->owner_id !== $policy->user_id) {
                DB::table('nodexa_backup_policies')->where('id', $policy->id)->update([
                    'last_status' => 'failed',
                    'last_error' => 'Server not found or ownership changed.',
                    'next_run_at' => now()->addHours(max(1, (int) $policy->frequency_hours)),
                    'updated_at' => now(),
                ]);
                continue;
            }

            try {
                $retention = max(1, min((int) $policy->retention_count, max(1, $server->backup_limit)));
                $existing = $server->backups()
                    ->where('is_successful', true)
                    ->where('is_locked', false)
                    ->orderByDesc('created_at')
                    ->get();

                foreach ($existing->slice(max(0, $retention - 1)) as $oldBackup) {
                    try {
                        $this->deleteBackups->handle($oldBackup);
                    } catch (\Throwable $exception) {
                        report($exception);
                    }
                }

                $backup = $this->backups->handle(
                    $server,
                    trim((string) $policy->name_prefix) . ' ' . now()->format('Y-m-d H:i'),
                    true
                );

                DB::table('nodexa_backup_policies')->where('id', $policy->id)->update([
                    'last_run_at' => now(),
                    'last_status' => 'started',
                    'last_error' => null,
                    'next_run_at' => now()->addHours(max(1, (int) $policy->frequency_hours)),
                    'updated_at' => now(),
                ]);

                $this->events->notify(
                    $policy->user_id,
                    'Automatisk backup startet',
                    'Backup af ' . $server->name . ' er startet.',
                    'backup',
                    '/server/' . $server->uuidShort . '/backups',
                    ['backup_uuid' => $backup->uuid]
                );

                $count++;
            } catch (\Throwable $exception) {
                report($exception);
                DB::table('nodexa_backup_policies')->where('id', $policy->id)->update([
                    'last_run_at' => now(),
                    'last_status' => 'failed',
                    'last_error' => substr($exception->getMessage(), 0, 2000),
                    'next_run_at' => now()->addHours(max(1, (int) $policy->frequency_hours)),
                    'updated_at' => now(),
                ]);
            }
        }

        return $count;
    }

    public function runBilling(): int
    {
        $created = 0;

        // Adopt existing paid/active Storefront orders into recurring billing.
        if (Schema::hasTable('nodexa_store_orders')) {
            $orders = DB::table('nodexa_store_orders')
                ->whereIn('status', ['paid', 'active'])
                ->whereNotNull('server_id')
                ->get();

            foreach ($orders as $order) {
                $exists = DB::table('nodexa_subscriptions')->where('order_id', $order->id)->exists();
                if (!$exists) {
                    DB::table('nodexa_subscriptions')->insert([
                        'user_id' => $order->user_id,
                        'server_id' => $order->server_id,
                        'order_id' => $order->id,
                        'status' => 'active',
                        'amount' => $order->amount,
                        'currency' => $order->currency ?: 'DKK',
                        'interval' => 'monthly',
                        'next_invoice_at' => now()->addMonth(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        $subscriptions = DB::table('nodexa_subscriptions')
            ->where('status', 'active')
            ->whereNotNull('next_invoice_at')
            ->where('next_invoice_at', '<=', now())
            ->get();

        foreach ($subscriptions as $subscription) {
            $number = 'NX-' . now()->format('Ym') . '-' . strtoupper(Str::random(8));
            $description = trim((string) ($subscription->description ?? '')) ?: 'Nodexa hosting subscription';

            $subtotal = round((float) $subscription->amount, 2);

            $saleMonthsTotal = max(0, (int) ($subscription->sale_months_total ?? 0));
            $saleMonthsUsed = max(0, (int) ($subscription->sale_months_used ?? 0));
            $saleDiscount = 0.0;

            if ($saleMonthsTotal > 0 && $saleMonthsUsed < $saleMonthsTotal) {
                $saleDiscount = min(
                    $subtotal,
                    max(0.0, round((float) ($subscription->sale_discount_amount ?? 0), 2))
                );
            }

            $afterSale = max(0.0, round($subtotal - $saleDiscount, 2));

            $discountMonthsTotal = max(0, (int) ($subscription->discount_months_total ?? 0));
            $discountMonthsUsed = max(0, (int) ($subscription->discount_months_used ?? 0));
            $couponDiscount = 0.0;

            if (
                !empty($subscription->discount_code)
                && $discountMonthsTotal > 0
                && $discountMonthsUsed < $discountMonthsTotal
            ) {
                if (($subscription->discount_type ?? null) === 'percent') {
                    $percent = max(0.0, min(100.0, (float) ($subscription->discount_value ?? 0)));
                    $couponDiscount = round($afterSale * ($percent / 100), 2);
                } else {
                    $couponDiscount = max(0.0, round((float) ($subscription->discount_amount ?? 0), 2));
                }

                $couponDiscount = min($afterSale, $couponDiscount);
            }

            $discount = round($saleDiscount + $couponDiscount, 2);
            $total = max(0.0, round($subtotal - $discount, 2));

            $saleMonthNumber = $saleDiscount > 0 ? ($saleMonthsUsed + 1) : null;
            $discountMonthNumber = $couponDiscount > 0 ? ($discountMonthsUsed + 1) : null;

            $invoiceId = DB::table('nodexa_invoices')->insertGetId([
                'user_id' => $subscription->user_id,
                'subscription_id' => $subscription->id,
                'number' => $number,
                'status' => 'unpaid',
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => 0,
                'total' => $total,
                'currency' => $subscription->currency,
                'due_at' => now()->addDays(7),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $itemDescription = $description;

            if ($saleDiscount > 0) {
                $itemDescription .= ' — Globalt udsalg'
                    . ' (måned ' . $saleMonthNumber . '/' . $saleMonthsTotal . ')';
            }

            if ($couponDiscount > 0) {
                $itemDescription .= ' — Rabatkode ' . $subscription->discount_code
                    . ' (måned ' . $discountMonthNumber . '/' . $discountMonthsTotal . ')';
            }

            DB::table('nodexa_invoice_items')->insert([
                'invoice_id' => $invoiceId,
                'description' => $itemDescription,
                'quantity' => 1,
                'unit_price' => $subtotal,
                'total' => $total,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $next = match ($subscription->interval) {
                'yearly' => now()->addYear(),
                'quarterly' => now()->addMonths(3),
                default => now()->addMonth(),
            };

            $subscriptionUpdate = [
                'next_invoice_at' => $next,
                'updated_at' => now(),
            ];

            if ($saleDiscount > 0) {
                $subscriptionUpdate['sale_months_used'] = $saleMonthNumber;
            }

            if ($couponDiscount > 0) {
                $subscriptionUpdate['discount_months_used'] = $discountMonthNumber;
            }

            DB::table('nodexa_subscriptions')->where('id', $subscription->id)->update($subscriptionUpdate);

            $notification = 'En ny faktura på ' . number_format($total, 2, ',', '.') . ' ' . $subscription->currency
                . ' er klar for ' . $description . '.';

            if ($saleDiscount > 0) {
                $notification .= ' Globalt udsalg er anvendt (måned '
                    . $saleMonthNumber . '/' . $saleMonthsTotal . ').';
            }

            if ($couponDiscount > 0) {
                $notification .= ' Rabatkode ' . $subscription->discount_code
                    . ' er anvendt (måned ' . $discountMonthNumber . '/' . $discountMonthsTotal . ').';
            }

            $this->events->notify(
                $subscription->user_id,
                'Ny faktura ' . $number,
                $notification,
                'billing',
                '/client/billing'
            );

            $this->events->emit('invoice.created', [
                'invoice_id' => $invoiceId,
                'number' => $number,
                'subtotal' => $subtotal,
                'sale_discount' => $saleDiscount,
                'coupon_discount' => $couponDiscount,
                'discount' => $discount,
                'total' => $total,
                'currency' => $subscription->currency,
                'sale_month' => $saleMonthNumber,
                'sale_months_total' => $saleMonthsTotal,
                'discount_code' => $couponDiscount > 0 ? $subscription->discount_code : null,
                'discount_month' => $discountMonthNumber,
                'discount_months_total' => $discountMonthsTotal,
                'service_type' => $subscription->service_type ?? null,
            ], $subscription->user_id);

            $created++;
        }
        $reminders = DB::table('nodexa_invoices')
            ->where('status', 'unpaid')
            ->whereNull('reminder_sent_at')
            ->whereNotNull('due_at')
            ->where('due_at', '<=', now()->addDays(3))
            ->where('due_at', '>=', now())
            ->get();

        foreach ($reminders as $invoice) {
            $this->events->notify(
                $invoice->user_id,
                'Betalingspåmindelse ' . $invoice->number,
                'Fakturaen på ' . number_format((float) $invoice->total, 2, ',', '.') . ' ' . $invoice->currency . ' forfalder snart.',
                'billing',
                '/client/billing'
            );

            DB::table('nodexa_invoices')->where('id', $invoice->id)->update([
                'reminder_sent_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $overdue = DB::table('nodexa_invoices')
            ->where('status', 'unpaid')
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->get();

        foreach ($overdue as $invoice) {
            DB::table('nodexa_invoices')->where('id', $invoice->id)->update([
                'status' => 'overdue',
                'overdue_notified_at' => now(),
                'updated_at' => now(),
            ]);

            $this->events->notify(
                $invoice->user_id,
                'Faktura forfalden ' . $invoice->number,
                'Fakturaen er forfalden. Tilknyttede services kan blive suspenderet, indtil betalingen er registreret.',
                'warning',
                '/client/billing'
            );

            $subscription = $invoice->subscription_id
                ? DB::table('nodexa_subscriptions')->where('id', $invoice->subscription_id)->first()
                : null;

            if ($subscription?->server_id) {
                $server = Server::query()->find($subscription->server_id);
                if ($server && !$server->isSuspended()) {
                    try {
                        $this->suspension->toggle($server, SuspensionService::ACTION_SUSPEND);
                        $this->events->notify(
                            $invoice->user_id,
                            'Service suspenderet',
                            'Serveren ' . $server->name . ' er suspenderet på grund af en forfalden faktura.',
                            'warning',
                            '/client/billing'
                        );
                    } catch (\Throwable $exception) {
                        report($exception);
                    }
                }
            }

            if (($subscription->service_type ?? null) === 'cfx_eup') {
                try {
                    $this->cfxEup->suspendSubscription($subscription->id);
                } catch (\Throwable $exception) {
                    report($exception);
                }
            }

            if (($subscription->service_type ?? null) === 'flax_vps') {
                try {
                    $this->flaxReseller->suspendSubscription($subscription->id);
                } catch (\Throwable $exception) {
                    report($exception);
                }
            }
        }

        // Re-activate CFX EUP entitlements automatically when payment has been
        // registered and no other invoice on the subscription remains overdue.
        if (Schema::hasTable('nodexa_cfx_eup_orders')) {
            $cfxSubscriptions = DB::table('nodexa_subscriptions')
                ->where('service_type', 'cfx_eup')
                ->whereIn('status', ['pending', 'active'])
                ->get();

            foreach ($cfxSubscriptions as $subscription) {
                $hasOverdue = DB::table('nodexa_invoices')
                    ->where('subscription_id', $subscription->id)
                    ->where('status', 'overdue')
                    ->exists();

                if ($hasOverdue) {
                    continue;
                }

                $paidInvoice = DB::table('nodexa_invoices')
                    ->where('subscription_id', $subscription->id)
                    ->where('status', 'paid')
                    ->orderByDesc('paid_at')
                    ->orderByDesc('id')
                    ->first();

                if ($paidInvoice) {
                    try {
                        $this->cfxEup->activateInvoice($paidInvoice->id);
                    } catch (\Throwable $exception) {
                        report($exception);
                    }
                }
            }
        }

        if (Schema::hasTable('nodexa_flax_services')) {
            $flaxSubscriptions = DB::table('nodexa_subscriptions')
                ->where('service_type', 'flax_vps')
                ->whereIn('status', ['pending', 'active'])
                ->get();

            foreach ($flaxSubscriptions as $subscription) {
                $hasOverdue = DB::table('nodexa_invoices')
                    ->where('subscription_id', $subscription->id)
                    ->where('status', 'overdue')
                    ->exists();

                $paidInvoice = DB::table('nodexa_invoices')
                    ->where('subscription_id', $subscription->id)
                    ->where('status', 'paid')
                    ->orderByDesc('paid_at')
                    ->orderByDesc('id')
                    ->first();

                if ($paidInvoice) {
                    try {
                        $this->flaxReseller->activateInvoice($paidInvoice->id);
                    } catch (\Throwable $exception) {
                        report($exception);
                    }
                }

                if (!$hasOverdue) {
                    try {
                        $this->flaxReseller->unsuspendSubscription($subscription->id);
                    } catch (\Throwable $exception) {
                        report($exception);
                    }
                }
            }
        }

        return $created;
    }
}
