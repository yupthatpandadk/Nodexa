<?php

namespace Pterodactyl\Services\Nodexa;

use Pterodactyl\Models\Server;

/**
 * Resolve a txAdmin link from a FiveM server's actual Pterodactyl allocations
 * and egg variables. Does not proxy txAdmin, copy credentials, expose API
 * tokens, or claim the process is online.
 */
final class TxAdminIntegrationService
{
    public function forServer(Server $server): array
    {
        $values = [];
        foreach ($server->variables as $variable) {
            $key = strtoupper(trim((string) $variable->env_variable));
            if (in_array($key, ['TXADMIN_ENABLED', 'TXHOST_TXA_PORT', 'TXADMINPORT', 'TXADMIN_PORT', 'TXHOST_TXA_URL'], true)) {
                $values[$key] = trim((string) ($variable->server_value ?? $variable->default_value ?? ''));
            }
        }

        $allocations = $server->allocations->map(static fn ($allocation) => [
            'port' => (int) $allocation->port,
            'ip' => (string) $allocation->ip,
            'alias' => (string) ($allocation->ip_alias ?? ''),
        ])->all();

        return self::resolve(
            $values,
            $allocations,
            (string) ($server->node->fqdn ?? ''),
            FiveMServerType::matches($server->egg->nest?->name),
            (int) $server->allocation->port
        );
    }

    /**
     * Pure resolution for testing: never rely on a guessed port, an unowned
     * allocation, or a user-entered URL to reach an arbitrary host.
     *
     * @param array<string, string> $variables
     * @param array<int, array{port:int,ip:string,alias?:string}> $allocations
     */
    public static function resolve(array $variables, array $allocations, string $nodeFqdn, bool $isFiveM, ?int $gamePort = null): array
    {
        if (!$isFiveM) {
            return [
                'supported' => false, 'enabled' => null,
                'port' => null, 'allocated' => false,
                'url' => null, 'state' => 'not_fivem',
            ];
        }

        // A missing switch means "not known", not "disabled". Different
        // community eggs configure the txAdmin monitor in different ways.
        $rawToggle = trim($variables['TXADMIN_ENABLED'] ?? '');
        $enabled = $rawToggle === ''
            ? null
            : in_array(strtolower($rawToggle), ['1', 'true', 'yes', 'on'], true);

        $portRaw = trim($variables['TXHOST_TXA_PORT']
            ?? $variables['TXADMINPORT']
            ?? $variables['TXADMIN_PORT']
            ?? '');
        $port = $portRaw === '' ? 40120 : (ctype_digit($portRaw) ? (int) $portRaw : 0);
        if ($port < 1024 || $port > 65535) {
            return [
                'supported' => true, 'enabled' => $enabled,
                'port' => null, 'allocated' => false,
                'url' => null, 'state' => 'invalid_port',
            ];
        }

        if ($gamePort !== null && $port === $gamePort) {
            return [
                'supported' => true, 'enabled' => $enabled,
                'port' => $port, 'allocated' => false,
                'url' => null, 'state' => 'port_conflict',
            ];
        }

        // Only expose a clickable endpoint if Pterodactyl has explicitly
        // assigned this exact TCP port to this server.
        $matched = null;
        foreach ($allocations as $allocation) {
            if ((int) ($allocation['port'] ?? 0) === $port) {
                $matched = $allocation;
                break;
            }
        }
        if ($matched === null) {
            return [
                'supported' => true, 'enabled' => $enabled,
                'port' => $port, 'allocated' => false,
                'url' => null, 'state' => $enabled === false ? 'disabled' : 'missing_allocation',
            ];
        }

        $host = self::safeHost((string) ($matched['alias'] ?? ''))
            ?? self::safeHost((string) ($matched['ip'] ?? ''))
            ?? self::safeHost($nodeFqdn);

        // 0.0.0.0 and :: are valid bind addresses, but not public endpoints.
        if (in_array($host, ['0.0.0.0', '::', '::0'], true)) {
            $host = self::safeHost($nodeFqdn);
        }
        if ($host === null) {
            return [
                'supported' => true, 'enabled' => $enabled,
                'port' => $port, 'allocated' => true,
                'url' => null, 'state' => 'invalid_host',
            ];
        }

        $urlHost = str_contains($host, ':') ? '[' . $host . ']' : $host;
        $url = 'http://' . $urlHost . ':' . $port . '/';

        // txAdmin can have an HTTPS reverse proxy on the same approved host.
        // Arbitrary TXHOST_TXA_URL hosts are deliberately ignored to avoid
        // routing a customer to an unverified login/credential capture page.
        $preferred = trim($variables['TXHOST_TXA_URL'] ?? '');
        if ($preferred !== '') {
            $parts = parse_url($preferred);
            if (is_array($parts)
                && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
                && !isset($parts['user']) && !isset($parts['pass'])
                && !isset($parts['query']) && !isset($parts['fragment'])
                && in_array(strtolower((string) ($parts['host'] ?? '')), array_map('strtolower', array_filter([
                    $host,
                    self::safeHost((string) ($matched['alias'] ?? '')),
                    self::safeHost((string) ($matched['ip'] ?? '')),
                    self::safeHost($nodeFqdn),
                ])), true)
            ) {
                $url = 'https://' . $urlHost
                    . (isset($parts['port']) ? ':' . (int) $parts['port'] : '')
                    . '/';
            }
        }

        return [
            'supported' => true,
            'enabled' => $enabled,
            'port' => $port,
            'allocated' => true,
            'url' => $enabled === false ? null : $url,
            // "ready" means link is configured, NOT that txAdmin is running.
            'state' => $enabled === false ? 'disabled' : 'ready_to_open',
        ];
    }

    private static function safeHost(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || str_contains($value, '/') || str_contains($value, '\\')
            || str_contains($value, '@') || preg_match('/\s/', $value)) {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_IP)) {
            return $value;
        }

        if (filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) && strlen($value) <= 253) {
            return $value;
        }

        return null;
    }
}
