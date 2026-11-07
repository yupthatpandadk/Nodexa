<?php

namespace Pterodactyl\Services\Nodexa;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pterodactyl\Models\StoreOrder;
use Pterodactyl\Models\StoreProduct;
use RuntimeException;

class NodexaCartService
{
    public function __construct(
        private FlaxResellerService $flax,
        private NodexaEventService $events,
    ) {
    }

    public function resolve(array $cart): array
    {
        $items = [];
        $total = 0.0;

        foreach ($cart as $key => $raw) {
            if (!is_array($raw) || empty($raw['type']) || empty($raw['product_id'])) {
                continue;
            }

            if ($raw['type'] === 'game_server') {
                $product = StoreProduct::query()
                    ->where('id', (int) $raw['product_id'])
                    ->where('enabled', true)
                    ->first();

                if (!$product) {
                    continue;
                }

                $configuration = (array) ($raw['configuration'] ?? []);
                $item = [
                    'key' => (string) $key,
                    'type' => 'game_server',
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->game . ' game server',
                    'price' => (float) $product->price_monthly,
                    'currency' => 'DKK',
                    'configuration' => [
                        'server_name' => trim((string) ($configuration['server_name'] ?? '')),
                    ],
                    'meta' => [
                        'game' => $product->game,
                        'memory' => $product->memory,
                        'cpu' => $product->cpu,
                        'disk' => $product->disk,
                    ],
                ];
            } elseif ($raw['type'] === 'flax_vps') {
                $product = DB::table('nodexa_flax_products')
                    ->where('id', (int) $raw['product_id'])
                    ->where('enabled', true)
                    ->first();

                if (!$product) {
                    continue;
                }

                $configuration = (array) ($raw['configuration'] ?? []);
                $templateId = (int) ($configuration['os_template_id'] ?? 0);
                $template = DB::table('nodexa_flax_os_templates')
                    ->where('upstream_id', $templateId)
                    ->first();

                if (!$template) {
                    continue;
                }

                $item = [
                    'key' => (string) $key,
                    'type' => 'flax_vps',
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'description' => 'Flax VirtFusion VPS',
                    'price' => (float) $product->monthly_price,
                    'currency' => $product->currency ?: 'DKK',
                    'configuration' => [
                        'hostname' => trim((string) ($configuration['hostname'] ?? '')),
                        'os_template_id' => $templateId,
                        'os_label' => $template->label,
                    ],
                    'meta' => [
                        'vcpu' => $product->vcpu,
                        'memory_mb' => $product->memory_mb,
                        'disk_gb' => $product->disk_gb,
                        'bandwidth' => $product->bandwidth,
                    ],
                ];
            } else {
                continue;
            }

            $items[] = $item;
            $total += (float) $item['price'];
        }

        return [
            'items' => $items,
            'total' => round($total, 2),
            'currency' => 'DKK',
            'count' => count($items),
        ];
    }

