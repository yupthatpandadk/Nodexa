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
    public function __construct(
        public readonly string $reason,
        public readonly ?int $httpStatus = null,
        public readonly ?string $responseType = null,
        public readonly ?string $transport = null,
    ) {
        parent::__construct(match ($reason) {
            'access_denied' => 'Nodexa har ikke API-rettigheder til at nulstille passwordet på denne VPS. Kontakt Nodexa Support.',
            'lookup_unavailable' => 'Nodexa kan ikke verificere password-hentning fra VPS-systemet. Nulstillingen blev ikke startet, så du undgår at miste adgang. Kontakt Nodexa Support.',
            'rate_limited' => 'Password-reset er midlertidigt begrænset. Prøv ikke igen med det samme.',
            'endpoint_unavailable' => 'VPS-tjenestens reset-funktion blev ikke fundet. Kontakt Nodexa Support.',
            'network_unavailable' => 'Nodexa modtog ikke et sikkert svar på nulstillingen. Status er ukendt. Forsøg ikke igen, før support har kontrolleret den.',
            'invalid_response' => 'Reset-tjenesten returnerede et ugyldigt svar. Nulstillingens status er ukendt. Kontakt Nodexa Support, før du prøver igen.',
            default => 'Nulstillingen blev ikke bekræftet af VPS-tjenesten. Kontakt Nodexa Support, før du forsøger igen.',
        });
    }

    public static function fromProvider(Throwable $exception, ?string $transport = null): self
    {
        $lookup = VpsCredentialLookupException::fromProvider($exception);

        // Extract only metadata to aid operator diagnosis. Never store,
        // log or show raw response bodies or provider URLs / API keys.
        $message = $exception->getMessage();
        preg_match('/\\bHTTP\\s+(\\d{3})\\b/i', $message, $status);
        preg_match('~Content-Type:\\s*([a-z0-9.+-]+/[a-z0-9.+-]+)~i', $message, $type);
        $mime = strtolower($type[1] ?? '');
        $responseType = $mime === ''
            ? null
            : (str_contains($mime, 'json') ? 'json' : (str_contains($mime, 'html') ? 'html' : 'other'));

        return new self(
            $lookup->reason,
            isset($status[1]) ? (int) $status[1] : null,
            $responseType,
            in_array($transport, ['root', 'legacy'], true) ? $transport : null,
        );
    }

    public function isAmbiguous(): bool
    {
        return in_array($this->reason, ['network_unavailable', 'invalid_response'], true);
    }
}
