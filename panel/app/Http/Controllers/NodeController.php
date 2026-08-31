<?php
namespace App\Http\Controllers;

use App\Models\Node;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
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
        ]);
        $token = Str::random(64);
        $data['token'] = $token;
        $node = Node::create($data);
        return response()->json([
            'node'=>$node,
            'token'=>$token,
            'configuration'=>$this->configurationArray($node, $token),
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

    private function configurationArray(Node $node, string $token): array
    {
        $https = $node->scheme === 'https';
        $internalPort = $https ? 8080 : (int) $node->daemon_port;
        $panelUrl = rtrim((string) config('app.url'), '/');

        $parts = [
            'NODEXA_AGENT_TOKEN='.$token,
            'NODEXA_PANEL_URL='.$panelUrl,
            'NODEXA_AGENT_FQDN='.$node->fqdn,
            'NODEXA_AGENT_HTTPS='.($https ? '1' : '0'),
            'NODEXA_AGENT_PUBLIC_PORT='.(int) $node->daemon_port,
            'NODEXA_AGENT_INTERNAL_PORT='.$internalPort,
        ];

        return [
            'node_id'=>$node->id,
            'panel_url'=>$panelUrl,
            'token'=>$token,
            'scheme'=>$node->scheme,
            'fqdn'=>$node->fqdn,
            'listen'=>'127.0.0.1:'.$internalPort,
            'public_port'=>(int) $node->daemon_port,
            'sftp_port'=>$node->sftp_port,
            'data'=>'/var/lib/nodexa',
            'backups'=>'/var/lib/nodexa/backups',
            'install_command'=>implode(' ', $parts).' bash <(curl -fsSL https://raw.githubusercontent.com/yupthatpandadk/Nodexa/main/install.sh) node',
        ];
    }
}
