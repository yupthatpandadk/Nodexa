<?php
namespace App\Services;

use App\Models\Node;
use App\Models\SystemIssue;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

final class DaemonClient
{
    private function client(Node $node): PendingRequest
    {
        return Http::baseUrl(sprintf('%s://%s:%d', $node->scheme, $node->fqdn, $node->daemon_port))
            ->withToken($node->token)->acceptJson()->timeout(20)->retry(2, 200);
    }

    private function guarded(Node $node, ?string $serverId, string $action, callable $callback): mixed
    {
        try {
            $result = $callback();

            SystemIssue::where('source', 'agent')
                ->where('node_id', $node->id)
                ->where('server_id', $serverId)
                ->where('status', 'open')
                ->whereIn('type', [
                    'node_connection_failed',
                    'agent_authentication_failed',
                    'agent_endpoint_missing',
                    'agent_internal_error',
                    'agent_request_failed',
                    'agent_error',
                ])
                ->get()
                ->each(fn (SystemIssue $issue) => $issue->resolveIssue());

            return $result;
        } catch (Throwable $e) {
            [$type, $severity, $title, $message, $extra] = $this->diagnose($node, $action, $e);

            try {
                SystemIssue::report(
                    source: 'agent',
                    title: $title,
                    message: $message,
                    severity: $severity,
                    type: $type,
                    nodeId: $node->id,
                    serverId: $serverId,
                    context: array_merge([
                        'action' => $action,
                        'node' => $node->name,
                        'host' => $node->fqdn,
                        'port' => $node->daemon_port,
                        'scheme' => $node->scheme,
                        'exception' => get_class($e),
                    ], $extra)
                );
            } catch (Throwable) {
                // Diagnostic logging must never hide the original error.
            }

            throw $e;
        }
    }

    private function diagnose(Node $node, string $action, Throwable $e): array
    {
        if ($e instanceof ConnectionException) {
            $raw = strtolower($e->getMessage());
            if (str_contains($raw, 'timed out') || str_contains($raw, 'timeout')) {
                return ['node_timeout', 'critical', "Timeout til {$node->name}", 'Nodexa Agent svarede ikke inden timeout. Noden kan være overbelastet, offline eller blokeret af firewall.', []];
            }
            if (str_contains($raw, 'connection refused')) {
                return ['node_connection_refused', 'critical', "Forbindelse afvist af {$node->name}", 'Serveren kan nås, men Nodexa Agent lytter ikke på den konfigurerede port. Kontroller Agent-service og daemon-port.', []];
            }
            if (str_contains($raw, 'could not resolve') || str_contains($raw, 'name or service not known')) {
                return ['node_dns_failed', 'critical', "DNS-fejl på {$node->name}", 'Node-hostnavnet kunne ikke slås op. Kontroller FQDN/DNS-indstillingerne.', []];
            }
            if (str_contains($raw, 'ssl') || str_contains($raw, 'certificate')) {
                return ['node_tls_failed', 'critical', "SSL/TLS-fejl på {$node->name}", 'TLS-forbindelsen til Nodexa Agent kunne ikke valideres. Kontroller certifikat, hostname og HTTPS-konfiguration.', []];
            }
            return ['node_connection_failed', 'critical', "Kan ikke forbinde til {$node->name}", 'Panelet kunne ikke oprette forbindelse til Nodexa Agent: '.$e->getMessage(), []];
        }

        if ($e instanceof RequestException) {
            $response = $e->response;
            $status = $response?->status();
            $body = trim((string) $response?->body());
            $extra = ['http_status'=>$status, 'response'=>mb_substr($body, 0, 4000)];

            if ($status === 401 || $status === 403) {
                return ['agent_authentication_failed', 'critical', "Agent-token afvist på {$node->name}", 'Panelet kan nå Agenten, men autentificeringen blev afvist. Kontroller eller roter node-tokenet.', $extra];
            }
            if ($status === 404) {
                return ['agent_endpoint_missing', 'error', "Agent endpoint mangler på {$node->name}", "Agenten returnerede HTTP 404 under {$action}. Panel og Agent kan være på forskellige versioner.", $extra];
            }
            if ($status === 409) {
                return ['agent_conflict', 'warning', "Konflikt på {$node->name}", "Agenten returnerede HTTP 409 under {$action}. Handlingen konflikter med serverens nuværende tilstand.", $extra];
            }
            if ($status === 422) {
                return ['agent_validation_failed', 'error', "Ugyldig Agent-request på {$node->name}", "Agenten afviste data under {$action} med HTTP 422".($body !== '' ? ': '.mb_substr($body, 0, 1000) : '.'), $extra];
            }
            if ($status !== null && $status >= 500) {
                return ['agent_internal_error', 'critical', "Intern Agent-fejl på {$node->name}", "Agenten returnerede HTTP {$status} under {$action}".($body !== '' ? ': '.mb_substr($body, 0, 1000) : '.'), $extra];
            }
            if ($status !== null) {
                return ['agent_request_failed', 'error', "Agent-request fejlede på {$node->name}", "HTTP {$status} under {$action}".($body !== '' ? ': '.mb_substr($body, 0, 1000) : '.'), $extra];
            }
        }

        return ['agent_error', 'error', "Ukendt Agent-fejl på {$node->name}", $e->getMessage(), []];
    }

