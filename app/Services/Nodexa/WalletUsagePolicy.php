<?php

namespace Pterodactyl\Services\Nodexa;

use Illuminate\Support\Facades\DB;
use Pterodactyl\Contracts\Repository\SettingsRepositoryInterface;

/**
 * Determines which parts of a combined hosting checkout can be paid using
 * Wallet credit. Policy is enforced for new debits and Stripe reservations,
 * not only in checkout templates.
 */
class WalletUsagePolicy
{
    private const VPS_SETTING = 'nodexa::wallet.allow_vps';

    public function __construct(private SettingsRepositoryInterface $settings)
    {
    }

    /**
     * Preserve existing behavior until an administrator changes the setting.
     */
    public function allowsVps(): bool
    {
        return filter_var($this->settings->get(self::VPS_SETTING, '1'), FILTER_VALIDATE_BOOLEAN);
    }

    public function setAllowsVps(bool $enabled): void
    {
        $this->settings->set(self::VPS_SETTING, $enabled ? '1' : '0');
    }

    /**
     * Eligible credit after global sale and proportional coupon allocation.
     * Uses the same coupon rounding and last-line remainder as NodexaCartService.
     *
     * @param array<string, mixed> $summary
     */
    public function eligibleCentsForSummary(array $summary): int
    {
        $total = max(0, (int) ($summary['total_cents'] ?? 0));
        $lines = [];

        foreach ((array) ($summary['items'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $lines[] = [
                'type' => (string) ($item['type'] ?? ''),
                'base' => max(0, (int) round(((float) ($item['sale_price'] ?? 0)) * 100)),
            ];
        }

        return self::eligibleAfterDiscount($lines, (int) ($summary['discount_cents'] ?? 0), $total, $this->allowsVps());
    }

    public function eligibleCentsForCheckout(object $checkout): int
    {
        $total = max(0, (int) round(((float) $checkout->total) * 100));
        $percent = max(0.0, min(100.0, (float) ($checkout->global_sale_percent ?? 0)));
        $rows = DB::table('nodexa_cart_checkout_items')
            ->where('checkout_id', (int) $checkout->id)
            ->orderBy('id')
            ->get(['type', 'unit_price']);

        $lines = [];
        foreach ($rows as $row) {
            $price = max(0, (int) round((float) $row->unit_price * 100));
            $sale = min($price, max(0, (int) round($price * $percent / 100)));
            $lines[] = ['type' => (string) $row->type, 'base' => $price - $sale];
        }

        return self::eligibleAfterDiscount(
            $lines,
            (int) round(((float) ($checkout->discount ?? 0)) * 100),
            $total,
            $this->allowsVps()
        );
    }

    /**
     * Pure calculation kept public for regression tests, no DB required.
     *
     * @param array<int, array{type: string, base: int}> $lines
     */
    public static function eligibleAfterDiscount(array $lines, int $discountCents, int $totalCents, bool $allowsVps): int
    {
        $totalCents = max(0, $totalCents);
        if ($totalCents === 0 || count($lines) === 0) {
            return 0;
        }

        $totalBase = array_sum(array_map(static fn (array $line): int => max(0, $line['base']), $lines));
        if ($totalBase === 0) {
            return 0;
        }

        $discount = min(max(0, $discountCents), $totalBase);
        $remainingDiscount = $discount;
        $eligible = 0;
        $ineligible = 0;
        $last = count($lines) - 1;

        foreach (array_values($lines) as $index => $line) {
            $base = max(0, $line['base']);
            if ($index === $last) {
                $allocatedDiscount = $remainingDiscount;
            } else {
                $allocatedDiscount = min($remainingDiscount, max(0, (int) round($discount * ($base / $totalBase))));
            }
            $remainingDiscount -= $allocatedDiscount;
            $net = max(0, $base - $allocatedDiscount);

            if ($line['type'] === 'game_server' || ($allowsVps && $line['type'] === 'flax_vps')) {
                $eligible += $net;
            } else {
                $ineligible += $net;
            }
        }

        // Protect against any mismatch between stored totals and item totals:
        // Wallet must never cover a portion belonging to an ineligible line.
        return min($totalCents, $eligible, max(0, $totalCents - $ineligible));
    }

    /**
     * Match Stripe's 2.50 DKK minimum while never exceeding the eligible
     * portion of the checkout.
     */
    public static function walletFirstCents(int $total, int $balance, int $eligible): int
    {
        $total = max(0, $total);
        $usable = min($total, max(0, $balance), max(0, $eligible));

        return $usable === $total ? $total : min($usable, max(0, $total - 250));
    }
}
