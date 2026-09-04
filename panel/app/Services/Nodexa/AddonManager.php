<?php

namespace Pterodactyl\Services\Nodexa;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class AddonManager
{
    public function catalogPath(): string
    {
        return (string) config('addons.catalog_path', base_path('addons'));
    }

    public function currentVersion(): string
    {
        $versionFile = '/var/lib/nodexa/version.json';
        if (is_readable($versionFile)) {
            $decoded = json_decode((string) file_get_contents($versionFile), true);
            if (is_array($decoded) && !empty($decoded['version'])) {
                return ltrim((string) $decoded['version'], 'vV');
            }
        }

        $sourceVersion = dirname(base_path()) . '/VERSION';
        if (is_readable($sourceVersion)) {
            return ltrim(trim((string) file_get_contents($sourceVersion)), 'vV');
        }

        return '0.0.0';
    }

    public function all(): array
    {
        $states = $this->states();
        $addons = [];
        $catalog = $this->catalogPath();

        if (!is_dir($catalog)) {
            return [];
        }

        foreach (glob($catalog . '/*/addon.json') ?: [] as $manifestPath) {
            $manifest = $this->readManifest($manifestPath);
            if ($manifest === null) {
                continue;
            }

            $slug = $manifest['id'];
            $state = $states[$slug] ?? null;
            $manifest['_path'] = dirname($manifestPath);
            $manifest['installed'] = $state !== null;
            $manifest['enabled'] = (bool) ($state['enabled'] ?? false);
            $manifest['installed_version'] = $state['version'] ?? null;
            $manifest['compatible'] = $this->isCompatible($manifest);
            $manifest['update_available'] = $state !== null && version_compare((string) $manifest['version'], (string) $state['version'], '>');
            $addons[$slug] = $manifest;
        }

        uasort($addons, fn (array $a, array $b) => strcasecmp((string) $a['name'], (string) $b['name']));

        return $addons;
    }

    public function enabled(): array
    {
        return array_filter($this->all(), fn (array $addon) => $addon['installed'] && $addon['enabled'] && $addon['compatible']);
    }

    public function adminStylesheets(): array
    {
        $assets = [];
        foreach ($this->enabled() as $addon) {
            foreach ((array) ($addon['assets']['admin_css'] ?? []) as $asset) {
                $asset = ltrim((string) $asset, '/');
                if ($asset !== '' && !str_contains($asset, '..')) {
                    $assets[] = '/nodexa-addons/' . rawurlencode($addon['id']) . '/' . str_replace('%2F', '/', rawurlencode($asset));
                }
            }
        }

        return $assets;
    }

    public function adminScripts(): array
    {
        $assets = [];
        foreach ($this->enabled() as $addon) {
            foreach ((array) ($addon['assets']['admin_js'] ?? []) as $asset) {
                $asset = ltrim((string) $asset, '/');
                if ($asset !== '' && !str_contains($asset, '..')) {
                    $assets[] = '/nodexa-addons/' . rawurlencode($addon['id']) . '/' . str_replace('%2F', '/', rawurlencode($asset));
                }
            }
        }

        return $assets;
    }

