<?php

use App\Models\Node;
use App\Models\SystemIssue;
use App\Services\SystemDiagnostics;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** @var SystemDiagnostics $diagnostics */
$diagnostics = $app->make(SystemDiagnostics::class);
$result = $diagnostics->scan();
foreach ($result['checks'] as $check) {
    echo sprintf("[%s] %s%s\n", strtoupper($check['status']), $check['label'], isset($check['message']) ? ': '.$check['message'] : '');
}

foreach (Node::query()->orderBy('id')->get() as $node) {
    $errno = 0; $errstr = ''; $started = microtime(true);
    $socket = @fsockopen($node->fqdn, $node->daemon_port, $errno, $errstr, 3.0);
    $latency = (int) round((microtime(true) - $started) * 1000);

    if (is_resource($socket)) {
        fclose($socket);
        SystemIssue::where('source', 'node')->where('node_id', $node->id)->where('type', 'node_unreachable')->where('status', 'open')->get()->each(fn (SystemIssue $issue) => $issue->resolveIssue());
        echo "[OK] Node {$node->name} {$latency}ms\n";
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
    echo "[FAIL] Node {$node->name}: {$message}\n";
}
