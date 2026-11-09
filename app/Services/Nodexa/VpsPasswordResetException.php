<?php

namespace Pterodactyl\Services\Nodexa;

use RuntimeException;
use Throwable;

/**
 * Sanitized password-reset failures. Upstream errors may include credentials
 * or internal API routes, so only expose a stable failure category.
 */
final class VpsPasswordResetException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct(match ($reason) {
            'access_denied' => 'Nodexa har ikke API-rettigheder til at nulstille passwordet på denne VPS. Kontakt Nodexa Support.',
            'rate_limited' => 'Password-reset er midlertidigt begrænset. Prøv ikke igen med det samme.',
            'endpoint_unavailable' => 'VPS-tjenestens reset-funktion blev ikke fundet. Kontakt Nodexa Support.',
            'network_unavailable' => 'Nodexa modtog ikke et sikkert svar på nulstillingen. Status er ukendt. Forsøg ikke igen, før support har kontrolleret den.',
            'invalid_response' => 'Reset-tjenesten returnerede et ugyldigt svar. Nulstillingens status er ukendt. Kontakt Nodexa Support, før du prøver igen.',
            default => 'Nulstillingen blev ikke bekræftet af VPS-tjenesten. Kontakt Nodexa Support, før du forsøger igen.',
        });
    }

    public static function fromProvider(Throwable $exception): self
    {
        $lookup = VpsCredentialLookupException::fromProvider($exception);

        return new self($lookup->reason);
    }

    public function isAmbiguous(): bool
    {
        return in_array($this->reason, ['network_unavailable', 'invalid_response'], true);
    }
}
