<?php
namespace App\Services;

use App\Models\Node;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

final class DaemonClient
{
    private function client(Node $node): PendingRequest
    {
        return Http::baseUrl(sprintf('%s://%s:%d', $node->scheme, $node->fqdn, $node->daemon_port))
            ->withToken($node->token)->acceptJson()->timeout(20)->retry(2, 200);
    }

    public function createServer($server): array
    {
        return $this->client($server->node)->post('/api/servers', [
            'id' => $server->uuid,
            'name' => $server->name,
            'image' => $server->docker_image,
            'startup' => $server->startup,
            'memory_mb' => $server->memory_mb,
            'disk_mb' => $server->disk_mb,
            'cpu_limit' => $server->cpu_limit,
            'environment' => $server->environment ?? [],
        ])->throw()->json();
    }

    public function power($server, string $signal): array { return $this->client($server->node)->post("/api/servers/{$server->uuid}/power", ['signal' => $signal])->throw()->json(); }
    public function command($server, string $command): array { return $this->client($server->node)->post("/api/servers/{$server->uuid}/command", ['command' => $command])->throw()->json(); }
    public function stats($server): array { return $this->client($server->node)->get("/api/servers/{$server->uuid}/stats")->throw()->json(); }
    public function logs($server, int $tail = 200): string { return $this->client($server->node)->get("/api/servers/{$server->uuid}/logs", ['tail' => $tail])->throw()->body(); }
    public function files($server, string $path = '/'): array { return $this->client($server->node)->get("/api/servers/{$server->uuid}/files", ['path' => $path])->throw()->json(); }
    public function readFile($server, string $path): string { return $this->client($server->node)->get("/api/servers/{$server->uuid}/files/content", ['path' => $path])->throw()->body(); }
    public function writeFile($server, string $path, string $content): array { return $this->client($server->node)->withBody($content, 'text/plain')->put("/api/servers/{$server->uuid}/files/content?path=" . urlencode($path))->throw()->json(); }
    public function mkdir($server, string $path): array { return $this->client($server->node)->post("/api/servers/{$server->uuid}/files/directory", ['path' => $path])->throw()->json(); }
    public function deleteFile($server, string $path): array { return $this->client($server->node)->delete("/api/servers/{$server->uuid}/files?path=" . urlencode($path))->throw()->json(); }
    public function backup($server, string $name): array { return $this->client($server->node)->post("/api/servers/{$server->uuid}/backups", ['name' => $name])->throw()->json(); }
}
