<?php

namespace Pterodactyl\Services\Nodexa;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\StoreProduct;
use RuntimeException;

/**
 * Minecraft software and version choices are derived from the live Minecraft
 * Nest, not a fixed list of Egg IDs. The order stores the exact selection.
 */
class MinecraftOrderOptionsService
{
    private const VERSION_VARIABLES = [
        'MINECRAFT_VERSION', 'MC_VERSION', 'VANILLA_VERSION',
        'SERVER_VERSION', 'GAME_VERSION',
    ];

    public function isMinecraftProduct(StoreProduct $product): bool
    {
        return mb_strtolower(trim((string) $product->game)) === 'minecraft';
    }

    public function eggs()
    {
        return Egg::query()
            ->with(['nest', 'variables'])
            ->whereHas('nest', function ($query): void {
                $query->whereRaw('LOWER(name) LIKE ?', ['%minecraft%']);
            })
            ->orderBy('name')
            ->get();
    }

    public function belongsToMinecraftNest(Egg $egg): bool
    {
        $egg->loadMissing('nest');

        return str_contains(mb_strtolower((string) ($egg->nest?->name ?? '')), 'minecraft');
    }

    /**
     * Only offer a version override for Eggs exposing an unmistakably
     * Minecraft-specific installation variable. Unknown Eggs remain selectable,
     * but use the Egg's defaults rather than silently ignoring a version.
     */
    public function versionVariable(Egg $egg): ?string
    {
        $egg->loadMissing('variables');

        foreach (self::VERSION_VARIABLES as $key) {
            foreach ($egg->variables as $variable) {
                if (strtoupper((string) $variable->env_variable) === $key) {
                    return (string) $variable->env_variable;
                }
            }
        }

        foreach ($egg->variables as $variable) {
            $label = mb_strtolower((string) ($variable->name ?? ''));
            $env = strtoupper((string) $variable->env_variable);

            if (
                in_array($env, ['VERSION', 'MINECRAFT_RELEASE'], true)
                && preg_match('/minecraft|game version|server version/', $label)
            ) {
                return (string) $variable->env_variable;
            }
        }

        return null;
    }

    /**
     * Load official Minecraft Java release labels, cached to avoid per-user
     * upstream calls. A network outage still offers the Egg's latest default.
     *
     * @return string[]
     */
    public function versions(): array
    {
        try {
            $available = Cache::remember('nodexa:minecraft:release-versions:v1', now()->addHours(6), function (): array {
                $response = Http::timeout(6)->acceptJson()->get(
                    'https://piston-meta.mojang.com/mc/game/version_manifest_v2.json'
                );

                if (!$response->successful()) {
                    return [];
                }

                $versions = $response->json('versions');
                if (!is_array($versions)) {
                    return [];
                }

                $values = [];
                foreach ($versions as $item) {
                    $name = (string) ($item['id'] ?? '');
                    if (($item['type'] ?? '') === 'release' && self::validVersion($name) && $name !== 'latest') {
                        $values[] = $name;
                    }
                    if (count($values) >= 500) {
                        break;
                    }
                }

                return array_values(array_unique($values));
            });

            $fallback = ['26.3', '26.2', '26.1.2', '26.1.1', '26.1', '1.21.11', '1.21.1', '1.20.4', '1.20.1', '1.19.4', '1.18.2', '1.16.5', '1.12.2'];
            return array_values(array_unique(array_merge(
                ['latest'],
                is_array($available) && $available !== [] ? $available : $fallback
            )));
        } catch (\Throwable $exception) {
            report($exception);

            return ['latest', '26.3', '26.2', '26.1.2', '26.1.1', '26.1', '1.21.11', '1.21.1', '1.20.1', '1.19.4', '1.18.2', '1.16.5', '1.12.2'];
        }
    }

    public static function validVersion(string $version): bool
    {
        return $version === 'latest'
            || (bool) preg_match('/\A(?:1|2[6-9]|[3-9]\d)\.\d{1,2}(?:\.\d{1,2})?\z/D', $version);
    }

    /**
     * @return array{minecraft_egg_id: int, minecraft_version: ?string, minecraft_type: string}
     */
    public function selection(StoreProduct $product, int $eggId, ?string $version): array
    {
        if (!$this->isMinecraftProduct($product)) {
            throw new RuntimeException('Minecraft-valg kan kun anvendes på Minecraft-pakker.');
        }

        $egg = Egg::with(['nest', 'variables'])->find($eggId);
        if (!$egg || !$this->belongsToMinecraftNest($egg)) {
            throw new RuntimeException('Det valgte Minecraft Egg findes ikke længere i Minecraft Nest.');
        }

        $versionVariable = $this->versionVariable($egg);
        if ($versionVariable !== null) {
            $version = trim((string) $version);
            if (!self::validVersion($version)) {
                throw new RuntimeException('Vælg en gyldig Minecraft Java-version.');
            }
        } else {
            $version = null; // Proxy/unknown Eggs must not receive a fake override.
        }

        return [
            'minecraft_egg_id' => (int) $egg->id,
            'minecraft_version' => $version,
            'minecraft_type' => (string) $egg->name,
        ];
    }

    /**
     * For an ordered server, prefer a compatible runtime from those supplied
     * by the selected Egg, instead of blindly choosing the first Java image.
     */
    public function dockerImage(Egg $egg, ?string $version): ?string
    {
        $images = (array) ($egg->docker_images ?? []);
        if ($images === []) {
            return null;
        }

        $java = null;
        if ($version !== null && preg_match('/\A(1|2[6-9]|[3-9]\d)\.(\d+)(?:\.(\d+))?\z/D', $version, $parts)) {
            $major = (int) $parts[1];
            $minor = (int) $parts[2];
            $patch = (int) ($parts[3] ?? 0);
            $java = $major >= 26 ? 25
                : ($minor > 20 || ($minor === 20 && $patch >= 5) ? 21
                    : ($minor >= 18 ? 17 : ($minor === 17 ? 16 : 8)));
        }

        if ($java !== null) {
            foreach ($images as $label => $image) {
                if (preg_match('/\bjava[\s_-]*' . $java . '\b/i', (string) $label)
                    || preg_match('/java_' . $java . '(?:$|[^0-9])/i', (string) $image)
                ) {
                    return (string) $image;
                }
            }
        }

        return (string) reset($images);
    }
}
