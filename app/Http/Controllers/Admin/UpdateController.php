<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
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

        try {
            $response = Http::timeout(10)->withHeaders(['Accept' => 'application/vnd.github+json'])
                ->get('https://api.github.com/repos/' . self::REPOSITORY . '/contents/NODEXA_VERSION', ['ref' => 'main']);

            if ($response->successful()) {
                $latest = trim(base64_decode((string) $response->json('content')));
            } else {
                $error = 'GitHub returned HTTP ' . $response->status() . '.';
            }
        } catch (\Throwable $exception) {
            $error = $exception->getMessage();
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
        $running = file_exists(storage_path('app/nodexa-update.lock'));
        $progress = ['percent' => $running ? 5 : 100, 'step' => $running ? 'Starting update…' : 'Idle', 'status' => $running ? 'running' : 'idle'];

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
        if (file_exists($lock)) {
            return redirect()->route('admin.updates')->with('error', 'An update is already running.');
        }

        $script = base_path('bin/nodexa-update');
        if (!is_file($script)) {
            return redirect()->route('admin.updates')->with('error', 'Updater script is missing.');
        }

        @touch($lock);
        $log = storage_path('logs/nodexa-update.log');
        $command = sprintf(
            'nohup bash %s > %s 2>&1 < /dev/null &',
            escapeshellarg($script),
            escapeshellarg($log)
        );

        exec($command);

        return redirect()->route('admin.updates')->with('success', 'Nodexa update started. Refresh this page to follow progress.');
    }
}
