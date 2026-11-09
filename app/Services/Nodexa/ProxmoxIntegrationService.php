<?php

namespace Pterodactyl\Services\Nodexa;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class ProxmoxIntegrationService
{
    /**
     * Admin-provided destinations only. Private RFC1918 addresses are
     * permitted because Proxmox is commonly operated on a private LAN.
     * Localhost, link-local and metadata endpoints must never be accepted.
     */
    public function normalizeEndpoint(string $input): string
    {
        $url = trim($input);
        $parts = parse_url($url);

        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['query']) || isset($parts['fragment'])
            || !in_array($parts['path'] ?? '/', ['', '/'], true)
        ) {
            throw new RuntimeException('Angiv en HTTPS-adresse uden sti eller login, fx https://pve.example.dk:8006.');
        }

        $host = strtolower((string) $parts['host']);
        $unbracketedHost = trim($host, '[]');
        if (strlen($unbracketedHost) > 253
            || preg_match('/[\s\/\\\\@]/', $unbracketedHost)
            || in_array($unbracketedHost, ['localhost', 'metadata.google.internal', 'metadata'], true)
            || str_ends_with($unbracketedHost, '.localhost')
            || str_ends_with($unbracketedHost, '.localdomain')
        ) {
            throw new RuntimeException('Denne Proxmox-serveradresse kan ikke bruges.');
        }

        if (filter_var($unbracketedHost, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $first = (int) explode('.', $unbracketedHost)[0];
            if ($first === 0 || $first === 127 || $first >= 224
                || str_starts_with($unbracketedHost, '169.254.')
            ) {
                throw new RuntimeException('Loopback- og link-local-adresser er ikke tilladt.');
            }
        } elseif (filter_var($unbracketedHost, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            if ($unbracketedHost === '::1' || $unbracketedHost === '::'
                || str_starts_with($unbracketedHost, 'fe80:')
                || str_starts_with($unbracketedHost, 'ff')
            ) {
                throw new RuntimeException('Loopback- og link-local-adresser er ikke tilladt.');
            }
        } elseif (!preg_match('/^[a-z0-9][a-z0-9._-]*[a-z0-9]$/i', $unbracketedHost)) {
            throw new RuntimeException('Proxmox-serverens hostnavn er ugyldigt.');
        }

        $port = (int) ($parts['port'] ?? 8006);
        if (!in_array($port, [8006, 443], true)) {
            throw new RuntimeException('Brug Proxmox HTTPS-port 8006 eller en HTTPS-reverse-proxy på port 443.');
        }

        $hostForUrl = str_contains($unbracketedHost, ':') ? '[' . $unbracketedHost . ']' : $unbracketedHost;

        return 'https://' . $hostForUrl . ($port === 443 ? '' : ':8006');
    }

    public function validateTokenId(string $tokenId): string
    {
        $tokenId = trim($tokenId);
        if (!preg_match('/^[a-zA-Z0-9._+-]+@[a-zA-Z0-9._-]+![a-zA-Z0-9._-]+$/', $tokenId)) {
            throw new RuntimeException('Token ID skal være på formen bruger@realm!tokennavn.');
        }

        return $tokenId;
    }

    public function store(array $input, ?int $id = null): int
    {
        $endpoint = $this->normalizeEndpoint((string) $input['endpoint']);
        $tokenId = $this->validateTokenId((string) $input['token_id']);
        $secret = trim((string) ($input['token_secret'] ?? ''));

        if ($id === null && $secret === '') {
            throw new RuntimeException('Indtast API-tokenets secret for at tilføje forbindelsen.');
        }

        $data = [
            'name' => trim((string) $input['name']),
            'endpoint' => $endpoint,
            'token_id' => $tokenId,
            'verify_tls' => (bool) ($input['verify_tls'] ?? true),
            'enabled' => (bool) ($input['enabled'] ?? true),
            'status' => 'untested',
            'last_error' => null,
            'updated_at' => now(),
        ];

        if ($secret !== '') {
            $data['token_secret_encrypted'] = Crypt::encryptString($secret);
        }

        if ($id !== null) {
            $exists = DB::table('nodexa_proxmox_connections')->where('id', $id)->exists();
            if (!$exists) {
                throw new RuntimeException('Proxmox-forbindelsen blev ikke fundet.');
            }
            DB::table('nodexa_proxmox_connections')->where('id', $id)->update($data);
            return $id;
        }

        $data['created_at'] = now();
        return (int) DB::table('nodexa_proxmox_connections')->insertGetId($data);
    }

    /**
     * Read-only connection test and inventory snapshot. Token is decrypted
     * solely inside this server-side request, never returned to the browser.
     */
    public function sync(int $id): array
    {
        $row = DB::table('nodexa_proxmox_connections')->where('id', $id)->first();
        if (!$row) {
            throw new RuntimeException('Proxmox-forbindelsen blev ikke fundet.');
        }
        if (!$row->enabled) {
            throw new RuntimeException('Aktivér Proxmox-forbindelsen før test.');
        }

        try {
            $secret = Crypt::decryptString($row->token_secret_encrypted);
            $nodes = $this->getData($row, $secret, '/nodes');
            $guests = $this->getData($row, $secret, '/cluster/resources', ['type' => 'vm']);

            $safeNodes = [];
            foreach (array_slice($nodes, 0, 80) as $node) {
                if (!is_array($node)) {
                    continue;
                }
                $safeNodes[] = [
                    'name' => $this->clean($node['node'] ?? '', 90),
                    'status' => $this->clean($node['status'] ?? 'unknown', 30),
                    'cpu_percent' => round(min(100, max(0, (float) ($node['cpu'] ?? 0) * 100)), 1),
                    'memory_used' => max(0, (int) ($node['mem'] ?? 0)),
                    'memory_total' => max(0, (int) ($node['maxmem'] ?? 0)),
                ];
            }

            $safeGuests = [];
            foreach (array_slice($guests, 0, 1000) as $guest) {
                if (!is_array($guest) || !in_array($guest['type'] ?? '', ['qemu', 'lxc'], true)) {
                    continue;
                }
                $safeGuests[] = [
                    'id' => (int) ($guest['vmid'] ?? 0),
                    'name' => $this->clean($guest['name'] ?? 'VM', 120),
                    'node' => $this->clean($guest['node'] ?? '', 90),
                    'type' => $guest['type'],
                    'status' => $this->clean($guest['status'] ?? 'unknown', 30),
                    'cpu_percent' => round(min(100, max(0, (float) ($guest['cpu'] ?? 0) * 100)), 1),
                    'memory_used' => max(0, (int) ($guest['mem'] ?? 0)),
                    'memory_total' => max(0, (int) ($guest['maxmem'] ?? 0)),
                ];
            }

            DB::table('nodexa_proxmox_connections')->where('id', $id)->update([
                'status' => 'connected',
                'last_error' => null,
                'last_checked_at' => now(),
                'nodes_snapshot' => json_encode($safeNodes, JSON_UNESCAPED_UNICODE),
                'guests_snapshot' => json_encode($safeGuests, JSON_UNESCAPED_UNICODE),
                'snapshot_at' => now(),
                'updated_at' => now(),
            ]);

            return ['nodes' => count($safeNodes), 'guests' => count($safeGuests)];
        } catch (Throwable $exception) {
            $message = $exception instanceof RuntimeException
                ? $exception->getMessage()
                : 'Kunne ikke kontakte Proxmox. Kontrollér HTTPS, certifikat, adresse og firewall.';

            // Never persist raw cURL exception content: it can include a
            // credential-bearing URL or other internal server details.
            if (!in_array($message, [
                'Proxmox API afviste tokenet (401).',
                'Proxmox-tokenet mangler læserettigheder (403).',
                'Proxmox API er ikke tilgængelig (404).',
                'Proxmox returnerede et ugyldigt svar.',
            ], true)) {
                $message = 'Forbindelse mislykkedes. Kontrollér adresse, TLS-certifikat, API-token og netværk.';
            }

            DB::table('nodexa_proxmox_connections')->where('id', $id)->update([
                'status' => 'error',
                'last_error' => $message,
                'last_checked_at' => now(),
                'updated_at' => now(),
            ]);
            throw new RuntimeException($message);
        }
    }

    private function getData(object $connection, string $secret, string $path, array $query = []): array
    {
        $url = rtrim($connection->endpoint, '/') . '/api2/json' . $path;

        // No redirects, cookies or browser-based credentials; enforce HTTPS.
        // TLS verification defaults ON. Opting out is restricted to root admin.
        $response = Http::withHeaders([
            'Authorization' => 'PVEAPIToken=' . $connection->token_id . '=' . $secret,
        ])->acceptJson()
            ->connectTimeout(5)
            ->timeout(12)
            ->withOptions([
                'verify' => (bool) $connection->verify_tls,
                'allow_redirects' => false,
            ])
            ->get($url, $query);

        if ($response->status() === 401) {
            throw new RuntimeException('Proxmox API afviste tokenet (401).');
        }
        if ($response->status() === 403) {
            throw new RuntimeException('Proxmox-tokenet mangler læserettigheder (403).');
        }
        if ($response->status() === 404) {
            throw new RuntimeException('Proxmox API er ikke tilgængelig (404).');
        }
        if (!$response->successful() || !is_array($response->json('data'))) {
            throw new RuntimeException('Proxmox returnerede et ugyldigt svar.');
        }

        return $response->json('data');
    }

    private function clean($value, int $max): string
    {
        return mb_substr(trim(is_scalar($value) ? (string) $value : ''), 0, $max);
    }
}
