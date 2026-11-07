<?php

namespace Pterodactyl\Services\Nodexa;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GlobalStoreSaleService
{
    public function settings(): object
    {
        // A deployment may temporarily run this code before the new sales
        // settings migration has completed. An empty cart must still load.
        $row = Schema::hasTable('nodexa_store_sale_settings')
            ? DB::table('nodexa_store_sale_settings')->where('id', 1)->first()
            : null;

        return (object) [
            'enabled' => (bool) ($row->enabled ?? false),
            'percent' => (float) ($row->percent ?? 0),
            'duration_months' => max(1, (int) ($row->duration_months ?? 1)),
            'label' => trim((string) ($row->label ?? '')) ?: 'Globalt udsalg',
        ];
    }

    public function save(bool $enabled, float $percent, int $durationMonths, ?string $label): void
    {
        $percent = max(0, min(100, round($percent, 2)));
        $durationMonths = max(1, min(120, $durationMonths));
        $label = trim((string) $label);

        DB::table('nodexa_store_sale_settings')->updateOrInsert(
            ['id' => 1],
            [
                'enabled' => $enabled,
                'percent' => $percent,
                'duration_months' => $durationMonths,
                'label' => $label !== '' ? mb_substr($label, 0, 120) : 'Globalt udsalg',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function active(): ?object
    {
        $settings = $this->settings();

        if (!$settings->enabled || $settings->percent <= 0) {
            return null;
        }

        return $settings;
    }

    public function discountCents(int $amountCents): int
    {
        $sale = $this->active();

        if (!$sale || $amountCents <= 0) {
            return 0;
        }

        return min(
            $amountCents,
            max(0, (int) round($amountCents * ($sale->percent / 100)))
        );
    }

    public function salePrice(float|int|string $amount): float
    {
        $cents = (int) round(((float) $amount) * 100);

        return max(0, ($cents - $this->discountCents($cents)) / 100);
    }
}
