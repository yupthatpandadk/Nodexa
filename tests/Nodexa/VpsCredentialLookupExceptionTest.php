<?php

namespace Pterodactyl\Tests\Nodexa;

use PHPUnit\Framework\TestCase;
use Pterodactyl\Services\Nodexa\VpsCredentialLookupException;
use RuntimeException;

class VpsCredentialLookupExceptionTest extends TestCase
{
    public function test_access_denial_is_classified_without_exposing_provider_details(): void
    {
        $exception = VpsCredentialLookupException::fromProvider(
            new RuntimeException('Flax API fejl: HTTP 403 developer access denied: token=secret')
        );

        $this->assertSame('access_denied', $exception->reason);
        $this->assertStringContainsString('Nodexa', $exception->getMessage());
        $this->assertStringNotContainsString('Flax', $exception->getMessage());
        $this->assertStringNotContainsString('secret', $exception->getMessage());
    }

    public function test_missing_endpoint_is_classified(): void
    {
        $exception = VpsCredentialLookupException::fromProvider(
            new RuntimeException('Flax API returnerede ugyldigt JSON (HTTP 404)')
        );
        $this->assertSame('endpoint_unavailable', $exception->reason);
    }

    public function test_invalid_json_is_classified(): void
    {
        $exception = VpsCredentialLookupException::fromProvider(
            new RuntimeException('Flax API returnerede ugyldigt JSON (HTTP 200)')
        );
        $this->assertSame('invalid_response', $exception->reason);
    }

    public function test_network_errors_are_classified(): void
    {
        $exception = VpsCredentialLookupException::fromProvider(
            new RuntimeException('cURL error 28: Operation timed out')
        );
        $this->assertSame('network_unavailable', $exception->reason);
    }

    public function test_unknown_failure_uses_generic_message(): void
    {
        $exception = VpsCredentialLookupException::fromProvider(
            new RuntimeException('provider sent opaque confidential error')
        );
        $this->assertSame('provider_rejected', $exception->reason);
        $this->assertStringNotContainsString('opaque', $exception->getMessage());
    }
}
