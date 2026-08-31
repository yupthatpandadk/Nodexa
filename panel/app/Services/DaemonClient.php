<?php
namespace App\Services;

use App\Models\Node;
use App\Models\SystemIssue;
use Illuminate\Http\Client\PendingRequest;
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
                ->where('type', 'agent_request_failed')
                ->where('status', 'open')
                ->get()
                ->each(fn (SystemIssue $issue) => $issue->resolveIssue());

            return $result;
        } catch (Throwable $e) {
            SystemIssue::report(
                source: 'agent',
                title: "Agent-fejl på {$node->name}",
                message: $e->getMessage(),
                severity: 'critical',
                type: 'agent_request_failed',
                nodeId: $node->id,
                serverId: $serverId,
                context: [
                    'action'=>$action,
                    'host'=>$node->fqdn,
                    'port'=>$node->daemon_port,
                    'exception'=>get_class($e),
                ]
            );
            throw $e;
        }
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

    public function power($server, string $signal): array
    {
        $node = $server->node;
        return $this->guarded($node, (string)$server->id, 'power:'.$signal, fn () => $this->client($node)->post("/api/servers/{$server->uuid}/power", ['signal'=>$signal])->throw()->json());
    }

    public function command($server, string $command): array
    {
        $node = $server->node;
        return $this->guarded($node, (string)$server->id, 'command', fn () => $this->client($node)->post("/api/servers/{$server->uuid}/command", ['command'=>$command])->throw()->json());
    }

    public function stats($server): array
    {
        $node = $server->node;
        return $this->guarded($node, (string)$server->id, 'stats', fn () => $this->client($node)->get("/api/servers/{$server->uuid}/stats")->throw()->json());
    }

    public function logs($server, int $tail = 200): string
    {
        $node = $server->node;
        return $this->guarded($node, (string)$server->id, 'logs', fn () => $this->client($node)->get("/api/servers/{$server->uuid}/logs", ['tail'=>$tail])->throw()->body());
    }

    public function files($server, string $path = '/'): array
    {
        $node = $server->node;
        return $this->guarded($node, (string)$server->id, 'files', fn () => $this->client($node)->get("/api/servers/{$server->uuid}/files", ['path'=>$path])->throw()->json());
    }

    public function readFile($server, string $path): string
    {
        $node = $server->node;
        return $this->guarded($node, (string)$server->id, 'read_file', fn () => $this->client($node)->get("/api/servers/{$server->uuid}/files/content", ['path'=>$path])->throw()->body());
    }

    public function writeFile($server, string $path, string $content): array
    {
        $node = $server->node;
        return $this->guarded($node, (string)$server->id, 'write_file', fn () => $this->client($node)->withBody($content, 'text/plain')->put("/api/servers/{$server->uuid}/files/content?path=".urlencode($path))->throw()->json());
    }

    public function mkdir($server, string $path): array
    {
        $node = $server->node;
        return $this->guarded($node, (string)$server->id, 'mkdir', fn () => $this->client($node)->post("/api/servers/{$server->uuid}/files/directory", ['path'=>$path])->throw()->json());
    }

    public function deleteFile($server, string $path): array
    {
        $node = $server->node;
        return $this->guarded($node, (string)$server->id, 'delete_file', fn () => $this->client($node)->delete("/api/servers/{$server->uuid}/files?path=".urlencode($path))->throw()->json());
    }

    public function backup($server, string $name): array
    {
        $node = $server->node;
        return $this->guarded($node, (string)$server->id, 'backup', fn () => $this->client($node)->post("/api/servers/{$server->uuid}/backups", ['name'=>$name])->throw()->json());
    }
}
