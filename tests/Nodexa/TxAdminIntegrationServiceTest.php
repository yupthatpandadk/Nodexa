<?php

namespace Pterodactyl\Tests\Nodexa;

use PHPUnit\Framework\TestCase;
use Pterodactyl\Services\Nodexa\TxAdminIntegrationService;

class TxAdminIntegrationServiceTest extends TestCase
{
    private array $allocations = [
        ['port' => 30120, 'ip' => '203.0.113.5', 'alias' => 'node.example.test'],
        ['port' => 40120, 'ip' => '203.0.113.5', 'alias' => 'node.example.test'],
    ];

    public function test_real_fivem_assignment_provides_openable_txadmin_link(): void
    {
        $result = TxAdminIntegrationService::resolve(
            ['TXADMIN_ENABLED' => '1', 'TXHOST_TXA_PORT' => '40120'],
            $this->allocations,
            'node.example.test',
            true
        );

        $this->assertSame('ready_to_open', $result['state']);
        $this->assertTrue($result['allocated']);
        $this->assertSame('http://node.example.test:40120/', $result['url']);
        $this->assertTrue($result['enabled']);
    }

    public function test_fivem_without_allocated_panel_port_cannot_guess_a_url(): void
    {
        $result = TxAdminIntegrationService::resolve(
            ['TXADMIN_ENABLED' => '1', 'TXHOST_TXA_PORT' => '40120'],
            [['port' => 30120, 'ip' => '203.0.113.5']],
            'node.example.test',
            true
        );

        $this->assertSame('missing_allocation', $result['state']);
        $this->assertNull($result['url']);
    }

    public function test_disabled_txadmin_does_not_offer_login(): void
    {
        $result = TxAdminIntegrationService::resolve(
            ['TXADMIN_ENABLED' => '0', 'TXHOST_TXA_PORT' => '40120'],
            $this->allocations,
            'node.example.test',
            true
        );
        $this->assertSame('disabled', $result['state']);
        $this->assertNull($result['url']);
    }

    public function test_other_nests_cannot_open_txadmin(): void
    {
        $result = TxAdminIntegrationService::resolve(
            ['TXADMIN_ENABLED' => '1', 'TXHOST_TXA_PORT' => '40120'],
            $this->allocations,
            'node.example.test',
            false
        );
        $this->assertSame('not_fivem', $result['state']);
        $this->assertFalse($result['supported']);
        $this->assertNull($result['url']);
    }

    public function test_unknown_egg_toggle_does_not_claim_enabled_or_disabled(): void
    {
        $result = TxAdminIntegrationService::resolve([], $this->allocations, 'node.example.test', true);
        $this->assertNull($result['enabled']);
        $this->assertSame('ready_to_open', $result['state']);
    }

    public function test_invalid_port_never_becomes_link(): void
    {
        foreach (['0', '99999', '40120/path', '30120?x=1'] as $port) {
            $result = TxAdminIntegrationService::resolve(
                ['TXHOST_TXA_PORT' => $port], $this->allocations, 'node.example.test', true
            );
            $this->assertSame('invalid_port', $result['state']);
            $this->assertNull($result['url']);
        }
    }

    public function test_safe_https_proxy_url_on_verified_host(): void
    {
        $result = TxAdminIntegrationService::resolve(
            ['TXADMIN_ENABLED' => '1', 'TXHOST_TXA_PORT' => '40120', 'TXHOST_TXA_URL' => 'https://node.example.test/'],
            $this->allocations, 'node.example.test', true
        );
        $this->assertSame('https://node.example.test/', $result['url']);
    }

    public function test_external_unverified_custom_login_host_is_ignored(): void
    {
        $result = TxAdminIntegrationService::resolve(
            ['TXADMIN_ENABLED' => '1', 'TXHOST_TXA_PORT' => '40120', 'TXHOST_TXA_URL' => 'https://not-my-server.example/login'],
            $this->allocations, 'node.example.test', true
        );
        $this->assertSame('http://node.example.test:40120/', $result['url']);
    }

    public function test_invalid_hostname_cannot_be_injected_into_admin_link(): void
    {
        $result = TxAdminIntegrationService::resolve(
            ['TXHOST_TXA_PORT' => '40120'],
            [['port' => 40120, 'ip' => 'bad.example/test', 'alias' => 'http://attacker.test']],
            '',
            true
        );
        $this->assertSame('invalid_host', $result['state']);
        $this->assertNull($result['url']);
    }

    public function test_bind_allocation_address_is_replaced_by_node_fqdn(): void
    {
        $result = TxAdminIntegrationService::resolve(
            ['TXHOST_TXA_PORT' => '40120'],
            [['port' => 40120, 'ip' => '0.0.0.0', 'alias' => '']],
            'node.example.test',
            true
        );
        $this->assertSame('http://node.example.test:40120/', $result['url']);
    }
}
