<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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
        $latest = $this->latestVersion($installed);
        $state = $this->updateState();

        return view('admin.updates.index', [
            'installed' => $installed,
            'latest' => $latest,
            'state' => $state,
            'log' => $this->tailLog(),
            'updateAvailable' => !empty($installed['commit'])
                && !empty($latest['commit'])
                && strtolower((string) $installed['commit']) !== strtolower((string) $latest['commit']),
        ]);
    }

    public function status(): JsonResponse
    {
        return response()->json([
            'state' => $this->updateState(),
            'installed' => $this->installedVersion(),
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
        $latest = $this->latestVersion($installed);
        if (!empty($installed['commit']) && !empty($latest['commit']) && strtolower((string) $installed['commit']) === strtolower((string) $latest['commit'])) {
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

    private function latestVersion(array $installed): array
    {
        $repository = preg_replace('/[^A-Za-z0-9_.\/-]/', '', (string) ($installed['repository'] ?? 'yupthatpandadk/Nodexa')) ?: 'yupthatpandadk/Nodexa';
        $branch = preg_replace('/[^A-Za-z0-9_.\/-]/', '', (string) ($installed['branch'] ?? 'pterodactyl-core')) ?: 'pterodactyl-core';

        try {
            $response = Http::acceptJson()
                ->withUserAgent('Nodexa-Panel-Updater')
                ->timeout(8)
                ->get("https://api.github.com/repos/{$repository}/commits/{$branch}");

            if (!$response->successful()) {
                return ['commit' => null, 'error' => 'GitHub svarede med HTTP ' . $response->status()];
            }

            $data = $response->json();
            return [
                'commit' => $data['sha'] ?? null,
                'message' => trim((string) data_get($data, 'commit.message', '')),
                'author' => data_get($data, 'commit.author.name'),
                'date' => data_get($data, 'commit.author.date'),
                'url' => $data['html_url'] ?? null,
                'error' => null,
            ];
        } catch (\Throwable $exception) {
            return ['commit' => null, 'error' => 'Kunne ikke kontakte GitHub: ' . $exception->getMessage()];
        }
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
