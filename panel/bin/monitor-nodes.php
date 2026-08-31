<?php

use App\Models\Node;
use App\Models\SystemIssue;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

foreach (Node::query()->orderBy('id')->get() as $node) {
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

        echo "[OK] {$node->name} {$latency}ms\n";
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

    echo "[FAIL] {$node->name}: {$message}\n";
}
