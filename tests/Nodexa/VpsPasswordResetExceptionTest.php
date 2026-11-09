<?php

namespace Pterodactyl\Tests\Nodexa;

use PHPUnit\Framework\TestCase;
use Pterodactyl\Services\Nodexa\VpsPasswordResetException;
use RuntimeException;

class VpsPasswordResetExceptionTest extends TestCase
{
    public function test_denied_provider_reset_is_safe_to_display(): void
    {
        $failure = VpsPasswordResetException::fromProvider(
            new RuntimeException('Flax API fejl: 403 developer access denied, key=SECRET')
        );

        $this->assertSame('access_denied', $failure->reason);
        $this->assertFalse($failure->isAmbiguous());
        $this->assertStringContainsString('Nodexa', $failure->getMessage());
        $this->assertStringNotContainsString('Flax', $failure->getMessage());
        $this->assertStringNotContainsString('SECRET', $failure->getMessage());
    }

    public function test_network_timeout_has_unknown_state_and_must_not_be_retried(): void
    {
        $failure = VpsPasswordResetException::fromProvider(
            new RuntimeException('cURL error 28: Operation timed out')
        );

        $this->assertSame('network_unavailable', $failure->reason);
        $this->assertTrue($failure->isAmbiguous());
        $this->assertStringContainsString('Status er ukendt', $failure->getMessage());
    }

    public function test_invalid_provider_response_is_ambiguous(): void
    {
        $failure = VpsPasswordResetException::fromProvider(
            new RuntimeException('Flax API returnerede ugyldigt JSON (HTTP 200)')
        );

        $this->assertSame('invalid_response', $failure->reason);
        $this->assertTrue($failure->isAmbiguous());
    }

    public function test_password_read_preflight_prevents_resets_when_lookup_fails(): void
    {
        $failure = new VpsPasswordResetException('lookup_unavailable');
        $this->assertStringContainsString('ikke startet', $failure->getMessage());
        $this->assertFalse($failure->isAmbiguous());
    }
}
