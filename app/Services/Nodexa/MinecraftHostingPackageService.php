<?php

namespace Pterodactyl\Services\Nodexa;

use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\Egg;
use RuntimeException;

/**
 * Named Minecraft hosting plans for Nodexa's existing Pterodactyl storefront.
 *
 * These profiles are editable SUGGESTIONS. Generating them never publishes
 * anything, modifies existing prices or creates customer servers.
 */
class MinecraftHostingPackageService
{
    /**
     * Memory/disk in MiB, CPU as Pterodactyl percentages.
     * Prices are example DKK/month (not a claim about market rates).
     */
    public const TIERS = [
        'dirt' => [
            'name' => 'Dirt',
            'memory' => 2048, 'cpu' => 100, 'disk' => 10240,
            'databases' => 1, 'backups' => 1, 'allocations' => 1, 'price' => 29,
            'description' => 'Til en mindre Minecraft-verden med lette plugins.',
        ],
        'grass' => [
            'name' => 'Grass',
            'memory' => 3072, 'cpu' => 125, 'disk' => 15360,
            'databases' => 1, 'backups' => 2, 'allocations' => 1, 'price' => 39,
            'description' => 'God til en lille survival-server.',
        ],
        'stone' => [
            'name' => 'Stone',
            'memory' => 4096, 'cpu' => 150, 'disk' => 20480,
            'databases' => 2, 'backups' => 2, 'allocations' => 1, 'price' => 49,
            'description' => 'Balanceret Minecraft-pakke til survival og plugins.',
        ],
        'iron' => [
            'name' => 'Iron',
            'memory' => 6144, 'cpu' => 200, 'disk' => 30720,
            'databases' => 2, 'backups' => 3, 'allocations' => 2, 'price' => 69,
            'description' => 'Mere plads til plugins og voksende fællesskaber.',
        ],
        'gold' => [
            'name' => 'Gold',
            'memory' => 8192, 'cpu' => 250, 'disk' => 40960,
            'databases' => 3, 'backups' => 3, 'allocations' => 2, 'price' => 89,
            'description' => 'Ekstra ressourcer til større verdener og flere plugins.',
        ],
        'diamond' => [
            'name' => 'Diamond',
            'memory' => 10240, 'cpu' => 300, 'disk' => 61440,
            'databases' => 3, 'backups' => 4, 'allocations' => 2, 'price' => 119,
            'description' => 'Til krævende Minecraft-opsætninger og lettere modpacks.',
        ],
        'emerald' => [
            'name' => 'Emerald',
            'memory' => 12288, 'cpu' => 350, 'disk' => 81920,
            'databases' => 4, 'backups' => 5, 'allocations' => 3, 'price' => 149,
            'description' => 'God kapacitet til større community-servere og mods.',
        ],
        'obsidian' => [
            'name' => 'Obsidian',
            'memory' => 16384, 'cpu' => 450, 'disk' => 102400,
            'databases' => 5, 'backups' => 6, 'allocations' => 3, 'price' => 199,
            'description' => 'Den største pakke til store og krævende Minecraft-servere.',
        ],
    ];

    public function profiles(): array
    {
        return self::TIERS;
    }

    public function supportsEgg(Egg $egg): bool
    {
        return (new EggHostingPackageService())->detectCategory($egg) === 'minecraft';
    }

    /**
     * @param array<int, string> $tiers
     * @param array<string, int|float|string|null> $prices
     * @return array{created: int, skipped: int}
     */
    public function generate(Egg $egg, array $tiers, array $prices = []): array
    {
        if (!$this->supportsEgg($egg)) {
            throw new RuntimeException('Vælg et Minecraft Egg (f.eks. Paper, Spigot eller Fabric).');
        }

        if ($tiers === [] || count($tiers) > count(self::TIERS)
            || count(array_unique($tiers)) !== count($tiers)
            || array_diff($tiers, array_keys(self::TIERS))
        ) {
            throw new RuntimeException('Vælg mindst én gyldig Minecraft-pakke uden dubletter.');
        }

        // Validate ALL inputs before inserting any rows.
        $selected = [];
        foreach ($tiers as $tier) {
            $profile = self::TIERS[$tier];
            $rawPrice = $prices[$tier] ?? null;
            $price = ($rawPrice === null || $rawPrice === '')
                ? (float) $profile['price']
                : (is_numeric($rawPrice) ? (float) $rawPrice : -1);

            if (!is_finite($price) || $price < 0.01 || $price > 99999) {
                throw new RuntimeException('Ugyldig pris for Minecraft ' . $profile['name'] . '.');
            }

            $selected[$tier] = number_format($price, 2, '.', '');
        }

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($egg, $selected, &$created, &$skipped): void {
            foreach ($selected as $tier => $price) {
                $profile = self::TIERS[$tier];
                $slug = 'minecraft-egg-' . $egg->id . '-' . $tier;

                $added = DB::table('nodexa_store_products')->insertOrIgnore([
                    'name' => 'Minecraft ' . $profile['name'],
                    'slug' => $slug,
                    'game' => 'Minecraft',
                    'description' => $profile['description'] . ' '
                        . ($profile['memory'] / 1024) . ' GB RAM, '
                        . ($profile['disk'] / 1024) . ' GB SSD og '
                        . $profile['cpu'] . '% CPU. Nodexa Control Panel inkluderet.',
                    'price_monthly' => $price,
                    'egg_id' => $egg->id,
                    'memory' => $profile['memory'],
                    'cpu' => $profile['cpu'],
                    'disk' => $profile['disk'],
                    'databases' => $profile['databases'],
                    'backups' => $profile['backups'],
                    'allocations' => $profile['allocations'],
                    'enabled' => false,
                    'featured' => $tier === 'stone',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($added > 0) {
                    $created++;
                } else {
                    $skipped++;
                }
            }
        }, 3);

        return ['created' => $created, 'skipped' => $skipped];
    }
}
