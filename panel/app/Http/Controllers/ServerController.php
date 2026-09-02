<?php
namespace App\Http\Controllers;

use App\Models\Server;
use App\Services\DaemonClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ServerController extends Controller
{
    private function permissions(Request $request, Server $server): array
    {
        $user = $request->user();
        if ((bool) $user->is_admin || $server->owner_id === $user->id) return ['*'];
        $entry = $server->subusers()->where('user_id', $user->id)->first();
        return $entry?->permissions ?? [];
    }

    private function authorizeServer(Request $request, Server $server, ?string $permission = null): array
    {
        $permissions = $this->permissions($request, $server);
        abort_if(empty($permissions), 403);
        if ($permission !== null && !in_array('*', $permissions, true)) {
            abort_unless(in_array($permission, $permissions, true), 403, 'You do not have permission to perform this action.');
        }
        return $permissions;
    }

    private function normalizeEnvironment(mixed $environment): array
    {
        if (!is_array($environment)) return [];
        $normalized = [];
        foreach ($environment as $key => $value) {
            if (is_string($key) && !ctype_digit($key)) {
                $key = trim($key);
                if ($key !== '' && (is_scalar($value) || $value === null)) $normalized[$key] = (string) ($value ?? '');
                continue;
            }
            if (is_string($value) && str_contains($value, '=')) {
                [$envKey, $envValue] = explode('=', $value, 2);
                $envKey = trim($envKey);
                if ($envKey !== '') $normalized[$envKey] = $envValue;
            }
        }
        return $normalized;
    }

    private function applyTemplateDefaults(array $data): array
    {
        $template = $data['template_slug'] ?? 'custom';
        $environment = $this->normalizeEnvironment($data['environment'] ?? []);

        if ($template === 'minecraft' || $template === 'minecraft-java') {
            $data['template_slug'] = 'minecraft-java';
            $data['docker_image'] = $data['docker_image'] ?: 'ghcr.io/parkervcp/yolks:java_21';
            $data['startup'] = $data['startup'] ?: 'java -Xms128M -XX:MaxRAMPercentage=95.0 -jar server.jar nogui';
            $environment += [
                'MINECRAFT_VERSION' => '1.21.8',
                'SERVER_PORT' => '25565',
            ];
        } elseif ($template === 'fivem') {
            $data['template_slug'] = 'fivem';
        } else {
            $data['template_slug'] = 'custom';
        }

        $data['environment'] = $environment;
        return $data;
    }

    private function reinstallWithConnectionRetry(DaemonClient $daemon, Server $server): array
    {
        $last = null;
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                return $daemon->reinstall($server);
            } catch (ConnectionException $e) {
                $last = $e;
                if ($attempt < 3) usleep(400000 * $attempt);
            }
        }
        throw $last ?? new \RuntimeException('Nodexa Agent kunne ikke kontaktes under geninstallationen.');
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Server::with('node')->orderByDesc('created_at');
        if (!$user->is_admin) {
            $query->where(function ($q) use ($user) {
                $q->where('owner_id', $user->id)->orWhereHas('subusers', fn ($sq) => $sq->where('user_id', $user->id));
            });
        }
        $servers = $query->limit(250)->get();
        $servers->each(function (Server $server) use ($request) { $server->setAttribute('access_permissions', $this->permissions($request, $server)); });
        return response()->json($servers->values()->all());
    }

    public function show(Request $request, Server $server)
    {
        $permissions = $this->authorizeServer($request, $server);
        $server->load('node')->setAttribute('access_permissions', $permissions);
        return $server;
    }

    public function store(Request $request, DaemonClient $daemon)
    {
        abort_unless((bool) $request->user()->is_admin, 403, 'Only administrators can create servers.');
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'node_id' => 'required|exists:nodes,id',
            'template_slug' => 'nullable|string|in:custom,minecraft,minecraft-java,fivem',
            'docker_image' => 'required|string|max:512',
            'startup' => 'required|string|max:10000',
            'memory_mb' => 'required|integer|min:128',
            'disk_mb' => 'required|integer|min:512',
            'cpu_limit' => 'required|integer|min:0|max:1000',
            'environment' => 'nullable|array',
            'owner_id' => 'required|integer|exists:users,id',
        ]);
        $ownerId = (int) $data['owner_id'];
        unset($data['owner_id']);
        $data = $this->applyTemplateDefaults($data);

        $server = DB::transaction(function () use ($data, $ownerId) {
            $last = Server::query()->lockForUpdate()->max('server_number') ?? 0;
            $number = $last + 1;
            return Server::create($data + [
                'uuid' => (string) Str::uuid(),
                'server_number' => $number,
                'identifier' => 's'.$number,
                'owner_id' => $ownerId,
                'status' => 'installing',
            ]);
        });

        return $this->provision($server, $daemon, true);
    }

    public function retryInstall(Request $request, Server $server, DaemonClient $daemon)
    {
        abort_unless((bool) $request->user()->is_admin, 403, 'Only administrators can retry server installation.');
        abort_unless(in_array((string) $server->status, ['install_failed', 'installing'], true), 409, 'Only failed or interrupted installations can be retried.');
        $server->environment = $this->normalizeEnvironment($server->environment ?? []);
        $server->status = 'installing';
        $server->save();
        return $this->provision($server, $daemon, false);
    }

    public function reinstall(Request $request, Server $server, DaemonClient $daemon)
    {
        $user = $request->user();
        abort_unless((bool) $user->is_admin || $server->owner_id === $user->id, 403, 'Only the server owner or an administrator can reinstall this server.');
        abort_if(($server->template_slug ?? 'custom') === 'custom', 409, 'Denne server bruger ikke en administreret template og kan derfor ikke geninstalleres automatisk.');

        $server->status = 'installing';
        $server->save();

        try {
            $result = $this->reinstallWithConnectionRetry($daemon, $server->load('node'));
            $server->update(['status' => 'offline']);
            return response()->json([
                'message' => 'Serveren er geninstalleret. Template/Egg-filerne er gendannet, mens brugerdata er bevaret.',
                'server' => $server->fresh()->load('node'),
                'agent' => $result,
            ]);
        } catch (Throwable $e) {
            $server->update(['status' => 'install_failed']);
            return response()->json([
                'message' => 'Geninstallationen fejlede.',
                'error' => $e->getMessage(),
                'server' => $server->fresh()->load('node'),
            ], 502);
        }
    }

    private function provision(Server $server, DaemonClient $daemon, bool $created): mixed
    {
        try {
            $daemon->createServer($server->load('node'));
            $server->update(['status' => 'offline']);
            return response()->json($server->fresh()->load('node'), $created ? 201 : 200);
        } catch (Throwable $e) {
            $server->update(['status' => 'install_failed']);
            return response()->json([
                'message' => 'Serveren blev oprettet i Nodexa, men installationen på noden fejlede.',
                'error' => $e->getMessage(),
                'server' => $server->fresh()->load('node'),
                'retry_endpoint' => '/api/servers/'.$server->id.'/retry-install',
            ], 502);
        }
    }

    public function power(Request $request, Server $server, DaemonClient $daemon)
    {
        $data = $request->validate(['signal' => 'required|in:start,stop,restart,kill']);
        $permission = match ($data['signal']) { 'start' => 'power.start', 'restart' => 'power.restart', default => 'power.stop' };
        $this->authorizeServer($request, $server, $permission);
        return $daemon->power($server->load('node'), $data['signal']);
    }

    public function command(Request $request, Server $server, DaemonClient $daemon)
    {
        $this->authorizeServer($request, $server, 'console.command');
        $data = $request->validate(['command' => 'required|string|max:4096']);
        return $daemon->command($server->load('node'), $data['command']);
    }
}
