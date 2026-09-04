<?php

namespace Pterodactyl\Http\Controllers;

use Illuminate\View\View;

class StorefrontController extends Controller
{
    public function home(): View
    {
        return view('storefront.home');
    }

    public function games(): View
    {
        return view('storefront.games', [
            'games' => $this->gamesCatalog(),
        ]);
    }

    public function pricing(): View
    {
        return view('storefront.pricing', [
            'plans' => [
                [
                    'name' => 'Starter',
                    'eyebrow' => 'Til mindre communities',
                    'memory' => '2–4 GB RAM',
                    'cpu' => 'Balanceret CPU',
                    'storage' => 'NVMe-lager',
                    'description' => 'Et enkelt udgangspunkt til små Minecraft-, Terraria- og andre lette game servers.',
                ],
                [
                    'name' => 'Performance',
                    'eyebrow' => 'Mest fleksibel',
                    'memory' => '4–8 GB RAM',
                    'cpu' => 'Høj CPU-prioritet',
                    'storage' => 'NVMe-lager',
                    'description' => 'Til aktive communities, plugins, mods og servere med flere samtidige spillere.',
                    'featured' => true,
                ],
                [
                    'name' => 'Power',
                    'eyebrow' => 'Til tunge workloads',
                    'memory' => '8–16+ GB RAM',
                    'cpu' => 'Performance CPU',
                    'storage' => 'Udvidet NVMe',
                    'description' => 'Til større modpacks, FiveM, Rust og workloads der kræver mere plads og processorkraft.',
                ],
                [
                    'name' => 'Custom',
                    'eyebrow' => 'Byg efter behov',
                    'memory' => 'Valgfri RAM',
                    'cpu' => 'Valgfri CPU',
                    'storage' => 'Valgfrit lager',
                    'description' => 'En skræddersyet løsning til communities, netværk eller særlige krav.',
                ],
            ],
        ]);
    }

    public function features(): View
    {
        return view('storefront.features');
    }

    public function support(): View
    {
        return view('storefront.support');
    }

    private function gamesCatalog(): array
    {
        return [
            ['name' => 'Minecraft', 'tag' => 'Java / Paper / Forge', 'icon' => '⛏', 'description' => 'Vanilla, Paper, plugins og modded servers med fuld fil- og consoleadgang.'],
            ['name' => 'FiveM', 'tag' => 'FXServer', 'icon' => 'V', 'description' => 'Byg og administrér din FiveM-server med console, filer, backups og databaseværktøjer.'],
            ['name' => 'Rust', 'tag' => 'Dedicated Server', 'icon' => 'R', 'description' => 'En stærk base til Rust communities med planlagte tasks, backups og ressourcekontrol.'],
            ['name' => 'Counter-Strike 2', 'tag' => 'SteamCMD', 'icon' => 'CS', 'description' => 'Serverhosting til communities, private matches og egne konfigurationer.'],
            ['name' => 'Palworld', 'tag' => 'Dedicated Server', 'icon' => 'P', 'description' => 'Kør en persistent Palworld-verden og administrér den direkte fra Nodexa.'],
            ['name' => 'Valheim', 'tag' => 'Dedicated Server', 'icon' => 'V', 'description' => 'Enkel hosting til private eller community-baserede Valheim-verdener.'],
            ['name' => 'Terraria', 'tag' => 'Vanilla / tModLoader', 'icon' => 'T', 'description' => 'Letvægts game hosting med hurtig adgang til config, worlds og backups.'],
            ['name' => 'Andre games', 'tag' => 'Egg-baseret', 'icon' => '+', 'description' => 'Nodexa kan udvides med Eggs til flere spil og servertyper efter behov.'],
        ];
    }
}
