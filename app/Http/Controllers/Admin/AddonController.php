<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Addon;
use Pterodactyl\Models\Egg;

class AddonController extends Controller
{
    private const MINECRAFT_PLUGIN_MANAGER = 'minecraft-plugin-manager';

    public function __construct(private AlertsMessageBag $alert)
    {
    }

    public function index(): View
    {
        $addon = Addon::query()->firstOrCreate(
            ['name' => self::MINECRAFT_PLUGIN_MANAGER],
            [
                'game' => 'Minecraft',
                'category' => 'Plugin Manager',
                'description' => 'Lader Minecraft-serverejere finde, installere, opdatere og afinstallere plugins via Modrinth.',
                'version' => '1.0',
                'compatible_versions' => 'Paper, Purpur, Spigot, Folia, Bukkit',
                'download_url' => 'https://modrinth.com',
                'install_path' => '/plugins/',
                'egg_ids' => null,
                'enabled' => true,
            ]
        );

        return view('admin.addons.index', [
            'addon' => $addon,
            'eggs' => Egg::query()->with('nest')->orderBy('name')->get(),
        ]);
    }

    public function show(Addon $addon): View
    {
        abort_unless($addon->name === self::MINECRAFT_PLUGIN_MANAGER, 404);

        return view('admin.addons.show', [
            'addon' => $addon,
            'eggs' => Egg::query()->with('nest')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Addon $addon): RedirectResponse
    {
        abort_unless($addon->name === self::MINECRAFT_PLUGIN_MANAGER, 404);

        $data = $request->validate([
            'egg_ids' => 'nullable|array',
            'egg_ids.*' => 'integer|exists:eggs,id',
        ]);

        $addon->update([
            'egg_ids' => collect($data['egg_ids'] ?? [])->map(fn ($id) => (int) $id)->implode(',') ?: null,
            'enabled' => $request->boolean('enabled'),
        ]);

        $this->alert->success('Minecraft Plugin Manager er opdateret.')->flash();

        return redirect()->route('admin.addons.show', $addon);
    }
}
