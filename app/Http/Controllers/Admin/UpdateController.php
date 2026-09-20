<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
use Illuminate\View\View;
use Pterodactyl\Http\Controllers\Controller;

class UpdateController extends Controller
{
    private const REPOSITORY = 'yupthatpandadk/Nodexa';

    public function index(): View
    {
        $installed = trim((string) @file_get_contents(base_path('NODEXA_VERSION'))) ?: 'dev';
        $latest = null;
        $error = null;
        $releases = [];

        // Prefer raw.githubusercontent.com for this tiny public version file. The
        // GitHub Contents API is rate limited per server IP and can return HTTP 403
        // on shared/datacenter addresses even though the repository is public.
        try {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'Nodexa-Update-Center'])
                ->get('https://raw.githubusercontent.com/' . self::REPOSITORY . '/main/NODEXA_VERSION');

            if ($response->successful()) {
                $latest = trim((string) $response->body());
            } else {
                // Fallback to the GitHub API. This also supports an optional token
                // without requiring one for public installations.
                $request = Http::timeout(10)->withHeaders([
                    'Accept' => 'application/vnd.github+json',
                    'User-Agent' => 'Nodexa-Update-Center',
                    'X-GitHub-Api-Version' => '2022-11-28',
                ]);

                if ($token = env('NODEXA_GITHUB_TOKEN')) {
                    $request = $request->withToken($token);
                }

                $api = $request->get('https://api.github.com/repos/' . self::REPOSITORY . '/contents/NODEXA_VERSION', ['ref' => 'main']);

                if ($api->successful()) {
                    $latest = trim(base64_decode((string) $api->json('content')));
                } else {
                    $error = 'Could not check for updates (raw HTTP ' . $response->status() . ', GitHub HTTP ' . $api->status() . ').';
                }
            }
        } catch (\Throwable $exception) {
            $error = 'Could not check for updates: ' . $exception->getMessage();
        }

        try {
            $changelog = Http::timeout(5)->get('https://raw.githubusercontent.com/' . self::REPOSITORY . '/main/NODEXA_CHANGELOG.json');
            if ($changelog->successful()) {
                $decoded = $changelog->json();
                $releases = is_array($decoded) && isset($decoded['releases']) && is_array($decoded['releases'])
                    ? $decoded['releases']
                    : [];
            }
        } catch (\Throwable $exception) {
            // Release notes are optional and must never break the Update Center.
            $releases = [];
        }

        $log = storage_path('logs/nodexa-update.log');
        $running = file_exists(storage_path('app/nodexa-update.lock'));

        return view('admin.updates.index', [
            'installed' => $installed,
            'latest' => $latest,
            'error' => $error,
            'running' => $running,
            'releases' => $releases,
            'log' => file_exists($log) ? implode("\n", array_slice(file($log, FILE_IGNORE_NEW_LINES) ?: [], -80)) : null,
        ]);
    }

    public function progress(): JsonResponse
    {
        $progressFile = storage_path('app/nodexa-update-progress.json');
        $lock = storage_path('app/nodexa-update.lock');
        $pidFile = storage_path('app/nodexa-update.pid');
        $running = $this->isUpdaterRunning($lock, $pidFile);
        $progress = ['percent' => $running ? 1 : 100, 'step' => $running ? 'Starting update…' : 'Idle', 'status' => $running ? 'running' : 'idle'];

        if (is_file($progressFile)) {
            $decoded = json_decode((string) @file_get_contents($progressFile), true);
            if (is_array($decoded)) {
                $progress = array_merge($progress, $decoded);
            }
        }

        $progress['running'] = $running;

        return response()->json($progress);
    }

    public function install(): RedirectResponse
    {
        $lock = storage_path('app/nodexa-update.lock');
        $pidFile = storage_path('app/nodexa-update.pid');
        if ($this->isUpdaterRunning($lock, $pidFile)) {
            return redirect()->route('admin.updates')->with('error', 'An update is already running.');
        }

        @unlink($lock);
        @unlink($pidFile);

        $script = base_path('bin/nodexa-update');
        if (!is_file($script)) {
            return redirect()->route('admin.updates')->with('error', 'Updater script is missing.');
        }

        $progressFile = storage_path('app/nodexa-update-progress.json');
        @file_put_contents($progressFile, json_encode([
            'percent' => 1,
            'step' => 'Starting Nodexa updater…',
            'status' => 'running',
            'updated_at' => now()->toIso8601String(),
        ]));

        $log = storage_path('logs/nodexa-update.log');
        // The panel normally runs as www-data. Run the updater with explicit HOME and
        // Git safe.directory so Git does not stall/fail because the checkout belongs
        // to another user. Append stderr/stdout to the live update log.
        $command = sprintf(
            'HOME=/tmp COMPOSER_HOME=/tmp/composer GIT_TERMINAL_PROMPT=0 nohup bash %s >> %s 2>&1 < /dev/null & echo $!',
            escapeshellarg($script),
            escapeshellarg($log)
        );

        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        if ($exitCode !== 0 || empty($output) || !ctype_digit(trim((string) $output[0]))) {
            @unlink($lock);
            @file_put_contents($progressFile, json_encode([
                'percent' => 100,
                'step' => 'Could not start updater process.',
                'status' => 'failed',
                'updated_at' => now()->toIso8601String(),
            ]));

            return redirect()->route('admin.updates')->with('error', 'Could not start the Nodexa updater process.');
        }

        $pid = trim((string) $output[0]);
        @file_put_contents($pidFile, $pid);

        return redirect()->route('admin.updates')->with('success', 'Nodexa update started. Progress updates automatically.');
    }

    private function isUpdaterRunning(string $lock, string $pidFile): bool
    {
        if (!is_file($lock) && !is_file($pidFile)) {
            return false;
        }

        $pid = is_file($pidFile) ? trim((string) @file_get_contents($pidFile)) : '';
        if ($pid !== '' && ctype_digit($pid) && is_dir('/proc/' . $pid)) {
            return true;
        }

        // A stale lock/PID must never leave Update Center stuck forever.
        if (is_file($lock) && (time() - (int) @filemtime($lock)) < 30) {
            return true;
        }

        @unlink($lock);
        @unlink($pidFile);

        return false;
    }
}
