<?php

namespace Pterodactyl\Http\Controllers\Admin;

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

        $log = storage_path('logs/nodexa-update.log');
        $running = file_exists(storage_path('app/nodexa-update.lock'));

        return view('admin.updates.index', [
            'installed' => $installed,
            'latest' => $latest,
            'error' => $error,
            'running' => $running,
            'log' => file_exists($log) ? implode("\n", array_slice(file($log, FILE_IGNORE_NEW_LINES) ?: [], -80)) : null,
        ]);
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
