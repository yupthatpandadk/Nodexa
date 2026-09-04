<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Node;
use Pterodactyl\Repositories\Wings\DaemonConfigurationRepository;
use Symfony\Component\Process\Process;

class DiagnosticsController extends Controller
{
    private const FIXER = '/usr/local/sbin/nodexa-diagnostics-fix';

    private const FIX_ACTIONS = [
        'permissions',
        'storage-link',
        'clear-cache',
        'restart-queue',
        'restart-scheduler',
        'restart-web',
        'local-wings',
    ];

    public function __construct(private DaemonConfigurationRepository $wings)
    {
    }

    public function index(): View
    {
        $panelChecks = $this->panelChecks();
        $nodes = $this->nodeChecks();

        return view('admin.diagnostics.index', [
            'panelChecks' => $panelChecks,
            'nodes' => $nodes,
            'recentErrors' => $this->recentLaravelErrors(),
            'panelSummary' => $this->summary($panelChecks),
            'nodeSummary' => [
                'total' => count($nodes),
                'online' => count(array_filter($nodes, fn (array $node) => $node['status'] === 'ok')),
                'warning' => count(array_filter($nodes, fn (array $node) => $node['status'] === 'warning')),
                'offline' => count(array_filter($nodes, fn (array $node) => $node['status'] === 'error')),
            ],
        ]);
    }

    public function fix(Request $request): RedirectResponse
    {
        $action = (string) $request->input('action', '');
        if (!in_array($action, self::FIX_ACTIONS, true)) {
            return redirect()->route('admin.diagnostics')->with('diagnostics_error', 'Ukendt eller ikke-tilladt reparationshandling.');
        }

        if (!is_executable(self::FIXER)) {
            return redirect()->route('admin.diagnostics')->with('diagnostics_error', 'Nodexa Diagnostics repair-helperen er ikke installeret endnu. Kør den seneste Nodexa-opdatering igen.');
        }

        $process = new Process(['/usr/bin/sudo', self::FIXER, $action]);
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            $message = trim($process->getErrorOutput() ?: $process->getOutput());
            return redirect()->route('admin.diagnostics')->with(
                'diagnostics_error',
                'Reparationen kunne ikke gennemføres.' . ($message !== '' ? ' ' . Str::limit($message, 350) : '')
            );
        }

