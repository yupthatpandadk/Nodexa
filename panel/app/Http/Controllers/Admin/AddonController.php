<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Nodexa\AddonManager;

class AddonController extends Controller
{
    public function __construct(private AddonManager $addons)
    {
    }

    public function index(): View
    {
        return view('admin.addons.index', [
            'addons' => $this->addons->all(),
            'nodexaVersion' => $this->addons->currentVersion(),
        ]);
    }

    public function install(string $slug): RedirectResponse
    {
        try {
            $addon = $this->addons->install($slug);

            return $this->back(sprintf('%s v%s blev installeret og aktiveret.', $addon['name'], $addon['version']), 'success');
        } catch (\Throwable $exception) {
            report($exception);

            return $this->back($exception->getMessage(), 'danger');
        }
    }

    public function toggle(Request $request, string $slug): RedirectResponse
    {
        try {
            $enabled = $request->boolean('enabled');
            $addon = $this->addons->find($slug);
            $this->addons->setEnabled($slug, $enabled);

            return $this->back(sprintf('%s blev %s.', $addon['name'] ?? $slug, $enabled ? 'aktiveret' : 'deaktiveret'), 'success');
        } catch (\Throwable $exception) {
            report($exception);

            return $this->back($exception->getMessage(), 'danger');
        }
    }

    public function uninstall(string $slug): RedirectResponse
    {
        try {
            $addon = $this->addons->find($slug);
            $this->addons->uninstall($slug);

            return $this->back(sprintf('%s blev afinstalleret. Eventuelle addon-data er ikke slettet automatisk.', $addon['name'] ?? $slug), 'success');
        } catch (\Throwable $exception) {
            report($exception);

            return $this->back($exception->getMessage(), 'danger');
        }
    }

    private function back(string $message, string $type): RedirectResponse
    {
        return redirect()->route('admin.addons')->with('addon_status', [
            'type' => $type,
            'message' => $message,
        ]);
    }
}
