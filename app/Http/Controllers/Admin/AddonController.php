<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Addon;

class AddonController extends Controller
{
    public function __construct(private AlertsMessageBag $alert)
    {
    }

    public function index(): View
    {
        return view('admin.addons.index', [
            'addons' => Addon::query()->orderBy('game')->orderBy('category')->orderBy('name')->get()->groupBy('game'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Addon::query()->create($this->validated($request));
        $this->alert->success('Addon er oprettet.')->flash();
        return redirect()->route('admin.addons');
    }

    public function update(Request $request, Addon $addon): RedirectResponse
    {
        $addon->update($this->validated($request));
        $this->alert->success('Addon er opdateret.')->flash();
        return redirect()->route('admin.addons');
    }

    public function delete(Addon $addon): RedirectResponse
    {
        $addon->delete();
        $this->alert->success('Addon er slettet.')->flash();
        return redirect()->route('admin.addons');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'game' => 'required|string|max:100',
            'category' => 'required|string|max:100',
            'name' => 'required|string|max:150',
            'description' => 'nullable|string|max:1000',
            'version' => 'nullable|string|max:100',
            'compatible_versions' => 'nullable|string|max:255',
            'download_url' => 'required|url|max:2048',
            'install_path' => 'required|string|max:255',
            'egg_ids' => 'nullable|string|max:255',
        ]);
        $data['enabled'] = $request->boolean('enabled');
        return $data;
    }
}
