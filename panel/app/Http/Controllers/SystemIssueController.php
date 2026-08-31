<?php
namespace App\Http\Controllers;

use App\Models\Node;
use App\Models\SystemIssue;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class SystemIssueController extends Controller
{
    private function admin(Request $request): void
    {
        abort_unless((bool) $request->user()?->is_admin, 403, 'Administrator permission required.');
    }

    public function index(Request $request)
    {
        $this->admin($request);

        $query = SystemIssue::query()->orderByRaw("status = 'open' DESC")->orderByDesc('last_seen_at');
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        if ($request->filled('source')) $query->where('source', $request->string('source'));
        if ($request->filled('severity')) $query->where('severity', $request->string('severity'));

        return $query->paginate(50);
    }

    public function scanNodes(Request $request)
    {
        $this->admin($request);
        $results = [];

        foreach (Node::query()->orderBy('name')->get() as $node) {
            $errno = 0;
            $errstr = '';
            $started = microtime(true);
            $socket = @fsockopen($node->fqdn, $node->daemon_port, $errno, $errstr, 3.0);
            $latency = (int) round((microtime(true) - $started) * 1000);

            if (is_resource($socket)) {
                fclose($socket);
                SystemIssue::where('source', 'node')
                    ->where('node_id', $node->id)
                    ->where('type', 'node_unreachable')
                    ->where('status', 'open')
                    ->get()
                    ->each(fn (SystemIssue $issue) => $issue->resolveIssue());
                $results[] = ['node_id'=>$node->id,'name'=>$node->name,'online'=>true,'latency_ms'=>$latency];
                continue;
            }

            $message = trim(($errstr ?: 'Connection failed') . ($errno ? " (errno {$errno})" : ''));
            SystemIssue::report(
                source: 'node',
                title: "Node {$node->name} kan ikke kontaktes",
                message: $message,
                severity: 'critical',
                type: 'node_unreachable',
                nodeId: $node->id,
                context: [
                    'fqdn'=>$node->fqdn,
                    'port'=>$node->daemon_port,
                    'scheme'=>$node->scheme,
                    'latency_ms'=>$latency,
                ]
            );
            $results[] = ['node_id'=>$node->id,'name'=>$node->name,'online'=>false,'error'=>$message];
        }

        return ['nodes'=>$results,'checked_at'=>now()];
    }

    public function resolve(Request $request, SystemIssue $issue)
    {
        $this->admin($request);
        $issue->resolveIssue();
        return $issue->fresh();
    }

    public function clientError(Request $request)
    {
        $this->admin($request);
        $data = $request->validate([
            'message'=>'required|string|max:4000',
            'source'=>'nullable|string|max:500',
            'line'=>'nullable|integer',
            'column'=>'nullable|integer',
            'stack'=>'nullable|string|max:12000',
            'url'=>'nullable|string|max:2000',
        ]);

        return SystemIssue::report(
            source: 'frontend',
            title: 'Frontend fejl',
            message: $data['message'],
            severity: 'error',
            type: 'javascript',
            context: [
                'source'=>$data['source'] ?? null,
                'line'=>$data['line'] ?? null,
                'column'=>$data['column'] ?? null,
                'stack'=>$data['stack'] ?? null,
                'url'=>$data['url'] ?? null,
            ]
        );
    }
}
