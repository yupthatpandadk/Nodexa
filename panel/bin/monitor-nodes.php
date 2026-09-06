<?php

use App\Models\Node;
use App\Models\SystemIssue;
use App\Services\SystemDiagnostics;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

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
    $started = microtime(true);
    $checkedAt = now();
    $url = sprintf('%s://%s:%d/health', $node->scheme, $node->fqdn, $node->daemon_port);

    try {
        $response = Http::acceptJson()
            ->connectTimeout(3)
            ->timeout(5)
            ->get($url)
            ->throw();

        $latency = (int) round((microtime(true) - $started) * 1000);
        $payload = $response->json();
        $isAgent = is_array($payload)
            && ($payload['ok'] ?? false) === true
            && ($payload['service'] ?? null) === 'nodexa-agent';

        if (!$isAgent) {
            throw new RuntimeException('Health endpoint answered, but the response was not a valid Nodexa Agent health payload.');
        }

        $node->update([
            'health_status' => 'online',
            'health_latency_ms' => $latency,
            'health_last_checked_at' => $checkedAt,
            'health_last_seen_at' => $checkedAt,
            'health_message' => null,
        ]);

        SystemIssue::where('source', 'node')
            ->where('node_id', $node->id)
            ->where('type', 'node_unreachable')
            ->where('status', 'open')
            ->get()
            ->each(fn (SystemIssue $issue) => $issue->resolveIssue());

        echo "[OK] Node {$node->name} {$latency}ms\n";
        continue;
    } catch (Throwable $e) {
        $latency = (int) round((microtime(true) - $started) * 1000);
        $message = trim($e->getMessage()) ?: 'Agent health check failed.';
        $diagnosis = 'Panelet kunne ikke validere Nodexa Agent health endpointet.';
        $recommendation = 'Kontroller node, nodexa-agent, daemon-port, HTTPS/TLS og firewall.';

        if ($e instanceof ConnectionException) {
            $lower = strtolower($message);
            if (str_contains($lower, 'refused')) {
                $diagnosis = 'Node-maskinen svarer, men Agent-porten afviser forbindelsen.';
                $recommendation = 'Kontroller at nodexa-agent kører og lytter på den konfigurerede port.';
            } elseif (str_contains($lower, 'timed out') || str_contains($lower, 'timeout')) {
                $diagnosis = 'Noden svarede ikke inden timeout.';
                $recommendation = 'Kontroller netværk, firewall, routing og om node-maskinen er online.';
            } elseif (str_contains($lower, 'resolve') || str_contains($lower, 'name or service not known')) {
                $diagnosis = 'Node-hostnavnet kunne ikke slås op.';
                $recommendation = 'Kontroller FQDN og DNS records.';
            } elseif (str_contains($lower, 'ssl') || str_contains($lower, 'certificate')) {
                $diagnosis = 'TLS-forbindelsen til Node kunne ikke valideres.';
                $recommendation = 'Kontroller certifikat, FQDN og HTTPS-konfiguration.';
            }
        } elseif ($e instanceof RequestException) {
            $status = $e->response?->status();
            $diagnosis = $status === 404
                ? 'Node svarer, men Nodexa Agent health endpointet mangler.'
                : 'Nodexa Agent health endpointet returnerede en HTTP-fejl'.($status ? " ({$status})" : '').'.';
            $recommendation = 'Kontroller at Panel og Agent er opdateret og at reverse proxy videresender /health korrekt.';
        }

        $node->update([
            'health_status' => 'offline',
            'health_latency_ms' => $latency,
            'health_last_checked_at' => $checkedAt,
            'health_message' => mb_substr($message, 0, 4000),
        ]);

        SystemIssue::report('node', "Node {$node->name} kan ikke kontaktes", $message, 'critical', 'node_unreachable', $node->id, null, [
            'fqdn' => $node->fqdn,
            'port' => $node->daemon_port,
            'scheme' => $node->scheme,
            'latency_ms' => $latency,
            'health_url' => $url,
            'diagnosis' => $diagnosis,
            'recommendation' => $recommendation,
        ]);

        echo "[FAIL] Node {$node->name}: {$message}\n";
    }
}
