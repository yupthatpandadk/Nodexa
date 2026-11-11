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

                // If direct NODEXA_VERSION checks are blocked (common on datacenter IPs),
                // use the signed-in repository changelog as a public version fallback.
                if (!$latest && is_array($decoded)) {
                    $fallback = trim((string) ($decoded['latest'] ?? ($releases[0]['version'] ?? '')));
                    if ($fallback !== '') {
                        $latest = $fallback;
                        $error = null;
                    }
                }
            }
        } catch (\Throwable $exception) {
            // Release notes are optional and must never break the Update Center.
            $releases = [];
        }

        $log = storage_path('logs/nodexa-update.log');
        $lock = storage_path('app/nodexa-update.lock');
        $pidFile = storage_path('app/nodexa-update.pid');
        $running = $this->isUpdaterRunning($lock, $pidFile);
        $progressJson = @file_get_contents(storage_path('app/nodexa-update-progress.json'));
        $previousProgress = is_string($progressJson) ? json_decode($progressJson, true) : null;
        $updateFailed = !$running
            && in_array(($previousProgress['status'] ?? ''), ['failed', 'queued', 'running'], true)
            && ($latest === null || version_compare($installed, $latest, '<'));

        return view('admin.updates.index', [
            'installed' => $installed,
            'latest' => $latest,
            'error' => $error,
            'running' => $running,
            'updateFailed' => $updateFailed,
            'lastProgress' => is_array($previousProgress) ? $previousProgress : [],
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
        // Never allow a reinstall when this installation already reports the
        // newest published Nodexa version. This is enforced server-side as well
        // as in the UI so a manual POST cannot bypass the disabled button.
        $installed = trim((string) @file_get_contents(base_path('NODEXA_VERSION'))) ?: 'dev';

        try {
            $versionResponse = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'Nodexa-Update-Center'])
                ->get('https://raw.githubusercontent.com/' . self::REPOSITORY . '/main/NODEXA_VERSION');

            if (!$versionResponse->successful()) {
                return redirect()->route('admin.updates')->with('error', 'Could not verify the latest Nodexa version. Try Check again.');
            }

            $latest = trim((string) $versionResponse->body());

            if ($latest === '' || $installed === $latest || ($installed !== 'dev' && version_compare($installed, $latest, '>='))) {
                return redirect()->route('admin.updates')->with('success', 'Nodexa is already up to date. A new update must be available before updating again.');
            }
        } catch (\Throwable $exception) {
            return redirect()->route('admin.updates')->with('error', 'Could not verify the latest Nodexa version: ' . $exception->getMessage());
        }

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

        // Reserve the short startup window so a second administrator cannot
        // queue a duplicate job before systemd has started the unit.
        if (@file_put_contents($lock, now()->toIso8601String()) === false) {
            return redirect()->route('admin.updates')->with('error', 'Update Center cannot write its lock file. Check storage/app permissions.');
        }
        @file_put_contents($progressFile, json_encode([
            'percent' => 1,
            'step' => 'Waiting for privileged Nodexa update service…',
            'status' => 'queued',
            'updated_at' => now()->toIso8601String(),
        ]));

        // Do not execute root-owned source updates as the PHP web worker and
        // do not grant www-data write access to the application source tree.
        // setup_update_service.sh grants exactly this fixed systemctl command.
        $output = [];
        $exitCode = 0;
        exec('sudo -n /usr/bin/systemctl start --no-block nodexa-update.service 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            @unlink($lock);
            @file_put_contents($progressFile, json_encode([
                'percent' => 100,
                'step' => 'Privileged updater not configured or could not start.',
                'status' => 'failed',
                'updated_at' => now()->toIso8601String(),
            ]));

            return redirect()->route('admin.updates')->with('error',
                'Updater service could not start. Run the Nodexa installer with sudo over SSH once to configure secure web updates.'
            );
        }

        return redirect()->route('admin.updates')->with('success', 'Nodexa update queued safely through systemd. Progress updates automatically.');
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
