<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use Pterodactyl\Http\Controllers\Controller;
use Symfony\Component\Process\Process;

class UpdateController extends Controller
{
    private const STATE_DIR = '/var/lib/nodexa';
    private const VERSION_FILE = self::STATE_DIR . '/version.json';
    private const STATE_FILE = self::STATE_DIR . '/update-state.json';
    private const LOG_FILE = '/var/log/nodexa-update.log';

    public function index(): View
    {
        $installed = $this->installedVersion();
        $latest = $this->latestVersion($installed, true);
        $state = $this->updateState();

        return view('admin.updates.index', [
            'installed' => $installed,
            'latest' => $latest,
            'state' => $state,
            'log' => $this->tailLog(),
            'updateAvailable' => $this->updateAvailable($installed, $latest),
        ]);
    }

    public function status(): JsonResponse
    {
        $installed = $this->installedVersion();
        $latest = $this->latestVersion($installed);

        return response()->json([
            'state' => $this->updateState(),
            'installed' => $installed,
            'latest' => $latest,
            'update_available' => $this->updateAvailable($installed, $latest),
            'log' => $this->tailLog(),
        ]);
    }

    public function run(): RedirectResponse
    {
        $state = $this->updateState();
        if (($state['status'] ?? 'idle') === 'running') {
            return redirect()->route('admin.updates')->with('update_message', 'En Nodexa-opdatering kører allerede.');
        }

        $installed = $this->installedVersion();
        $latest = $this->latestVersion($installed, true);

        if (!$this->updateAvailable($installed, $latest)) {
            return redirect()->route('admin.updates')->with('update_message', 'Nodexa er allerede opdateret til den nyeste GitHub-version.');
        }

        $trigger = '/usr/local/sbin/nodexa-update-trigger';
        if (!is_executable($trigger)) {
            return redirect()->route('admin.updates')->with('update_error', 'Nodexa update-triggeren er ikke installeret på denne server. Kør setup-updater.sh som root.');
        }

        $process = new Process(['/usr/bin/sudo', $trigger]);
        $process->setTimeout(15);
        $process->run();

        if (!$process->isSuccessful()) {
            $error = trim($process->getErrorOutput() ?: $process->getOutput());
            return redirect()->route('admin.updates')->with('update_error', 'Kunne ikke starte updateren.' . ($error !== '' ? ' ' . $error : ''));
        }

        return redirect()->route('admin.updates')->with('update_message', 'Nodexa-opdateringen er startet. Siden følger status automatisk.');
    }

    private function installedVersion(): array
    {
        $data = $this->readJson(self::VERSION_FILE);

        return [
            'version' => (string) ($data['version'] ?? 'unknown'),
            'commit' => $data['commit'] ?? null,
            'repository' => (string) ($data['repository'] ?? 'yupthatpandadk/Nodexa'),
            'branch' => (string) ($data['branch'] ?? 'pterodactyl-core'),
            'installed_at' => $data['installed_at'] ?? null,
        ];
    }

    private function latestVersion(array $installed, bool $force = false): array
    {
        $repository = preg_replace('/[^A-Za-z0-9_.\/-]/', '', (string) ($installed['repository'] ?? 'yupthatpandadk/Nodexa')) ?: 'yupthatpandadk/Nodexa';
        $branch = preg_replace('/[^A-Za-z0-9_.\/-]/', '', (string) ($installed['branch'] ?? 'pterodactyl-core')) ?: 'pterodactyl-core';
        $cacheKey = 'nodexa:update:latest:' . sha1($repository . ':' . $branch);

        if ($force) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($repository, $branch) {
            try {
                $commitResponse = Http::acceptJson()
                    ->withUserAgent('Nodexa-Panel-Updater')
                    ->timeout(8)
                    ->get("https://api.github.com/repos/{$repository}/commits/{$branch}");

                if (!$commitResponse->successful()) {
                    return ['commit' => null, 'version' => null, 'error' => 'GitHub svarede med HTTP ' . $commitResponse->status()];
                }

                $commitData = $commitResponse->json();
                $version = null;

                try {
                    $versionResponse = Http::accept('text/plain')
                        ->withUserAgent('Nodexa-Panel-Updater')
                        ->timeout(8)
                        ->get("https://raw.githubusercontent.com/{$repository}/{$branch}/VERSION");

                    if ($versionResponse->successful()) {
                        $candidate = ltrim(trim((string) $versionResponse->body()), 'vV');
                        if (preg_match('/^\d+(?:\.\d+){1,3}(?:[-+][0-9A-Za-z.-]+)?$/', $candidate)) {
                            $version = $candidate;
                        }
                    }
                } catch (\Throwable $exception) {
                    // Commit information is still useful if VERSION cannot be read.
                }

                return [
                    'version' => $version,
                    'commit' => $commitData['sha'] ?? null,
                    'message' => trim((string) data_get($commitData, 'commit.message', '')),
                    'author' => data_get($commitData, 'commit.author.name'),
                    'date' => data_get($commitData, 'commit.author.date'),
                    'url' => $commitData['html_url'] ?? null,
                    'error' => null,
                ];
            } catch (\Throwable $exception) {
                return ['commit' => null, 'version' => null, 'error' => 'Kunne ikke kontakte GitHub: ' . $exception->getMessage()];
            }
        });
    }

    private function updateAvailable(array $installed, array $latest): bool
    {
        $installedVersion = ltrim(trim((string) ($installed['version'] ?? '')), 'vV');
        $latestVersion = ltrim(trim((string) ($latest['version'] ?? '')), 'vV');

        // VERSION is the source of truth for releases. A different Git commit by itself
        // must not make an already-installed release appear outdated.
        if ($installedVersion !== '' && $latestVersion !== '' && $installedVersion !== 'unknown') {
            return version_compare($latestVersion, $installedVersion, '>');
        }

        // Compatibility fallback for older installations where VERSION is unavailable.
        if (empty($installed['commit']) || empty($latest['commit'])) {
            return false;
        }

        return strtolower((string) $installed['commit']) !== strtolower((string) $latest['commit']);
    }

    private function updateState(): array
    {
        $state = $this->readJson(self::STATE_FILE);

        return [
            'status' => (string) ($state['status'] ?? 'idle'),
            'message' => (string) ($state['message'] ?? 'Ingen opdatering kører.'),
            'updated_at' => $state['updated_at'] ?? null,
        ];
    }

    private function tailLog(int $lines = 80): string
    {
        if (!is_readable(self::LOG_FILE)) {
            return 'Ingen update-log endnu.';
        }

        $content = @file(self::LOG_FILE, FILE_IGNORE_NEW_LINES);
        if (!is_array($content)) {
            return 'Kunne ikke læse update-loggen.';
        }

        return implode("\n", array_slice($content, -$lines));
    }

    private function readJson(string $path): array
    {
        if (!is_readable($path)) {
            return [];
        }

        $decoded = json_decode((string) @file_get_contents($path), true);
        return is_array($decoded) ? $decoded : [];
    }
}
