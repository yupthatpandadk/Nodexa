<?php

namespace Pterodactyl\Services\Nodexa;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pterodactyl\Models\StoreOrder;
use Pterodactyl\Models\StoreProduct;
use Pterodactyl\Models\StoreCoupon;
use Pterodactyl\Services\Store\ProvisionStoreOrderService;
use RuntimeException;

class NodexaCartService
{
    public function __construct(
        private FlaxResellerService $flax,
        private NodexaEventService $events,
        private ProvisionStoreOrderService $provision,
        private GlobalStoreSaleService $sale,
    ) {
    }

    public function resolve(array $cart, ?string $couponCode = null): array
    {
        $items = [];
        $subtotalCents = 0;

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

            $originalCents = $this->moneyToCents($item['price']);
            $saleDiscountCents = $this->sale->discountCents($originalCents);
            $salePriceCents = max(0, $originalCents - $saleDiscountCents);

            $item['original_price'] = $originalCents / 100;
            $item['sale_discount'] = $saleDiscountCents / 100;
            $item['sale_price'] = $salePriceCents / 100;

            $items[] = $item;
            $subtotalCents += $originalCents;
        }

        $sale = $this->sale->active();
        $saleDiscountCents = array_sum(array_map(
            fn (array $item) => $this->moneyToCents($item['sale_discount'] ?? 0),
            $items
        ));
        $afterSaleCents = max(0, $subtotalCents - $saleDiscountCents);

        // Coupon codes are intentionally calculated after the global sale.
        $coupon = $this->couponForAmount($couponCode, $afterSaleCents);
        $discountCents = (int) ($coupon['discount_cents'] ?? 0);
        $totalCents = max(0, $afterSaleCents - $discountCents);

