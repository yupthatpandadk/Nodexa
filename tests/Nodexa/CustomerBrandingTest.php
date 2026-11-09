<?php

namespace Pterodactyl\Tests\Nodexa;

use PHPUnit\Framework\TestCase;
use Pterodactyl\Services\Nodexa\CustomerBranding;
use RuntimeException;

class CustomerBrandingTest extends TestCase
{
    public function test_provider_names_are_removed_from_customer_labels(): void
    {
        $this->assertSame('Nodexa VPS Hosting', CustomerBranding::label('Flax VirtFusion VPS Hosting'));
        $this->assertSame('Nodexa VPS', CustomerBranding::label('FlaxHosting VPS'));
        $this->assertSame('Nodexa Support', CustomerBranding::label('Nodexa Support'));
        $this->assertSame('Nodexa VPS', CustomerBranding::label(null, 'Nodexa VPS'));
    }

    public function test_provider_urls_are_not_exposed(): void
    {
        $this->assertSame(
            'Kontakt Nodexa support',
            CustomerBranding::label('Kontakt https://flaxhosting.com/knowledgebase/10/help')
        );
    }

    public function test_upstream_diagnostics_are_hidden_from_customers(): void
    {
        $this->assertSame(
            CustomerBranding::serviceError(),
            CustomerBranding::errorMessage(new RuntimeException('Flax API error: invalid reseller key'))
        );
        $this->assertSame(
            CustomerBranding::serviceError(),
            CustomerBranding::errorMessage(new RuntimeException('SQLSTATE: nodexa_flax_services'))
        );
    }

    public function test_unrelated_useful_customer_errors_remain_readable(): void
    {
        $this->assertSame(
            'Din Wallet har ikke saldo nok.',
            CustomerBranding::errorMessage(new RuntimeException('Din Wallet har ikke saldo nok.'))
        );
    }
}
