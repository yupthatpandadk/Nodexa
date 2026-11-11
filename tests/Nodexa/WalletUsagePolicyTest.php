<?php

namespace Pterodactyl\Tests\Nodexa;

use PHPUnit\Framework\TestCase;
use Pterodactyl\Services\Nodexa\WalletUsagePolicy;

class WalletUsagePolicyTest extends TestCase
{
    public function test_game_server_is_eligible_even_when_vps_is_disabled(): void
    {
        $this->assertSame(8000, WalletUsagePolicy::eligibleAfterDiscount(
            [['type' => 'game_server', 'base' => 10000]], 2000, 8000, false
        ));
    }

    public function test_vps_only_order_cannot_spend_wallet_when_disabled(): void
    {
        $this->assertSame(0, WalletUsagePolicy::eligibleAfterDiscount(
            [['type' => 'flax_vps', 'base' => 10000]], 0, 10000, false
        ));
    }

    public function test_vps_only_order_is_eligible_when_enabled(): void
    {
        $this->assertSame(10000, WalletUsagePolicy::eligibleAfterDiscount(
            [['type' => 'flax_vps', 'base' => 10000]], 0, 10000, true
        ));
    }

    public function test_mixed_cart_applies_proportional_discount_only_to_eligible_items(): void
    {
        $items = [
            ['type' => 'game_server', 'base' => 10000],
            ['type' => 'flax_vps', 'base' => 20000],
        ];
        // 30 DKK coupon: 10 DKK belongs to gameserver, 20 DKK to VPS.
        $this->assertSame(9000, WalletUsagePolicy::eligibleAfterDiscount($items, 3000, 27000, false));
        $this->assertSame(27000, WalletUsagePolicy::eligibleAfterDiscount($items, 3000, 27000, true));
    }

    public function test_unknown_product_types_never_receive_wallet_credit(): void
    {
        $this->assertSame(5000, WalletUsagePolicy::eligibleAfterDiscount([
            ['type' => 'game_server', 'base' => 5000],
            ['type' => 'unknown', 'base' => 5000],
        ], 0, 10000, true));
    }

    public function test_mismatched_checkout_total_cannot_increase_eligible_credit(): void
    {
        $this->assertSame(3000, WalletUsagePolicy::eligibleAfterDiscount([
            ['type' => 'game_server', 'base' => 5000],
            ['type' => 'flax_vps', 'base' => 5000],
        ], 0, 8000, false));
    }

    public function test_wallet_first_preserves_stripe_minimum_and_eligibility(): void
    {
        $this->assertSame(9000, WalletUsagePolicy::walletFirstCents(30000, 20000, 9000));
        $this->assertSame(10000, WalletUsagePolicy::walletFirstCents(10000, 10000, 10000));
        $this->assertSame(9750, WalletUsagePolicy::walletFirstCents(10000, 10000, 9900));
        $this->assertSame(0, WalletUsagePolicy::walletFirstCents(200, 10000, 100));
        $this->assertSame(0, WalletUsagePolicy::walletFirstCents(10000, 10000, 0));
    }
}
