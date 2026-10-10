<?php

namespace Pterodactyl\Services\Nodexa;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CfxEupService
{
    public function __construct(private NodexaEventService $events)
    {
    }

    public function addKey(string $key, ?string $label, ?string $notes, ?int $createdBy): int
    {
        $key = trim($key);
        if ($key === '') {
            throw new RuntimeException('EUP key må ikke være tom.');
        }

        $fingerprint = hash('sha256', $key);
        if (DB::table('nodexa_cfx_eup_keys')->where('key_fingerprint', $fingerprint)->exists()) {
            throw new RuntimeException('Denne EUP key findes allerede i systemet.');
        }

        return DB::table('nodexa_cfx_eup_keys')->insertGetId([
            'label' => trim((string) $label) ?: null,
            'key_encrypted' => Crypt::encryptString($key),
            'key_fingerprint' => $fingerprint,
            'status' => 'available',
            'created_by' => $createdBy,
            'notes' => trim((string) $notes) ?: null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function createRecurringOrder(
        int $userId,
        float $monthlyPrice,
        string $currency,
        string $description,
        ?int $keyId,
        ?int $createdBy
    ): array {
        return DB::transaction(function () use ($userId, $monthlyPrice, $currency, $description, $keyId, $createdBy) {
            $key = $keyId
                ? DB::table('nodexa_cfx_eup_keys')->where('id', $keyId)->lockForUpdate()->first()
                : DB::table('nodexa_cfx_eup_keys')->where('status', 'available')->orderBy('id')->lockForUpdate()->first();

            if (!$key || $key->status !== 'available') {
                throw new RuntimeException('Der er ingen ledig CFX EUP key til denne ordre.');
            }

            $subscriptionId = DB::table('nodexa_subscriptions')->insertGetId([
                'user_id' => $userId,
                'server_id' => null,
                'order_id' => null,
                'service_type' => 'cfx_eup',
                'service_reference' => null,
                'description' => $description,
                'status' => 'pending',
                'amount' => $monthlyPrice,
                'currency' => strtoupper($currency),
                'interval' => 'monthly',
                'next_invoice_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $number = 'NX-EUP-' . now()->format('Ym') . '-' . strtoupper(Str::random(7));
            $invoiceId = DB::table('nodexa_invoices')->insertGetId([
                'user_id' => $userId,
                'subscription_id' => $subscriptionId,
                'number' => $number,
                'status' => 'unpaid',
                'subtotal' => $monthlyPrice,
                'tax' => 0,
                'total' => $monthlyPrice,
                'currency' => strtoupper($currency),
                'due_at' => now()->addDays(7),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('nodexa_invoice_items')->insert([
                'invoice_id' => $invoiceId,
                'description' => $description . ' — månedligt abonnement',
                'quantity' => 1,
                'unit_price' => $monthlyPrice,
                'total' => $monthlyPrice,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $orderId = DB::table('nodexa_cfx_eup_orders')->insertGetId([
                'user_id' => $userId,
                'eup_key_id' => $key->id,
                'subscription_id' => $subscriptionId,
                'first_invoice_id' => $invoiceId,
                'created_by' => $createdBy,
                'status' => 'awaiting_payment',
                'description' => $description,
                'monthly_price' => $monthlyPrice,
                'currency' => strtoupper($currency),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('nodexa_subscriptions')->where('id', $subscriptionId)->update([
                'service_reference' => $orderId,
                'updated_at' => now(),
            ]);

            DB::table('nodexa_cfx_eup_keys')->where('id', $key->id)->update([
                'status' => 'reserved',
                'assigned_user_id' => $userId,
                'assigned_order_id' => $orderId,
                'updated_at' => now(),
            ]);

            $this->events->notify(
                $userId,
                'CFX EUP ordre oprettet',
                $description . ' er reserveret til dig. Din EUP key bliver synlig, når første faktura er betalt.',
                'billing',
                '/client/eup-keys'
            );

            $this->events->emit('cfx_eup.order.created', [
                'order_id' => $orderId,
                'subscription_id' => $subscriptionId,
                'invoice_id' => $invoiceId,
                'monthly_price' => $monthlyPrice,
                'currency' => strtoupper($currency),
            ], $userId);

            return compact('orderId', 'subscriptionId', 'invoiceId');
        });
    }

    public function assignDirect(int $userId, int $keyId, string $description, ?int $createdBy): int
    {
        return DB::transaction(function () use ($userId, $keyId, $description, $createdBy) {
            $key = DB::table('nodexa_cfx_eup_keys')->where('id', $keyId)->lockForUpdate()->first();
            if (!$key || $key->status !== 'available') {
                throw new RuntimeException('Denne EUP key er ikke ledig.');
            }

            $orderId = DB::table('nodexa_cfx_eup_orders')->insertGetId([
                'user_id' => $userId,
                'eup_key_id' => $keyId,
                'subscription_id' => null,
                'first_invoice_id' => null,
                'created_by' => $createdBy,
                'status' => 'active',
                'description' => $description,
                'monthly_price' => 0,
                'currency' => 'DKK',
                'activated_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('nodexa_cfx_eup_keys')->where('id', $keyId)->update([
                'status' => 'assigned',
                'assigned_user_id' => $userId,
                'assigned_order_id' => $orderId,
                'assigned_at' => now(),
                'updated_at' => now(),
            ]);

            $this->events->notify(
                $userId,
                'CFX EUP key tildelt',
                $description . ' er nu tilgængelig på din Nodexa-konto.',
                'service',
                '/client/eup-keys'
            );

            return $orderId;
        });
    }

    public function activateInvoice(int $invoiceId): void
    {
        $invoice = DB::table('nodexa_invoices')->where('id', $invoiceId)->first();
        if (!$invoice?->subscription_id) {
            return;
        }

        $subscription = DB::table('nodexa_subscriptions')->where('id', $invoice->subscription_id)->first();
        if (!$subscription || $subscription->service_type !== 'cfx_eup' || !$subscription->service_reference) {
            return;
        }

        DB::transaction(function () use ($subscription, $invoice) {
            $order = DB::table('nodexa_cfx_eup_orders')->where('id', $subscription->service_reference)->lockForUpdate()->first();
            if (!$order) {
                return;
            }

            DB::table('nodexa_subscriptions')->where('id', $subscription->id)->update([
                'status' => 'active',
                'next_invoice_at' => $subscription->next_invoice_at ?: now()->addMonth(),
                'updated_at' => now(),
            ]);

            DB::table('nodexa_cfx_eup_orders')->where('id', $order->id)->update([
                'status' => 'active',
                'activated_at' => $order->activated_at ?: now(),
                'suspended_at' => null,
                'updated_at' => now(),
            ]);

            if ($order->eup_key_id) {
                DB::table('nodexa_cfx_eup_keys')->where('id', $order->eup_key_id)->update([
                    'status' => 'assigned',
                    'assigned_user_id' => $order->user_id,
                    'assigned_order_id' => $order->id,
                    'assigned_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->events->notify(
                $order->user_id,
                'CFX EUP abonnement aktivt',
                $order->description . ' er aktivt, og din key kan nu ses under CFX EUP Keys.',
                'service',
                '/client/eup-keys'
            );

            $this->events->emit('cfx_eup.activated', [
                'order_id' => $order->id,
                'invoice_id' => $invoice->id,
            ], $order->user_id);
        });
    }

    public function suspendSubscription(int $subscriptionId): void
    {
        $subscription = DB::table('nodexa_subscriptions')->where('id', $subscriptionId)->first();
        if (!$subscription || $subscription->service_type !== 'cfx_eup' || !$subscription->service_reference) {
            return;
        }

        $order = DB::table('nodexa_cfx_eup_orders')->where('id', $subscription->service_reference)->first();
        if (!$order || in_array($order->status, ['cancelled', 'awaiting_payment'], true)) {
            return;
        }

        DB::table('nodexa_cfx_eup_orders')->where('id', $order->id)->update([
            'status' => 'suspended',
            'suspended_at' => now(),
            'updated_at' => now(),
        ]);

        if ($order->eup_key_id) {
            DB::table('nodexa_cfx_eup_keys')->where('id', $order->eup_key_id)->update([
                'status' => 'suspended',
                'updated_at' => now(),
            ]);
        }

        $this->events->notify(
            $order->user_id,
            'CFX EUP abonnement suspenderet',
            'Din EUP key er skjult i Nodexa, fordi en faktura er forfalden.',
            'warning',
            '/client/billing'
        );
    }

    public function cancelOrder(int $orderId): void
    {
        DB::transaction(function () use ($orderId) {
            $order = DB::table('nodexa_cfx_eup_orders')->where('id', $orderId)->lockForUpdate()->first();
            if (!$order) {
                throw new RuntimeException('EUP ordre blev ikke fundet.');
            }

            DB::table('nodexa_cfx_eup_orders')->where('id', $orderId)->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'updated_at' => now(),
            ]);

            if ($order->subscription_id) {
                DB::table('nodexa_subscriptions')->where('id', $order->subscription_id)->update([
                    'status' => 'cancelled',
                    'cancel_at' => now(),
                    'next_invoice_at' => null,
                    'updated_at' => now(),
                ]);
            }

            if ($order->eup_key_id) {
                DB::table('nodexa_cfx_eup_keys')->where('id', $order->eup_key_id)->update([
                    'status' => 'available',
                    'assigned_user_id' => null,
                    'assigned_order_id' => null,
                    'assigned_at' => null,
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function decryptKey(string $encrypted): string
    {
        return Crypt::decryptString($encrypted);
    }
}