    public function createCheckout(int $userId, array $cart): int
    {
        $resolved = $this->resolve($cart);
        if ($resolved['count'] < 1) {
            throw new RuntimeException('Kurven er tom eller indeholder ikke længere tilgængelige produkter.');
        }

        foreach ($resolved['items'] as $item) {
            if ($item['type'] === 'game_server' && $item['configuration']['server_name'] === '') {
                throw new RuntimeException('En game-server i kurven mangler servernavn.');
            }

            if ($item['type'] === 'flax_vps' && $item['configuration']['hostname'] === '') {
                throw new RuntimeException('En VPS i kurven mangler VPS-navn.');
            }
        }

        return DB::transaction(function () use ($userId, $resolved) {
            $number = 'NX-CART-' . now()->format('Ym') . '-' . strtoupper(Str::random(7));

            $checkoutId = DB::table('nodexa_cart_checkouts')->insertGetId([
                'user_id' => $userId,
                'number' => $number,
                'status' => 'awaiting_payment',
                'subtotal' => $resolved['total'],
                'discount' => 0,
                'total' => $resolved['total'],
                'currency' => $resolved['currency'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($resolved['items'] as $item) {
                DB::table('nodexa_cart_checkout_items')->insert([
                    'checkout_id' => $checkoutId,
                    'type' => $item['type'],
                    'product_id' => $item['product_id'],
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'unit_price' => $item['price'],
                    'currency' => $item['currency'],
                    'configuration' => json_encode($item['configuration'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $this->events->notify(
                $userId,
                'Samlet ordre oprettet',
                $number . ' er oprettet med ' . $resolved['count'] . ' vare(r) til ' . number_format($resolved['total'], 2, ',', '.') . ' DKK.',
                'billing',
                '/cart/orders/' . $checkoutId
            );

            $this->events->emit('cart.checkout.created', [
                'checkout_id' => $checkoutId,
                'number' => $number,
                'total' => $resolved['total'],
                'items' => $resolved['count'],
            ], $userId);

            return $checkoutId;
        });
    }

    public function markPaid(int $checkoutId, string $paymentMethod = 'manual', ?string $paymentReference = null): array
    {
        $checkout = DB::table('nodexa_cart_checkouts')->where('id', $checkoutId)->first();
        if (!$checkout) {
            throw new RuntimeException('Den samlede checkout blev ikke fundet.');
        }

        if (in_array($checkout->status, ['paid', 'completed'], true)) {
            return ['completed' => true, 'processed' => 0, 'failed' => 0];
        }

        DB::table('nodexa_cart_checkouts')->where('id', $checkoutId)->update([
            'status' => 'processing',
            'paid_at' => $checkout->paid_at ?: now(),
            'payment_method' => $paymentMethod,
            'payment_reference' => $paymentReference,
            'updated_at' => now(),
        ]);

        $items = DB::table('nodexa_cart_checkout_items')
            ->where('checkout_id', $checkoutId)
            ->orderBy('id')
            ->get();

        $processed = 0;
        $failed = 0;

        foreach ($items as $item) {
            if ($item->status === 'completed' && $item->reference_id) {
                continue;
            }

            $configuration = json_decode((string) $item->configuration, true) ?: [];

            try {
                DB::table('nodexa_cart_checkout_items')->where('id', $item->id)->update([
                    'status' => 'processing',
                    'updated_at' => now(),
                ]);

                if ($item->type === 'game_server') {
                    $product = StoreProduct::query()
                        ->where('id', $item->product_id)
                        ->first();

                    if (!$product) {
                        throw new RuntimeException('Game-serverproduktet findes ikke længere.');
                    }

                    $order = StoreOrder::create([
                        'user_id' => $checkout->user_id,
                        'product_id' => $product->id,
                        'status' => 'paid',
                        'amount' => $item->unit_price,
                        'subtotal' => $item->unit_price,
                        'discount' => 0,
                        'currency' => $item->currency ?: 'DKK',
                        'server_name' => trim((string) ($configuration['server_name'] ?? 'Server')),
                        'payment_method' => $paymentMethod,
                        'payment_reference' => $paymentReference ?: $checkout->number,
                        'paid_at' => now(),
                    ]);

                    DB::table('nodexa_cart_checkout_items')->where('id', $item->id)->update([
                        'status' => 'completed',
                        'reference_id' => $order->id,
                        'updated_at' => now(),
                    ]);
                } elseif ($item->type === 'flax_vps') {
                    $result = $this->flax->createOrder(
                        (int) $checkout->user_id,
                        (int) $item->product_id,
                        trim((string) ($configuration['hostname'] ?? 'VPS')),
                        (int) ($configuration['os_template_id'] ?? 0)
                    );

                    DB::table('nodexa_invoices')->where('id', $result['invoiceId'])->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                        'payment_method' => $paymentMethod,
                        'payment_reference' => $paymentReference ?: $checkout->number,
                        'cart_checkout_id' => $checkoutId,
                        'hidden_from_client' => true,
                        'updated_at' => now(),
                    ]);

                    $this->flax->activateInvoice((int) $result['invoiceId']);

                    DB::table('nodexa_cart_checkout_items')->where('id', $item->id)->update([
                        'status' => 'completed',
                        'reference_id' => $result['serviceId'],
                        'updated_at' => now(),
                    ]);
                } else {
                    throw new RuntimeException('Ukendt kurvvaretype: ' . $item->type);
                }

                $processed++;
            } catch (\Throwable $exception) {
                report($exception);
                $failed++;

                DB::table('nodexa_cart_checkout_items')->where('id', $item->id)->update([
                    'status' => 'failed',
                    'description' => trim((string) $item->description) . ' · Fejl: ' . substr($exception->getMessage(), 0, 500),
                    'updated_at' => now(),
                ]);
            }
        }

        $remaining = DB::table('nodexa_cart_checkout_items')
            ->where('checkout_id', $checkoutId)
            ->where('status', '!=', 'completed')
            ->count();

        $status = $remaining === 0 ? 'completed' : 'partial';

        DB::table('nodexa_cart_checkouts')->where('id', $checkoutId)->update([
            'status' => $status,
            'completed_at' => $remaining === 0 ? now() : null,
            'updated_at' => now(),
        ]);

        if ($remaining === 0) {
            $this->events->notify(
                $checkout->user_id,
                'Samlet ordre betalt',
                $checkout->number . ' er betalt. Alle ' . $items->count() . ' vare(r) er behandlet.',
                'service',
                '/client'
            );
        }

        $this->events->emit('cart.checkout.paid', [
            'checkout_id' => $checkoutId,
            'number' => $checkout->number,
            'processed' => $processed,
            'failed' => $failed,
            'status' => $status,
        ], $checkout->user_id);

        return [
            'completed' => $remaining === 0,
            'processed' => $processed,
            'failed' => $failed,
        ];
    }
}
