<?php

namespace Pterodactyl\Services\Nodexa;

/**
 * White-label presentation for the customer storefront.
 *
 * Keep internal Flax integrations, identifiers and admin diagnostics intact.
 * Only customer-visible names and messages are transformed.
 */
final class CustomerBranding
{
    public static function label(?string $value, string $fallback = ''): string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return $fallback;
        }

        // Never expose upstream provider website addresses or brand names in
        // a customer-facing product label, description or stored notification.
        $text = preg_replace('~https?://(?:www\.)?flaxhosting\.com(?:/[^\s]*)?~i', 'Nodexa support', $text);
        $text = preg_replace('~\bflax\s*hosting\b|\bflax\b|\bvirtfusion\b~iu', 'Nodexa', $text);

        return trim((string) $text);
    }

    public static function serviceError(): string
    {
        // Upstream responses can contain provider branding, API routes,
        // internal IPs and sensitive diagnostics. Log these internally but
        // never echo them directly to a customer.
        return 'VPS-handlingen kunne ikke gennemføres. Prøv igen, eller kontakt Nodexa Support.';
    }
}
