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
    private const REPO = 'yupthatpandadk/Nodexa';
    private const BRANCH = 'main';

    public function index(): View
    {
        $installed = $this->installedVersion();
        $latest = $this->latestVersion(true);
        return view('admin.updates.index', [
            'installed' => $installed,
            'latest' => $latest,
            'state' => $this->updateState(),
            'changelog' => $this->changelog($latest['commit'] ?? null),
            'updateAvailable' => $this->updateAvailable($installed, $latest),
        ]);
    }

    public function status(): JsonResponse
    {
        $installed = $this->installedVersion();
        $latest = $this->latestVersion(true);
        return response()->json([
            'state' => $this->updateState(),
            'installed' => $installed,
            'latest' => $latest,
            'update_available' => $this->updateAvailable($installed, $latest),
        ]);
    }

    public function run(): RedirectResponse
    {
        $state = $this->updateState();
        if (($state['status'] ?? 'idle') === 'running') {
            return redirect()->route('admin.updates')->with('update_message', 'En Nodexa-opdatering kører allerede.');
        }
        $installed = $this->installedVersion();
        $latest = $this->latestVersion(true);
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
        $version = (string) ($data['version'] ?? 'unknown');
        if ($version === 'unknown' || $version === '') {
            foreach ([base_path('../VERSION'), base_path('VERSION'), dirname(base_path()) . '/VERSION'] as $path) {
                if (!is_readable($path)) continue;
                $candidate = $this->normaliseVersion((string) @file_get_contents($path));
                if ($candidate !== null) { $version = $candidate; break; }
            }
        }
        return [
            'version' => $version,
            'commit' => $data['commit'] ?? null,
            'repository' => (string) ($data['repository'] ?? self::REPO),
            'branch' => (string) ($data['branch'] ?? self::BRANCH),
            'installed_at' => $data['installed_at'] ?? null,
        ];
    }

    private function latestVersion(bool $force = false): array
    {
        $key = 'nodexa:update:latest:v6';
        if ($force) Cache::forget($key);
        $fetch = function () {
            $version = null;
            $commit = null;
            $meta = [];
            $errors = [];
            $headers = ['Cache-Control' => 'no-cache, no-store, max-age=0', 'Pragma' => 'no-cache'];

            try {
                $response = Http::acceptJson()->withUserAgent('Nodexa-Panel-Updater')->withHeaders($headers)->timeout(8)
                    ->get('https://api.github.com/repos/' . self::REPO . '/commits/' . self::BRANCH);
                if ($response->successful()) {
                    $json = $response->json();
                    $commit = $json['sha'] ?? null;
                    $meta = [
                        'message' => trim((string) data_get($json, 'commit.message', '')),
                        'author' => data_get($json, 'commit.author.name'),
                        'date' => data_get($json, 'commit.author.date'),
                        'url' => $json['html_url'] ?? null,
                    ];
                } else $errors[] = 'GitHub API HTTP ' . $response->status();
            } catch (\Throwable $e) { $errors[] = 'GitHub API utilgængelig'; }

            // VERSION is deliberately read from raw main as a second, independent path.
            // This keeps Update Center working when api.github.com is rate-limited or blocked.
            try {
                $response = Http::withUserAgent('Nodexa-Panel-Updater')->withHeaders($headers)->timeout(8)
                    ->get('https://raw.githubusercontent.com/' . self::REPO . '/' . self::BRANCH . '/VERSION', ['cb' => (string) microtime(true)]);
                if ($response->successful()) $version = $this->normaliseVersion($response->body());
                else $errors[] = 'Raw VERSION HTTP ' . $response->status();
            } catch (\Throwable $e) { $errors[] = 'Raw VERSION utilgængelig'; }

            // Final fallback: LATEST contains the same public release number.
            if ($version === null) {
                try {
                    $response = Http::withUserAgent('Nodexa-Panel-Updater')->withHeaders($headers)->timeout(8)
                        ->get('https://raw.githubusercontent.com/' . self::REPO . '/' . self::BRANCH . '/LATEST', ['cb' => (string) microtime(true)]);
                    if ($response->successful()) $version = $this->normaliseVersion(strtok($response->body(), "\r\n"));
                } catch (\Throwable $e) { $errors[] = 'LATEST utilgængelig'; }
            }

            return array_merge([
                'version' => $version,
                'commit' => $commit,
                'error' => $version === null ? implode(' · ', array_unique($errors)) : null,
            ], $meta);
        };
        return $force ? $fetch() : Cache::remember($key, now()->addSeconds(10), $fetch);
    }

    private function changelog(?string $commit = null): array
    {
        $entries = [];
        $known = [];
        $map = fn ($e) => [
            'version' => (string) ($e['version'] ?? ''), 'sha' => $e['sha'] ?? '',
            'title' => (string) ($e['title'] ?? 'Nodexa update'),
            'body' => (string) ($e['description'] ?? $e['body'] ?? ''),
            'author' => $e['author'] ?? 'Nodexa', 'date' => $e['date'] ?? null,
            'url' => $e['url'] ?? null, 'type' => $e['type'] ?? null,
        ];
        $headers = ['Cache-Control' => 'no-cache, no-store, max-age=0', 'Pragma' => 'no-cache'];

        // Prefer raw GitHub so changelog does not disappear when GitHub API fails.
        try {
            $response = Http::withUserAgent('Nodexa-Panel-Updater')->withHeaders($headers)->timeout(8)
                ->get('https://raw.githubusercontent.com/' . self::REPO . '/' . self::BRANCH . '/CHANGELOG.json', ['cb' => (string) microtime(true)]);
            if ($response->successful()) {
                $remote = json_decode($response->body(), true);
                if (is_array($remote)) foreach ($remote as $entry) {
                    $version = (string) ($entry['version'] ?? '');
                    if ($version === '' || isset($known[$version])) continue;
                    $entries[] = $map($entry); $known[$version] = true;
                }
            }
        } catch (\Throwable $e) {}

        foreach ([base_path('../CHANGELOG.json'), base_path('CHANGELOG.json'), dirname(base_path()) . '/CHANGELOG.json'] as $path) {
            if (!is_readable($path)) continue;
            $local = json_decode((string) @file_get_contents($path), true);
            if (!is_array($local)) continue;
            foreach ($local as $entry) {
                $version = (string) ($entry['version'] ?? '');
                if ($version === '' || isset($known[$version])) continue;
                $entries[] = $map($entry); $known[$version] = true;
            }
        }
        usort($entries, fn ($a, $b) => version_compare(ltrim((string) $b['version'], 'vV'), ltrim((string) $a['version'], 'vV')));
        return $entries;
    }

    private function normaliseVersion(?string $value): ?string
    {
        $value = ltrim(trim((string) $value), 'vV');
        return preg_match('/^\d+(?:\.\d+){1,3}(?:[-+][0-9A-Za-z.-]+)?$/', $value) ? $value : null;
    }

    private function updateAvailable(array $installed, array $latest): bool
    {
        $iv = $this->normaliseVersion((string) ($installed['version'] ?? ''));
        $lv = $this->normaliseVersion((string) ($latest['version'] ?? ''));
        if ($iv !== null && $lv !== null) {
            if (version_compare($lv, $iv, '>')) return true;
            if (version_compare($lv, $iv, '<')) return false;
            if (!empty($installed['commit']) && !empty($latest['commit'])) return strtolower((string) $installed['commit']) !== strtolower((string) $latest['commit']);
            return false;
        }
        return !empty($installed['commit']) && !empty($latest['commit']) && strtolower((string) $installed['commit']) !== strtolower((string) $latest['commit']);
    }

    private function updateState(): array
    {
        $state = $this->readJson(self::STATE_FILE);
        return [
            'status' => (string) ($state['status'] ?? 'idle'),
            'message' => (string) ($state['message'] ?? 'Ingen opdatering kører.'),
            'progress' => max(0, min(100, (int) ($state['progress'] ?? 0))),
            'step' => (string) ($state['step'] ?? ''),
            'updated_at' => $state['updated_at'] ?? null,
        ];
    }

    private function readJson(string $path): array
    {
        if (!is_readable($path)) return [];
        $data = json_decode((string) @file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }
}
