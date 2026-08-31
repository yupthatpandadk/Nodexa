<?php
namespace App\Http\Controllers;

use App\Models\Node;
use App\Models\SystemIssue;
use App\Services\SystemDiagnostics;
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

    public function scanAll(Request $request, SystemDiagnostics $diagnostics)
    {
        $this->admin($request);
        return [
            'system' => $diagnostics->scan(),
            'nodes' => $this->performNodeScan(),
            'checked_at' => now(),
        ];
    }

    public function scanSystem(Request $request, SystemDiagnostics $diagnostics)
    {
        $this->admin($request);
        return $diagnostics->scan();
    }

    public function scanNodes(Request $request)
    {
        $this->admin($request);
        return ['nodes'=>$this->performNodeScan(),'checked_at'=>now()];
    }

    private function performNodeScan(): array
    {
        $results = [];
        foreach (Node::query()->orderBy('name')->get() as $node) {
            $errno = 0; $errstr = ''; $started = microtime(true);
            $socket = @fsockopen($node->fqdn, $node->daemon_port, $errno, $errstr, 3.0);
            $latency = (int) round((microtime(true) - $started) * 1000);
            if (is_resource($socket)) {
                fclose($socket);
                SystemIssue::where('source', 'node')->where('node_id', $node->id)->where('type', 'node_unreachable')->where('status', 'open')->get()->each(fn (SystemIssue $issue) => $issue->resolveIssue());
                $results[] = ['node_id'=>$node->id,'name'=>$node->name,'online'=>true,'latency_ms'=>$latency];
                continue;
            }
            $message = trim(($errstr ?: 'Connection failed') . ($errno ? " (errno {$errno})" : ''));
            $lower = strtolower($message);
            $diagnosis = 'Panelet kan ikke etablere TCP-forbindelse til Nodexa Agent.';
            $recommendation = 'Kontroller node, Agent-service, daemon-port og firewall.';
            if (str_contains($lower, 'refused')) { $diagnosis = 'Node-maskinen svarer, men Agent-porten afviser forbindelsen.'; $recommendation = 'Kontroller at nodexa-agent kører og lytter på den konfigurerede port.'; }
            elseif (str_contains($lower, 'timed out') || str_contains($lower, 'timeout')) { $diagnosis = 'Noden svarede ikke inden timeout.'; $recommendation = 'Kontroller netværk, firewall, routing og om node-maskinen er online.'; }
            elseif (str_contains($lower, 'name') || str_contains($lower, 'resolve')) { $diagnosis = 'Node-hostnavnet kunne ikke slås op.'; $recommendation = 'Kontroller FQDN og DNS records.'; }
            SystemIssue::report('node', "Node {$node->name} kan ikke kontaktes", $message, 'critical', 'node_unreachable', $node->id, null, [
                'fqdn'=>$node->fqdn,'port'=>$node->daemon_port,'scheme'=>$node->scheme,'latency_ms'=>$latency,
                'diagnosis'=>$diagnosis,'recommendation'=>$recommendation,
            ]);
            $results[] = ['node_id'=>$node->id,'name'=>$node->name,'online'=>false,'error'=>$message,'diagnosis'=>$diagnosis,'recommendation'=>$recommendation];
        }
        return $results;
    }

    public function resolve(Request $request, SystemIssue $issue)
    {
        $this->admin($request);
        $issue->resolveIssue();
        return $issue->fresh();
    }

    public function clientError(Request $request)
    {
        $data = $request->validate(['message'=>'required|string|max:4000','source'=>'nullable|string|max:500','line'=>'nullable|integer','column'=>'nullable|integer','stack'=>'nullable|string|max:12000','url'=>'nullable|string|max:2000']);
        return SystemIssue::report('frontend','Frontend fejl',$data['message'],'error','javascript',null,null,[
            'user_id'=>$request->user()?->id,'source'=>$data['source']??null,'line'=>$data['line']??null,'column'=>$data['column']??null,'stack'=>$data['stack']??null,'url'=>$data['url']??null,
            'diagnosis'=>'JavaScript-fejl registreret i Nodexa brugerfladen.','recommendation'=>'Åbn de tekniske detaljer for fil, linje og stack trace, og kontroller den berørte frontend-komponent.'
        ]);
    }
}
