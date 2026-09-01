<?php
namespace App\Http\Controllers;

use App\Models\Server;
use App\Services\DaemonClient;
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

    /**
     * Convert all supported UI/legacy environment shapes to the canonical
     * associative KEY => VALUE map expected by Nodexa Agent.
     */
    private function normalizeEnvironment(mixed $environment): array
    {
        if (!is_array($environment)) return [];

        $normalized = [];
        foreach ($environment as $key => $value) {
            if (is_string($key) && !ctype_digit($key)) {
                $key = trim($key);
                if ($key !== '' && (is_scalar($value) || $value === null)) {
                    $normalized[$key] = (string) ($value ?? '');
                }
                continue;
            }

            // Older clients could submit ["KEY=value", ...]. Keep those
            // records compatible instead of forwarding a JSON array to Go.
            if (is_string($value) && str_contains($value, '=')) {
                [$envKey, $envValue] = explode('=', $value, 2);
                $envKey = trim($envKey);
                if ($envKey !== '') $normalized[$envKey] = $envValue;
            }
        }

        return $normalized;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Server::with('node')->orderByDesc('created_at');
        if (!$user->is_admin) {
            $query->where(function ($q) use ($user) {
                $q->where('owner_id', $user->id)
                    ->orWhereHas('subusers', fn ($sq) => $sq->where('user_id', $user->id));
            });
        }

        $servers = $query->limit(250)->get();
        $servers->each(function (Server $server) use ($request) {
            $server->setAttribute('access_permissions', $this->permissions($request, $server));
        });

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
        $data['environment'] = $this->normalizeEnvironment($data['environment'] ?? []);

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

        // Repair records created by older Panel builds before retrying them.
        $server->environment = $this->normalizeEnvironment($server->environment ?? []);
        $server->status = 'installing';
        $server->save();

        return $this->provision($server, $daemon, false);
    }

    private function provision(Server $server, DaemonClient $daemon, bool $created): mixed
    {
        try {
            $daemon->createServer($server->load('node'));
            $server->update(['status' => 'offline']);

            return response()->json($server->fresh()->load('node'), $created ? 201 : 200);
        } catch (Throwable $e) {
            // Keep the database record so the administrator can see which
            // provisioning attempt failed and repair/retry it later. DaemonClient
            // separately records the detailed Node error under Admin → Fejl.
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
        $permission = match ($data['signal']) {
            'start' => 'power.start',
            'restart' => 'power.restart',
            default => 'power.stop',
        };
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