        return redirect()->route('admin.diagnostics')->with('diagnostics_message', 'Nodexa gennemførte reparationen. Systemet er blevet scannet igen.');
    }

    private function panelChecks(): array
    {
        $checks = [];

        $appKey = (string) config('app.key', '');
        $checks[] = $this->check(
            'Laravel APP_KEY',
            $appKey !== '',
            $appKey !== '' ? 'Krypteringsnøglen er konfigureret.' : 'APP_KEY mangler. Panelet kan give 500-fejl og krypterede data kan ikke læses.',
            null,
            'error'
        );

        try {
            DB::select('SELECT 1');
            $checks[] = $this->check('Database', true, 'Forbindelsen til databasen virker.');
        } catch (\Throwable $exception) {
            $checks[] = $this->check('Database', false, 'Nodexa kan ikke kontakte databasen. Kontrollér DB_HOST, bruger, password og MariaDB.', null, 'error');
        }

        $storageWritable = is_writable(storage_path()) && is_writable(base_path('bootstrap/cache'));
        $checks[] = $this->check(
            'Laravel permissions',
            $storageWritable,
            $storageWritable ? 'storage/ og bootstrap/cache/ er skrivbare.' : 'Laravel kan ikke skrive til storage/ eller bootstrap/cache/.',
            $storageWritable ? null : 'permissions',
            'error'
        );

        $storageLink = public_path('storage');
        $linkOk = is_link($storageLink) && file_exists($storageLink);
        $checks[] = $this->check(
            'Public storage',
            $linkOk,
            $linkOk ? 'public/storage peger korrekt på Laravel storage.' : 'public/storage-linket mangler eller er brudt. Uploadede Egg-logoer og andre filer kan fejle.',
            $linkOk ? null : 'storage-link',
            'warning'
        );

        $versionFile = '/var/lib/nodexa/version.json';
        $versionOk = is_readable($versionFile);
        $version = 'ukendt';
        if ($versionOk) {
            $data = json_decode((string) @file_get_contents($versionFile), true);
            $version = is_array($data) ? (string) ($data['version'] ?? 'ukendt') : 'ukendt';
        }
        $checks[] = $this->check(
            'Nodexa version',
            $versionOk,
            $versionOk ? 'Installeret version: v' . $version : 'Nodexas versionsfil kan ikke læses. Update Center kan vise forkert status.',
            null,
            'warning'
        );

        $checks[] = $this->serviceCheck('Nodexa Queue', 'nodexa-queue.service', 'restart-queue', 'Queue-job, backups og baggrundsopgaver');
        $checks[] = $this->serviceCheck('Nodexa Scheduler', 'nodexa-scheduler.timer', 'restart-scheduler', 'Planlagte server-opgaver');
        $checks[] = $this->serviceCheck('Nginx', 'nginx.service', 'restart-web', 'Webserver');

        $updateTrigger = '/usr/local/sbin/nodexa-update-trigger';
        $checks[] = $this->check(
            'Update Center',
            is_executable($updateTrigger),
            is_executable($updateTrigger) ? 'Den begrænsede Nodexa update-trigger er installeret.' : 'Update-triggeren mangler. Opdater-knappen kan ikke starte systemupdateren.',
            null,
            'warning'
        );

        $total = @disk_total_space(base_path());
        $free = @disk_free_space(base_path());
        if (is_numeric($total) && is_numeric($free) && (float) $total > 0) {
            $freePercent = ((float) $free / (float) $total) * 100;
            $used = 100 - $freePercent;
            $status = $freePercent < 8 ? 'error' : ($freePercent < 15 ? 'warning' : 'ok');
            $checks[] = [
                'name' => 'Diskplads',
                'status' => $status,
                'detail' => sprintf('%.1f%% brugt · %.1f GB ledig.', $used, (float) $free / 1073741824),
                'fix' => null,
            ];
        }

        if (is_file('/etc/pterodactyl/config.yml')) {
            $localWings = $this->serviceState(['nodexa-agent.service', 'wings.service']);
            $checks[] = [
                'name' => 'Lokal Nodexa Agent / Wings',
                'status' => $localWings['active'] ? 'ok' : 'error',
                'detail' => $localWings['active']
                    ? 'En lokal Wings-service kører på panelmaskinen.'
                    : 'Der findes en lokal Wings-konfiguration, men ingen Wings-service er aktiv.',
                'fix' => $localWings['active'] ? null : 'local-wings',
            ];
        }

        return $checks;
    }

    private function nodeChecks(): array
    {
        $results = [];

        foreach (Node::query()->orderBy('name')->get() as $node) {
            $base = [
                'id' => $node->id,
                'name' => $node->name,
                'address' => $node->getConnectionAddress(),
                'configuration_url' => route('admin.nodes.view.configuration', $node->id),
                'maintenance' => (bool) $node->maintenance_mode,
                'repair_command' => $this->nodeRepairCommand($node),
                'version' => null,
                'system' => null,
            ];

            try {
                $data = $this->wings->setNode($node)->getSystemInformation();
                $results[] = $base + [
                    'status' => $node->maintenance_mode ? 'warning' : 'ok',
                    'title' => $node->maintenance_mode ? 'Online · Maintenance Mode' : 'Online',
                    'detail' => 'Nodexa kan kontakte Wings API /api/system.',
                    'version' => (string) ($data['version'] ?? 'ukendt'),
                    'system' => trim(sprintf(
                        '%s %s · %s CPU',
                        (string) ($data['os'] ?? 'Unknown'),
                        (string) ($data['architecture'] ?? ''),
                        (string) ($data['cpu_count'] ?? '?')
                    )),
                ];
            } catch (\Throwable $exception) {
                [$title, $detail] = $this->classifyNodeError($exception->getMessage(), $node);
                $results[] = $base + [
                    'status' => 'error',
                    'title' => $title,
                    'detail' => $detail,
                ];
            }
        }

        return $results;
    }

    private function classifyNodeError(string $message, Node $node): array
    {
        $message = strtolower($message);

        if (str_contains($message, 'connection refused') || str_contains($message, 'failed to connect')) {
            return ['Wings svarer ikke', 'Forbindelsen blev afvist. Wings kan være stoppet, eller port ' . $node->daemonListen . ' lytter ikke.'];
        }

        if (str_contains($message, 'timed out') || str_contains($message, 'timeout')) {
            return ['Timeout til Wings', 'Panelet kan ikke nå ' . $node->fqdn . ':' . $node->daemonListen . '. Kontrollér firewall, DNS og Node-netværket.'];
        }

        if (str_contains($message, 'ssl') || str_contains($message, 'certificate') || str_contains($message, 'curl error 60')) {
            return ['SSL/TLS-fejl', 'Wings svarer med en certifikatfejl. Kontrollér Node FQDN, HTTPS-indstilling og Let’s Encrypt-certifikatet.'];
        }

        if (str_contains($message, '401') || str_contains($message, '403') || str_contains($message, 'unauthorized')) {
            return ['Node-token afvist', 'Panelet kan nå Wings, men autorisationen fejler. Generér en ny Configuration/Auto-Deploy kommando for noden.'];
        }

        if (str_contains($message, 'could not resolve') || str_contains($message, 'name or service not known')) {
            return ['DNS-fejl', 'Node-domænet kan ikke slås op fra panelserveren. Kontrollér FQDN og DNS-records.'];
        }

        return ['Wings offline eller utilgængelig', 'Nodexa kunne ikke hente /api/system fra noden. Brug repair-kommandoen nedenfor og kontrollér derefter Node Configuration.'];
    }

    private function nodeRepairCommand(Node $node): string
    {
        $port = (int) $node->daemonListen;

        return implode("\n", [
            'sudo systemctl daemon-reload',
            'sudo systemctl reset-failed nodexa-agent wings 2>/dev/null || true',
            'sudo systemctl restart nodexa-agent 2>/dev/null || sudo systemctl restart wings',
            'sudo systemctl status nodexa-agent --no-pager -l 2>/dev/null || sudo systemctl status wings --no-pager -l',
            "sudo ss -lntp | grep ':{$port}' || true",
        ]);
    }

    private function serviceCheck(string $name, string $service, string $fix, string $purpose): array
    {
        $state = $this->serviceState([$service]);

        return [
            'name' => $name,
            'status' => $state['active'] ? 'ok' : ($state['missing'] ? 'warning' : 'error'),
            'detail' => $state['active']
                ? $purpose . ' kører normalt.'
                : ($state['missing'] ? $service . ' er ikke installeret.' : $service . ' er ikke aktiv.'),
            'fix' => $state['missing'] ? null : $fix,
        ];
    }

    private function serviceState(array $services): array
    {
        $found = false;

        foreach ($services as $service) {
            try {
                $exists = new Process(['systemctl', 'cat', $service]);
                $exists->setTimeout(3);
                $exists->run();
                if (!$exists->isSuccessful()) {
                    continue;
                }

                $found = true;
                $process = new Process(['systemctl', 'is-active', $service]);
                $process->setTimeout(3);
                $process->run();
                if (trim($process->getOutput()) === 'active') {
                    return ['active' => true, 'missing' => false];
                }
            } catch (\Throwable $exception) {
                // Fall through to a non-active result. Diagnostics must never crash the admin page.
            }
        }

        return ['active' => false, 'missing' => !$found];
    }

    private function recentLaravelErrors(): array
    {
        $log = storage_path('logs/laravel.log');
        if (!is_readable($log)) {
            return [];
        }

        try {
            $process = new Process(['tail', '-n', '180', $log]);
            $process->setTimeout(4);
            $process->run();
            if (!$process->isSuccessful()) {
                return [];
            }

            $errors = [];
            foreach (preg_split('/\R/', $process->getOutput()) ?: [] as $line) {
                if (!preg_match('/\.(ERROR|CRITICAL|ALERT|EMERGENCY):/i', $line)) {
                    continue;
                }

                $line = preg_replace('/(password|token|authorization)(\s*[=:]\s*)[^\s,;]+/i', '$1$2[hidden]', $line) ?: $line;
                $errors[] = Str::limit(trim($line), 500);
            }

            return array_slice($errors, -10);
        } catch (\Throwable $exception) {
            return [];
        }
    }

    private function check(string $name, bool $ok, string $detail, ?string $fix = null, string $failureStatus = 'error'): array
    {
        return [
            'name' => $name,
            'status' => $ok ? 'ok' : $failureStatus,
            'detail' => $detail,
            'fix' => $ok ? null : $fix,
        ];
    }

    private function summary(array $checks): array
    {
        return [
            'total' => count($checks),
            'ok' => count(array_filter($checks, fn (array $check) => $check['status'] === 'ok')),
            'warning' => count(array_filter($checks, fn (array $check) => $check['status'] === 'warning')),
            'error' => count(array_filter($checks, fn (array $check) => $check['status'] === 'error')),
        ];
    }
}