        return [
            'items' => $items,
            'subtotal' => $subtotalCents / 100,
            'sale_discount' => $saleDiscountCents / 100,
            'after_sale_subtotal' => $afterSaleCents / 100,
            'discount' => $discountCents / 100,
            'total' => $totalCents / 100,
            'subtotal_cents' => $subtotalCents,
            'sale_discount_cents' => $saleDiscountCents,
            'after_sale_subtotal_cents' => $afterSaleCents,
            'discount_cents' => $discountCents,
            'total_cents' => $totalCents,
            'currency' => 'DKK',
            'count' => count($items),
            'coupon' => $coupon,
            'global_sale' => $sale ? [
                'percent' => (float) $sale->percent,
                'duration_months' => (int) $sale->duration_months,
                'label' => (string) $sale->label,
            ] : null,
        ];
    }

    public function validateCoupon(?string $couponCode, int $subtotalCents): ?array
    {
        return $this->couponForAmount($couponCode, $subtotalCents, true);
    }


    public function createCheckout(int $userId, array $cart, ?string $couponCode = null): int
    {
        $resolved = $this->resolve($cart, $couponCode);

        if (trim((string) $couponCode) !== '' && !$resolved['coupon']) {
            throw new RuntimeException('Rabatkoden er ikke længere gyldig. Fjern den eller anvend en ny kode før betaling.');
        }

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
            $number = now()->format('Ym') . '-' . strtoupper(Str::random(7));

            $checkoutId = DB::table('nodexa_cart_checkouts')->insertGetId([
                'user_id' => $userId,
                'number' => $number,
                'status' => 'awaiting_payment',
                'coupon_id' => $resolved['coupon']['id'] ?? null,
                'coupon_code' => $resolved['coupon']['code'] ?? null,
                'coupon_type' => $resolved['coupon']['type'] ?? null,
                'coupon_value' => $resolved['coupon']['value'] ?? null,
                'coupon_duration_months' => $resolved['coupon']['duration_months'] ?? null,
                'global_sale_percent' => $resolved['global_sale']['percent'] ?? 0,
                'global_sale_discount' => $resolved['sale_discount'],
                'global_sale_duration_months' => $resolved['global_sale']['duration_months'] ?? 0,
                'subtotal' => $resolved['subtotal'],
                'discount' => $resolved['discount'],
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
        $this->reserveCoupon($checkoutId);

        $claim = DB::transaction(function () use ($checkoutId, $paymentMethod, $paymentReference) {
            $checkout = DB::table('nodexa_cart_checkouts')
                ->where('id', $checkoutId)
                ->lockForUpdate()
                ->first();

            if (!$checkout) {
                throw new RuntimeException('Den samlede checkout blev ikke fundet.');
            }

            if (in_array($checkout->status, ['cancelled', 'wallet_refunded', 'refunded'], true)
                || (bool) ($checkout->hidden_from_client ?? false)
            ) {
                throw new RuntimeException('Denne ordre er annulleret eller refunderet og kan ikke markeres betalt.');
            }

            if ($checkout->paid_at && $checkout->payment_method
                && $checkout->payment_method !== $paymentMethod
            ) {
                throw new RuntimeException('Betalingsmetoden kan ikke ændres på en allerede betalt ordre.');
            }

            if (!$checkout->paid_at && $paymentMethod !== 'stripe'
                && ($checkout->stripe_session_id || $checkout->stripe_status === 'creating'
                    || $checkout->wallet_hold_state === 'held')
            ) {
                throw new RuntimeException('Der er allerede en Stripe-betaling eller Wallet-reservation i gang. Annullér denne først.');
            }

            if (in_array($checkout->status, ['paid', 'completed'], true)) {
                return ['state' => 'completed', 'checkout' => $checkout];
            }

            if (
                $checkout->status === 'processing'
                && $checkout->updated_at
                && \Carbon\Carbon::parse($checkout->updated_at)->greaterThan(now()->subMinutes(5))
            ) {
                return ['state' => 'processing', 'checkout' => $checkout];
            }

            DB::table('nodexa_cart_checkouts')->where('id', $checkoutId)->update([
                'status' => 'processing',
                'paid_at' => $checkout->paid_at ?: now(),
                'payment_method' => $paymentMethod,
                'payment_reference' => $paymentReference,
                'updated_at' => now(),
            ]);

            return ['state' => 'claimed', 'checkout' => $checkout];
        });

        if ($claim['state'] === 'completed') {
            return ['completed' => true, 'processed' => 0, 'failed' => 0];
        }

        if ($claim['state'] === 'processing') {
            return ['completed' => false, 'processing' => true, 'processed' => 0, 'failed' => 0];
        }

        $checkout = DB::table('nodexa_cart_checkouts')->where('id', $checkoutId)->first();

        $items = DB::table('nodexa_cart_checkout_items')
            ->where('checkout_id', $checkoutId)
            ->orderBy('id')
            ->get();

        $saleAllocations = $this->allocateSaleDiscount($checkout, $items);
        $discountAllocations = $this->allocateCouponDiscount($checkout, $items, $saleAllocations);

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

                    $order = $item->reference_id
                        ? StoreOrder::query()->where('id', $item->reference_id)->where('user_id', $checkout->user_id)->first()
                        : null;

                    if (!$order) {
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

                        // Persist before provisioning so a retry resumes the
                        // same order instead of creating a duplicate server.
                        DB::table('nodexa_cart_checkout_items')->where('id', $item->id)->update([
                            'reference_id' => $order->id,
                            'updated_at' => now(),
                        ]);
                    }

                    if (!$order->server_id) {
                        $this->provision->handle($order);
                        $order->refresh();
                    }

                    if (!$order->server_id) {
                        throw new RuntimeException('Game-serverordren blev betalt, men serveren blev ikke provisioneret.');
                    }

                    DB::table('nodexa_subscriptions')->updateOrInsert(
                        ['order_id' => $order->id],
                        [
                            'user_id' => $order->user_id,
                            'server_id' => $order->server_id,
                            'status' => 'active',
                            'amount' => $order->amount,
                            'currency' => $order->currency ?: 'DKK',
                            'interval' => 'monthly',
                            'next_invoice_at' => now()->addMonth(),
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );

                    $subscription = DB::table('nodexa_subscriptions')->where('order_id', $order->id)->first();
                    if ($subscription) {
                        $this->applyRecurringSale(
                            (int) $subscription->id,
                            $checkout,
                            (int) ($saleAllocations[$item->id] ?? 0)
                        );
                        $this->applyRecurringDiscount(
                            (int) $subscription->id,
                            $checkout,
                            (int) ($discountAllocations[$item->id] ?? 0)
                        );
                    }

                    DB::table('nodexa_cart_checkout_items')->where('id', $item->id)->update([
                        'status' => 'completed',
                        'reference_id' => $order->id,
                        'updated_at' => now(),
                    ]);
                } elseif ($item->type === 'flax_vps') {
                    $serviceId = $item->reference_id ? (int) $item->reference_id : null;
                    $invoiceId = null;
                    $subscriptionId = null;

                    if ($serviceId) {
                        $existingService = DB::table('nodexa_flax_services')->where('id', $serviceId)->first();
                        if (!$existingService || (int) $existingService->user_id !== (int) $checkout->user_id) {
                            throw new RuntimeException('Den eksisterende VPS-reference er ugyldig.');
                        }

                        $invoiceId = (int) $existingService->first_invoice_id;
                        $subscriptionId = (int) $existingService->subscription_id;
                    } else {
                        $result = $this->flax->createOrder(
                            (int) $checkout->user_id,
                            (int) $item->product_id,
                            trim((string) ($configuration['hostname'] ?? 'VPS')),
                            (int) ($configuration['os_template_id'] ?? 0)
                        );

                        $serviceId = (int) $result['serviceId'];
                        $invoiceId = (int) $result['invoiceId'];
                        $subscriptionId = (int) $result['subscriptionId'];

                        // Persist the local service reference before calling the
                        // upstream API. If Flax fails afterwards, a retry resumes
                        // this service instead of creating a duplicate order.
                        DB::table('nodexa_cart_checkout_items')->where('id', $item->id)->update([
                            'reference_id' => $serviceId,
                            'updated_at' => now(),
                        ]);
                    }

                    if (!$invoiceId) {
                        throw new RuntimeException('VPS-servicen mangler sin tekniske faktura.');
                    }

                    DB::table('nodexa_invoices')->where('id', $invoiceId)->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                        'payment_method' => $paymentMethod,
                        'payment_reference' => $paymentReference ?: $checkout->number,
                        'cart_checkout_id' => $checkoutId,
                        'hidden_from_client' => true,
                        'updated_at' => now(),
                    ]);

                    $this->flax->activateInvoice($invoiceId);

                    if ($subscriptionId) {
                        $this->applyRecurringSale(
                            $subscriptionId,
                            $checkout,
                            (int) ($saleAllocations[$item->id] ?? 0)
                        );
                        $this->applyRecurringDiscount(
                            $subscriptionId,
                            $checkout,
                            (int) ($discountAllocations[$item->id] ?? 0)
                        );
                    }

                    DB::table('nodexa_cart_checkout_items')->where('id', $item->id)->update([
                        'status' => 'completed',
                        'reference_id' => $serviceId,
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
    private function allocateSaleDiscount(object $checkout, $items): array
    {
        $percent = max(0.0, min(100.0, (float) ($checkout->global_sale_percent ?? 0)));

        if ($percent <= 0 || $items->isEmpty()) {
            return [];
        }

        $allocations = [];

        foreach ($items as $item) {
            $itemCents = $this->moneyToCents((float) $item->unit_price);
            $allocations[$item->id] = min(
                $itemCents,
                max(0, (int) round($itemCents * ($percent / 100)))
            );
        }

        return $allocations;
    }

    private function allocateCouponDiscount(object $checkout, $items, array $saleAllocations): array
    {
        $discountCents = $this->moneyToCents((float) ($checkout->discount ?? 0));
        if ($discountCents <= 0 || $items->isEmpty()) {
            return [];
        }

        $bases = [];
        $baseTotal = 0;

        foreach ($items as $item) {
            $original = $this->moneyToCents((float) $item->unit_price);
            $base = max(0, $original - (int) ($saleAllocations[$item->id] ?? 0));
            $bases[$item->id] = $base;
            $baseTotal += $base;
        }

        if ($baseTotal <= 0) {
            return [];
        }

        $allocations = [];
        $remaining = $discountCents;
        $count = $items->count();

        foreach ($items->values() as $index => $item) {
            if ($index === $count - 1) {
                $allocation = $remaining;
            } else {
                $allocation = (int) round($discountCents * (($bases[$item->id] ?? 0) / $baseTotal));
                $allocation = max(0, min($remaining, $allocation));
            }

            $allocations[$item->id] = $allocation;
            $remaining -= $allocation;
        }

        return $allocations;
    }

    private function applyRecurringSale(int $subscriptionId, object $checkout, int $discountCents): void
    {
        $durationMonths = max(0, (int) ($checkout->global_sale_duration_months ?? 0));

        if ($discountCents <= 0 || $durationMonths <= 0) {
            return;
        }

        DB::table('nodexa_subscriptions')->where('id', $subscriptionId)->update([
            'sale_discount_amount' => $discountCents / 100,
            'sale_months_total' => $durationMonths,
            // Month 1 is the combined checkout that has just been paid.
            'sale_months_used' => 1,
            'updated_at' => now(),
        ]);
    }

    private function applyRecurringDiscount(int $subscriptionId, object $checkout, int $discountCents): void
    {
        $durationMonths = max(0, (int) ($checkout->coupon_duration_months ?? 0));

        if (!$checkout->coupon_code || $discountCents <= 0 || $durationMonths <= 0) {
            return;
        }

        DB::table('nodexa_subscriptions')->where('id', $subscriptionId)->update([
            'discount_code' => $checkout->coupon_code,
            'discount_type' => $checkout->coupon_type ?? 'fixed',
            'discount_value' => $checkout->coupon_value ?? ($discountCents / 100),
            // Fixed discounts use the allocated per-service amount. Percentage
            // discounts are recalculated from that month's post-sale base.
            'discount_amount' => $discountCents / 100,
            'discount_months_total' => $durationMonths,
            // Month 1 is the combined checkout that has just been paid.
            'discount_months_used' => 1,
            'updated_at' => now(),
        ]);
    }

    public function reserveCoupon(int $checkoutId): void
    {
        DB::transaction(function () use ($checkoutId) {
            $checkout = DB::table('nodexa_cart_checkouts')->where('id', $checkoutId)->lockForUpdate()->first();
            if (!$checkout || $checkout->status === 'cancelled' || (bool) ($checkout->hidden_from_client ?? false) || !$checkout->coupon_id || $checkout->coupon_reserved_at) {
                return;
            }

            $coupon = StoreCoupon::query()->where('id', $checkout->coupon_id)->lockForUpdate()->first();
            if (!$coupon || !$coupon->enabled) {
                throw new RuntimeException('Rabatkoden er ikke længere aktiv.');
            }

            if ($coupon->expires_at && $coupon->expires_at->isPast()) {
                throw new RuntimeException('Rabatkoden er udløbet.');
            }

            if ($coupon->max_uses !== null && (int) $coupon->uses >= (int) $coupon->max_uses) {
                throw new RuntimeException('Rabatkoden har nået sit maksimale antal anvendelser.');
            }

            $coupon->uses = (int) $coupon->uses + 1;
            $coupon->save();

            DB::table('nodexa_cart_checkouts')->where('id', $checkoutId)->update([
                'coupon_reserved_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function releaseCoupon(int $checkoutId): void
    {
        DB::transaction(function () use ($checkoutId) {
            $checkout = DB::table('nodexa_cart_checkouts')->where('id', $checkoutId)->lockForUpdate()->first();
            if (!$checkout || !$checkout->coupon_id || !$checkout->coupon_reserved_at || $checkout->paid_at) {
                return;
            }

            $coupon = StoreCoupon::query()->where('id', $checkout->coupon_id)->lockForUpdate()->first();
            if ($coupon && (int) $coupon->uses > 0) {
                $coupon->uses = max(0, (int) $coupon->uses - 1);
                $coupon->save();
            }

            DB::table('nodexa_cart_checkouts')->where('id', $checkoutId)->update([
                'coupon_reserved_at' => null,
                'updated_at' => now(),
            ]);
        });
    }

    private function couponForAmount(?string $couponCode, int $subtotalCents, bool $strict = false): ?array
    {
        $code = strtoupper(trim((string) $couponCode));
        if ($code === '') {
            return null;
        }

        $coupon = StoreCoupon::query()->whereRaw('UPPER(code) = ?', [$code])->first();

        $invalid = !$coupon
            || !$coupon->enabled
            || ($coupon->expires_at && $coupon->expires_at->isPast())
            || ($coupon->max_uses !== null && (int) $coupon->uses >= (int) $coupon->max_uses);

        if ($invalid) {
            if ($strict) {
                throw new RuntimeException('Rabatkoden er ugyldig, udløbet eller opbrugt.');
            }

            return null;
        }

        if ($coupon->type === 'percent') {
            $percent = max(0.0, min(100.0, (float) $coupon->value));
            $discountCents = (int) round($subtotalCents * ($percent / 100));
            $label = rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.') . '%';
        } else {
            $discountCents = $this->moneyToCents((float) $coupon->value);
            $label = number_format($discountCents / 100, 2, ',', '.') . ' DKK';
        }

        $discountCents = min($subtotalCents, max(0, $discountCents));

        return [
            'id' => $coupon->id,
            'code' => $coupon->code,
            'type' => $coupon->type,
            'value' => (float) $coupon->value,
            'label' => $label,
            'duration_months' => max(1, (int) ($coupon->duration_months ?? 1)),
            'discount_cents' => $discountCents,
            'discount' => $discountCents / 100,
        ];
    }

    private function moneyToCents(float|int|string $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

}
