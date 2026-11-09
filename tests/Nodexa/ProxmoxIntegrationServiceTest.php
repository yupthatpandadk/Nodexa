<?php

namespace Pterodactyl\Tests\Nodexa;

use PHPUnit\Framework\TestCase;
use Pterodactyl\Services\Nodexa\ProxmoxIntegrationService;
use RuntimeException;

class ProxmoxIntegrationServiceTest extends TestCase
{
    private ProxmoxIntegrationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ProxmoxIntegrationService();
    }

    public function test_normalizes_a_standard_proxmox_url(): void
    {
        $this->assertSame(
            'https://pve.example.dk:8006',
            $this->service->normalizeEndpoint('https://pve.example.dk:8006/')
        );

        $this->assertSame(
            'https://pve.example.dk:8006',
            $this->service->normalizeEndpoint('https://pve.example.dk')
        );

        $this->assertSame(
            'https://pve.example.dk',
            $this->service->normalizeEndpoint('https://pve.example.dk:443')
        );
    }

    public function test_allows_a_private_lan_proxmox_host(): void
    {
        $this->assertSame(
            'https://192.168.1.20:8006',
            $this->service->normalizeEndpoint('https://192.168.1.20:8006')
        );
    }

    /**
     * @dataProvider invalidEndpoints
     */
    public function test_rejects_unsafe_or_unexpected_urls(string $url): void
    {
        $this->expectException(RuntimeException::class);
        $this->service->normalizeEndpoint($url);
    }

    public static function invalidEndpoints(): array
    {
        return [
            ['http://pve.example.dk:8006'],
            ['https://127.0.0.1:8006'],
            ['https://169.254.169.254:8006'],
            ['https://localhost:8006'],
            ['https://pve.example.dk:1234'],
            ['https://pve.example.dk/redirect'],
            ['https://alice:secret@pve.example.dk:8006'],
            ['https://pve.example.dk:8006?token=secret'],
            ['https://pve.example.dk:8006#frag'],
        ];
    }

    public function test_accepts_and_rejects_token_identifiers(): void
    {
        $this->assertSame(
            'nodexa@pve!monitor',
            $this->service->validateTokenId(' nodexa@pve!monitor ')
        );

        $this->expectException(RuntimeException::class);
        $this->service->validateTokenId('root@pam');
    }
}