    public function install(string $slug): array
    {
        $addon = $this->find($slug);
        if (!$addon) {
            throw new RuntimeException('Addon-pakken blev ikke fundet.');
        }

        if (!$this->isCompatible($addon)) {
            throw new RuntimeException(sprintf('Addon kræver Nodexa %s eller nyere.', $addon['min_nodexa'] ?? 'ukendt'));
        }

        if (!Schema::hasTable('nodexa_addons')) {
            throw new RuntimeException('Addon-databasen er ikke installeret endnu. Kør Nodexa-opdateringen igen.');
        }

        $now = now();
        DB::table('nodexa_addons')->updateOrInsert(
            ['slug' => $slug],
            [
                'version' => (string) $addon['version'],
                'enabled' => true,
                'installed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $this->publishAssets($addon);

        return $addon;
    }

    public function setEnabled(string $slug, bool $enabled): void
    {
        $addon = $this->find($slug);
        if (!$addon) {
            throw new RuntimeException('Addon-pakken blev ikke fundet.');
        }

        if (!$this->isInstalled($slug)) {
            throw new RuntimeException('Addon er ikke installeret.');
        }

        if ($enabled && !$this->isCompatible($addon)) {
            throw new RuntimeException('Addon er ikke kompatibelt med denne Nodexa-version.');
        }

        if ($enabled) {
            $this->publishAssets($addon);
        }

        DB::table('nodexa_addons')->where('slug', $slug)->update([
            'enabled' => $enabled,
            'updated_at' => now(),
        ]);
    }

    public function uninstall(string $slug): void
    {
        if (Schema::hasTable('nodexa_addons')) {
            DB::table('nodexa_addons')->where('slug', $slug)->delete();
        }

        File::deleteDirectory(public_path('nodexa-addons/' . $slug));
    }

    public function find(string $slug): ?array
    {
        $slug = strtolower(trim($slug));
        if (!preg_match('/^[a-z0-9][a-z0-9_-]{1,79}$/', $slug)) {
            return null;
        }

        $manifestPath = $this->catalogPath() . '/' . $slug . '/addon.json';
        $manifest = $this->readManifest($manifestPath);
        if ($manifest === null || $manifest['id'] !== $slug) {
            return null;
        }

        $manifest['_path'] = dirname($manifestPath);

        return $manifest;
    }

    public function isInstalled(string $slug): bool
    {
        return isset($this->states()[$slug]);
    }

    private function states(): array
    {
        try {
            if (!Schema::hasTable('nodexa_addons')) {
                return [];
            }

            return DB::table('nodexa_addons')
                ->get()
                ->mapWithKeys(fn ($row) => [$row->slug => [
                    'version' => $row->version,
                    'enabled' => (bool) $row->enabled,
                    'installed_at' => $row->installed_at,
                ]])
                ->all();
        } catch (\Throwable) {
            return [];
        }
    }

    private function readManifest(string $path): ?array
    {
        if (!is_readable($path)) {
            return null;
        }

        $manifest = json_decode((string) file_get_contents($path), true);
        if (!is_array($manifest)) {
            return null;
        }

        $id = strtolower(trim((string) ($manifest['id'] ?? '')));
        $name = trim((string) ($manifest['name'] ?? ''));
        $version = trim((string) ($manifest['version'] ?? ''));

        if (!preg_match('/^[a-z0-9][a-z0-9_-]{1,79}$/', $id) || $name === '' || $version === '') {
            return null;
        }

        $manifest['id'] = $id;
        $manifest['name'] = $name;
        $manifest['version'] = $version;
        $manifest['description'] = trim((string) ($manifest['description'] ?? ''));
        $manifest['author'] = trim((string) ($manifest['author'] ?? 'Ukendt'));
        $manifest['category'] = trim((string) ($manifest['category'] ?? 'Andet'));
        $manifest['icon'] = preg_match('/^fa-[a-z0-9-]+$/', (string) ($manifest['icon'] ?? '')) ? $manifest['icon'] : 'fa-puzzle-piece';
        $manifest['min_nodexa'] = ltrim(trim((string) ($manifest['min_nodexa'] ?? '0.0.0')), 'vV');
        $manifest['assets'] = is_array($manifest['assets'] ?? null) ? $manifest['assets'] : [];

        return $manifest;
    }

    private function isCompatible(array $addon): bool
    {
        return version_compare($this->currentVersion(), (string) ($addon['min_nodexa'] ?? '0.0.0'), '>=');
    }

    private function publishAssets(array $addon): void
    {
        $source = $addon['_path'] . '/public';
        if (!is_dir($source)) {
            return;
        }

        $destination = public_path('nodexa-addons/' . $addon['id']);
        File::ensureDirectoryExists($destination, 0755, true);
        File::copyDirectory($source, $destination);
    }
}
