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
    private const DEFINITIONS = [
        'minecraft-plugin-manager' => [
            'title' => 'Minecraft Plugin Manager',
            'category' => 'Plugin Manager',
            'description' => 'Installer og administrer Minecraft plugins direkte fra Modrinth.',
            'version' => '1.0',
            'compatible_versions' => 'Paper, Purpur, Spigot, Folia, Bukkit',
            'download_url' => 'https://modrinth.com',
            'install_path' => '/plugins/',
            'provider' => 'Modrinth',
            'icon' => 'fa-puzzle-piece',
            'color' => '#9b7cff',
            'background' => 'rgba(124,58,237,.16)',
            'tab' => 'Plugins',
        ],
        'minecraft-mod-manager' => [
            'title' => 'Minecraft Mod Manager',
            'category' => 'Mod Manager',
            'description' => 'Mods fra Modrinth med automatisk Forge, NeoForge, Fabric og Quilt filtrering.',
            'version' => '1.0',
            'compatible_versions' => 'Forge, NeoForge, Fabric, Quilt',
            'download_url' => 'https://modrinth.com',
            'install_path' => '/mods/',
            'provider' => 'Modrinth',
            'icon' => 'fa-cubes',
            'color' => '#22d3ee',
            'background' => 'rgba(6,182,212,.14)',
            'tab' => 'Mods',
        ],
        'minecraft-player-list' => [
            'title' => 'Minecraft Player List',
            'category' => 'Player List',
            'description' => 'Vis online spillere, player count og max slots live direkte fra Minecraft-serveren.',
            'version' => '1.0',
            'compatible_versions' => 'Java Edition & Bedrock console-compatible servers',
            'download_url' => null,
            'install_path' => null,
            'provider' => 'Nodexa / Wings',
            'icon' => 'fa-users',
            'color' => '#34d399',
            'background' => 'rgba(16,185,129,.14)',
            'tab' => 'Player List',
        ],
        'minecraft-server-chat' => [
            'title' => 'MC Server Chat',
            'category' => 'Server Chat',
            'description' => 'Live Minecraft-chat i Nodexa med join/leave events og mulighed for at sende beskeder til spillerne.',
            'version' => '1.0',
            'compatible_versions' => 'Java Edition & console-compatible Minecraft servers',
            'download_url' => null,
            'install_path' => null,
            'provider' => 'Nodexa / Wings',
            'icon' => 'fa-comments',
            'color' => '#60a5fa',
            'background' => 'rgba(59,130,246,.14)',
            'tab' => 'Server Chat',
        ],
    ];

    public function __construct(private AlertsMessageBag $alert)
    {
    }

    public function index(): View
    {
        $eggs = Egg::query()->with('nest')->orderBy('name')->get();

        $minecraftEggIds = $eggs
            ->filter(function (Egg $egg) {
                $haystack = strtolower(trim((string) optional($egg->nest)->name . ' ' . $egg->name));

                foreach (['minecraft', 'paper', 'purpur', 'spigot', 'bukkit', 'folia', 'fabric', 'forge', 'neoforge', 'quilt', 'bedrock'] as $keyword) {
                    if (str_contains($haystack, $keyword)) {
                        return true;
                    }
                }

                return false;
            })
            ->pluck('id')
            ->implode(',');

        $addons = collect(self::DEFINITIONS)->map(function (array $definition, string $name) use ($minecraftEggIds) {
            $utilityAddon = in_array($name, ['minecraft-player-list', 'minecraft-server-chat'], true);

            return Addon::query()->firstOrCreate(
                ['name' => $name],
                [
                    'game' => 'Minecraft',
                    'category' => $definition['category'],
                    'description' => $definition['description'],
                    'version' => $definition['version'],
                    'compatible_versions' => $definition['compatible_versions'],
                    'download_url' => $definition['download_url'],
                    'install_path' => $definition['install_path'],
                    'egg_ids' => $utilityAddon ? ($minecraftEggIds ?: null) : null,
                    'enabled' => true,
                ]
            );
        });

        return view('admin.addons.index', [
            'addons' => $addons,
            'definitions' => self::DEFINITIONS,
            'eggs' => $eggs,
        ]);
    }

    public function show(Addon $addon): View
    {
        $meta = self::DEFINITIONS[$addon->name] ?? null;
        abort_unless($meta !== null, 404);

        return view('admin.addons.show', [
            'addon' => $addon,
            'meta' => $meta,
            'eggs' => Egg::query()->with('nest')->get()->sortBy('name')->values(),
        ]);
    }

    public function update(Request $request, Addon $addon): RedirectResponse
    {
        $meta = self::DEFINITIONS[$addon->name] ?? null;
        abort_unless($meta !== null, 404);

        $data = $request->validate([
            'egg_ids' => 'nullable|array',
            'egg_ids.*' => 'integer|exists:eggs,id',
        ]);

        $addon->update([
            'egg_ids' => collect($data['egg_ids'] ?? [])->map(fn ($id) => (int) $id)->implode(',') ?: null,
            'enabled' => $request->boolean('enabled'),
        ]);

        $this->alert->success($meta['title'] . ' er opdateret.')->flash();

        return redirect()->route('admin.addons.show', $addon);
    }
}
