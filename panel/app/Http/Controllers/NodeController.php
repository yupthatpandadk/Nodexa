<?php
namespace App\Http\Controllers;

use App\Models\DatabaseHost;
use App\Models\Node;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NodeController extends Controller
{
    private function admin(Request $request): void
    {
        abort_unless((bool) $request->user()?->is_admin, 403, 'Administrator permission required.');
    }

    public function index(Request $request)
    {
        $this->admin($request);
        return Node::withCount('servers')->paginate(50);
    }

    public function show(Request $request, Node $node)
    {
        $this->admin($request);
        $node->load([
            'servers' => fn ($query) => $query->select(['id','node_id','name','identifier','status','memory_mb','disk_mb','cpu_limit'])->orderBy('server_number'),
            'allocations' => fn ($query) => $query->with('server:id,name,identifier')->orderBy('ip')->orderBy('port'),
        ]);

        $memoryAllocated = (int) $node->servers->sum('memory_mb');
        $diskAllocated = (int) $node->servers->sum('disk_mb');
        $allocationTotal = $node->allocations->count();
        $allocationAssigned = $node->allocations->whereNotNull('server_id')->count();

        return response()->json([
            'node' => $node,
            'capacity' => [
                'memory_mb' => (int) $node->memory_mb,
                'memory_allocated_mb' => $memoryAllocated,
                'memory_free_mb' => max(0, (int) $node->memory_mb - $memoryAllocated),
                'disk_mb' => (int) $node->disk_mb,
                'disk_allocated_mb' => $diskAllocated,
                'disk_free_mb' => max(0, (int) $node->disk_mb - $diskAllocated),
                'servers' => $node->servers->count(),
                'allocations_total' => $allocationTotal,
                'allocations_assigned' => $allocationAssigned,
                'allocations_free' => max(0, $allocationTotal - $allocationAssigned),
            ],
            'servers' => $node->servers->values(),
            'allocations' => $node->allocations->values(),
        ]);
    }

    public function store(Request $request)
    {
        $this->admin($request);
        $data = $request->validate([
            'name'=>'required|string|max:120',
            'fqdn'=>'required|string|max:255',
            'scheme'=>'required|in:http,https',
            'daemon_port'=>'required|integer|min:1|max:65535',
            'sftp_port'=>'required|integer|min:1|max:65535',
            'memory_mb'=>'required|integer|min:128',
            'disk_mb'=>'required|integer|min:1024',
            'location'=>'nullable|string|max:120',
            'setup'=>'nullable|array',
            'setup.configure_ufw'=>'nullable|boolean',
            'setup.configure_database_host'=>'nullable|boolean',
            'setup.database_external'=>'nullable|boolean',
            'setup.database_allow_3306'=>'nullable|boolean',
            'setup.database_panel_source'=>'nullable|string|max:255',
            'setup.database_username'=>'nullable|string|max:64|regex:/^[A-Za-z0-9_]+$/',
            'setup.database_password'=>'nullable|string|min:12|max:128',
            'setup.ssl_email'=>'nullable|email|max:255',
        ]);

        $setupInput = $data['setup'] ?? [];
        unset($data['setup']);

        if ($data['scheme'] === 'https') {
            // Nginx/Let's Encrypt terminates HTTPS on the standard TLS port.
            $data['daemon_port'] = 443;
        }

        $panelHost = (string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: '');
        $setup = [
            'configure_ufw' => (bool) ($setupInput['configure_ufw'] ?? true),
            'configure_database_host' => (bool) ($setupInput['configure_database_host'] ?? false),
            'database_external' => (bool) ($setupInput['database_external'] ?? true),
            'database_allow_3306' => (bool) ($setupInput['database_allow_3306'] ?? true),
            'database_panel_source' => trim((string) ($setupInput['database_panel_source'] ?? $panelHost)),
            'database_username' => trim((string) ($setupInput['database_username'] ?? 'nodexa_dbhost')) ?: 'nodexa_dbhost',
            'ssl_email' => trim((string) ($setupInput['ssl_email'] ?? '')),
        ];

        if ($data['scheme'] === 'https' && $setup['ssl_email'] === '') {
            return response()->json(['message' => 'A Let\'s Encrypt email is required when HTTPS is enabled.'], 422);
        }

        $token = Str::random(64);
        $data['token'] = $token;
        $data['setup_options'] = $setup;
        $databasePassword = null;
        $databaseHost = null;

        [$node, $databaseHost, $databasePassword] = DB::transaction(function () use ($data, $setup, $setupInput, $token) {
            $node = Node::create($data);
            $databaseHost = null;
            $databasePassword = null;

            if ($setup['configure_database_host']) {
                $databasePassword = trim((string) ($setupInput['database_password'] ?? ''));
                if ($databasePassword === '') {
                    $databasePassword = Str::password(32, true, true, false, false);
                }

                $databaseHost = DatabaseHost::create([
                    'name' => $node->name.' Database',
                    'host' => $node->fqdn,
                    'port' => 3306,
                    'username' => $setup['database_username'],
                    'password' => $databasePassword,
                    // Game servers may run on different Nodes. Their individual
                    // generated database accounts remain isolated per database.
                    'remote_host' => '%',
                    'node_id' => $node->id,
                    'max_databases' => null,
                    'ssl' => false,
                    'enabled' => true,
                    'last_status' => 'unknown',
                ]);
            }

            return [$node, $databaseHost, $databasePassword];
        });

        return response()->json([
            'node'=>$node,
            'token'=>$token,
            'database_host'=>$databaseHost,
            'database_host_password'=>$databasePassword,
            'configuration'=>$this->configurationArray($node->fresh(), $token),
        ], 201);
    }

    public function configuration(Request $request, Node $node)
    {
        $this->admin($request);
        return $this->configurationArray($node, $node->token);
    }

    public function rotateToken(Request $request, Node $node)
    {
        $this->admin($request);
        $token = Str::random(64);
        $node->update(['token'=>$token]);
        return ['token'=>$token,'configuration'=>$this->configurationArray($node, $token)];
    }

    private function env(string $key, string|int $value): string
    {
        return $key.'='.escapeshellarg((string) $value);
    }

    private function configurationArray(Node $node, string $token): array
    {
        $https = $node->scheme === 'https';
        $publicPort = $https ? 443 : (int) $node->daemon_port;
        $internalPort = $https ? 8080 : $publicPort;
        $panelUrl = rtrim((string) config('app.url'), '/');
        $setup = is_array($node->setup_options) ? $node->setup_options : [];

        $configureUfw = (bool) ($setup['configure_ufw'] ?? true);
        $configureDatabase = (bool) ($setup['configure_database_host'] ?? false);
        $databaseExternal = (bool) ($setup['database_external'] ?? true);
        $databaseAllow3306 = (bool) ($setup['database_allow_3306'] ?? true);
        $databasePanelSource = trim((string) ($setup['database_panel_source'] ?? (parse_url($panelUrl, PHP_URL_HOST) ?: '')));
        $databaseUsername = trim((string) ($setup['database_username'] ?? 'nodexa_dbhost')) ?: 'nodexa_dbhost';
        $sslEmail = trim((string) ($setup['ssl_email'] ?? ''));

        $parts = [
            $this->env('NODEXA_AGENT_TOKEN', $token),
            $this->env('NODEXA_PANEL_URL', $panelUrl),
            $this->env('NODEXA_AGENT_FQDN', $node->fqdn),
            $this->env('NODEXA_AGENT_HTTPS', $https ? 1 : 0),
            $this->env('NODEXA_AGENT_PUBLIC_PORT', $publicPort),
            $this->env('NODEXA_AGENT_INTERNAL_PORT', $internalPort),
            $this->env('NODEXA_CONFIGURE_UFW', $configureUfw ? 1 : 0),
            $this->env('NODEXA_CONFIGURE_DB_HOST', $configureDatabase ? 1 : 0),
        ];

        $databasePassword = null;
        if ($configureDatabase) {
            $dbHost = $node->databaseHosts()->orderBy('id')->first();
            if ($dbHost) {
                try { $databasePassword = $dbHost->plainPassword(); } catch (\Throwable) { $databasePassword = null; }
            }
            $parts[] = $this->env('NODEXA_DB_EXTERNAL', $databaseExternal ? 1 : 0);
            $parts[] = $this->env('NODEXA_DB_ALLOW_3306', $databaseAllow3306 ? 1 : 0);
            $parts[] = $this->env('NODEXA_DB_PANEL_SOURCE', $databasePanelSource);
            $parts[] = $this->env('NODEXA_DB_HOST_USER', $databaseUsername);
            if ($databasePassword !== null && $databasePassword !== '') {
                $parts[] = $this->env('NODEXA_DB_HOST_PASSWORD', $databasePassword);
            }
        }

        if ($https && $sslEmail !== '') {
            $parts[] = $this->env('NODEXA_SSL_EMAIL', $sslEmail);
        }

        return [
            'node_id'=>$node->id,
            'panel_url'=>$panelUrl,
            'token'=>$token,
            'scheme'=>$node->scheme,
            'fqdn'=>$node->fqdn,
            'listen'=>'127.0.0.1:'.$internalPort,
            'public_port'=>$publicPort,
            'sftp_port'=>$node->sftp_port,
            'data'=>'/var/lib/nodexa',
            'backups'=>'/var/lib/nodexa/backups',
            'setup'=>$setup,
            'database_host_password'=>$databasePassword,
            'install_command'=>implode(' ', $parts).' bash <(curl -fsSL https://raw.githubusercontent.com/yupthatpandadk/Nodexa/main/install.sh) node',
        ];
    }
}
