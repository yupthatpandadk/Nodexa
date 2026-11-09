<?php

namespace Pterodactyl\Services\Nodexa;

use RuntimeException;
use Throwable;

/**
 * Safe, customer-displayable credential lookup errors.
 *
 * Never propagate raw API exception messages: they can contain a provider
 * hostname, bearer tokens, internal routes or returned credentials.
 */
final class VpsCredentialLookupException extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct(match ($reason) {
            'access_denied' => 'Nodexa har ikke API-adgang til at hente adgangskoden for denne VPS. Kontakt Nodexa Support.',
            'rate_limited' => 'Der er foretaget for mange password-forespørgsler. Prøv igen senere.',
            'endpoint_unavailable' => 'Password-tjenesten svarer ikke på den konfigurerede API-adresse. Kontakt Nodexa Support.',
            'network_unavailable' => 'Der er i øjeblikket ikke forbindelse til VPS-passwordtjenesten. Prøv igen senere.',
            'invalid_response' => 'Password-tjenesten returnerede et ugyldigt svar. Kontakt Nodexa Support.',
            'server_mismatch' => 'Password-svaret stemmer ikke med denne VPS. Kontakt Nodexa Support.',
            default => 'Password kunne ikke hentes fra VPS-systemet. Kontakt Nodexa Support.',
        });
    }

    public static function fromProvider(Throwable $exception): self
    {
        $error = mb_strtolower($exception->getMessage());

        if (preg_match('/\b(401|403)\b|forbidden|unauthori[sz]ed|permission|developer.?access|access.?denied|invalid.?key|api.?key|no.?access|not.?allowed|adgang.?nægtet|rettigheder/u', $error)) {
            return new self('access_denied');
        }

        if (preg_match('/\b429\b|rate.?limit|too many requests|for mange/u', $error)) {
            return new self('rate_limited');
        }

        if (preg_match('/\b(404|405)\b|route not found|unknown action|unsupported action|ukendt handling/u', $error)) {
            return new self('endpoint_unavailable');
        }

        if (preg_match('/curl error|connection|connect|timeout|timed out|resolve host|name lookup|could not resolve|kunne ikke kontakte|\b(502|503|504)\b/u', $error)) {
            return new self('network_unavailable');
        }

        if (preg_match('/ugyldigt json|invalid json|content.type|\bjson\b|password.svaret|password response/u', $error)) {
            return new self('invalid_response');
        }

        if (str_contains($error, 'knyttes sikkert til denne vps')) {
            return new self('server_mismatch');
        }

        return new self('provider_rejected');
    }
}