    public function createServer($server): array
    {
        $node = $server->node;
        return $this->guarded($node, (string) $server->id, 'create_server', fn () =>
            $this->client($node)->post('/api/servers', [
                'id' => $server->uuid,
                'name' => $server->name,
                'image' => $server->docker_image,
                'startup' => $server->startup,
                'memory_mb' => $server->memory_mb,
                'disk_mb' => $server->disk_mb,
                'cpu_limit' => $server->cpu_limit,
                'environment' => $server->environment ?? [],
            ])->throw()->json()
        );
    }

    public function power($server, string $signal): array { $node=$server->node; return $this->guarded($node,(string)$server->id,'power:'.$signal,fn()=>$this->client($node)->post("/api/servers/{$server->uuid}/power",['signal'=>$signal])->throw()->json()); }
    public function command($server, string $command): array { $node=$server->node; return $this->guarded($node,(string)$server->id,'command',fn()=>$this->client($node)->post("/api/servers/{$server->uuid}/command",['command'=>$command])->throw()->json()); }
    public function stats($server): array { $node=$server->node; return $this->guarded($node,(string)$server->id,'stats',fn()=>$this->client($node)->get("/api/servers/{$server->uuid}/stats")->throw()->json()); }
    public function logs($server, int $tail = 200): string { $node=$server->node; return $this->guarded($node,(string)$server->id,'logs',fn()=>$this->client($node)->get("/api/servers/{$server->uuid}/logs",['tail'=>$tail])->throw()->body()); }
    public function files($server, string $path = '/'): array { $node=$server->node; return $this->guarded($node,(string)$server->id,'files',fn()=>$this->client($node)->get("/api/servers/{$server->uuid}/files",['path'=>$path])->throw()->json()); }
    public function readFile($server, string $path): string { $node=$server->node; return $this->guarded($node,(string)$server->id,'read_file',fn()=>$this->client($node)->get("/api/servers/{$server->uuid}/files/content",['path'=>$path])->throw()->body()); }
    public function writeFile($server, string $path, string $content): array { $node=$server->node; return $this->guarded($node,(string)$server->id,'write_file',fn()=>$this->client($node)->withBody($content,'text/plain')->put("/api/servers/{$server->uuid}/files/content?path=".urlencode($path))->throw()->json()); }
    public function mkdir($server, string $path): array { $node=$server->node; return $this->guarded($node,(string)$server->id,'mkdir',fn()=>$this->client($node)->post("/api/servers/{$server->uuid}/files/directory",['path'=>$path])->throw()->json()); }
    public function deleteFile($server, string $path): array { $node=$server->node; return $this->guarded($node,(string)$server->id,'delete_file',fn()=>$this->client($node)->delete("/api/servers/{$server->uuid}/files?path=".urlencode($path))->throw()->json()); }
    public function backup($server, string $name): array { $node=$server->node; return $this->guarded($node,(string)$server->id,'backup',fn()=>$this->client($node)->post("/api/servers/{$server->uuid}/backups",['name'=>$name])->throw()->json()); }
}
