<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Throwable;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\Permission;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Nodexa\AddonManager;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;

class MinecraftModController extends ClientApiController
{
    private const MODRINTH_BASE = 'https://api.modrinth.com/v2';

    public function __construct(
        private DaemonFileRepository $files,
        private AddonManager $addons,
    ) {
        parent::__construct();
    }

    public function search(Request $request, Server $server): JsonResponse
    {
        $this->guard($request, $server);
        $loader = $this->detectLoader($server);
        $data = $request->validate([
            'query' => 'nullable|string|max:80',
            'game_version' => 'nullable|string|max:32',
            'offset' => 'nullable|integer|min:0|max:1000',
        ]);

        $facets = [
            ['project_type:mod'],
            ['categories:' . $loader],
        ];
        if (!empty($data['game_version'])) {
            $facets[] = ['versions:' . $data['game_version']];
        }

        $query = trim((string) ($data['query'] ?? ''));
        $response = $this->modrinth()->get('/search', [
            'query' => $query,
            'facets' => json_encode($facets, JSON_UNESCAPED_SLASHES),
            'index' => $query === '' ? 'downloads' : 'relevance',
            'offset' => (int) ($data['offset'] ?? 0),
            'limit' => 24,
        ]);

        if (!$response->successful()) {
            return response()->json(['message' => 'Mod-kataloget kunne ikke kontaktes lige nu.'], 502);
        }

        $payload = $response->json();
        $hits = collect($payload['hits'] ?? [])
            ->filter(function (array $hit) use ($loader) {
                $categories = array_map('strtolower', $hit['categories'] ?? []);
                return ($hit['project_type'] ?? null) === 'mod' && in_array($loader, $categories, true);
            })
            ->map(fn (array $hit) => [
                'project_id' => $hit['project_id'] ?? '',
                'slug' => $hit['slug'] ?? null,
                'title' => $hit['title'] ?? 'Ukendt mod',
                'description' => $hit['description'] ?? '',
                'author' => $hit['author'] ?? 'Ukendt',
                'icon_url' => $hit['icon_url'] ?? null,
                'downloads' => (int) ($hit['downloads'] ?? 0),
                'versions' => array_values($hit['versions'] ?? []),
                'categories' => array_values($hit['categories'] ?? []),
            ])
            ->values();

        return response()->json([
            'data' => $hits,
            'total_hits' => (int) ($payload['total_hits'] ?? $hits->count()),
            'loader' => $loader,
            'game_version' => $data['game_version'] ?? null,
            'source' => 'Modrinth',
        ]);
    }

    public function installed(Request $request, Server $server): JsonResponse
    {
        $this->guard($request, $server);
        $loader = $this->detectLoader($server);
        $mods = [];

        try {
            foreach ($this->files->setServer($server)->getDirectory('/mods') as $file) {
                $name = (string) ($file['name'] ?? '');
                if (($file['is_file'] ?? false) && Str::endsWith(Str::lower($name), '.jar')) {
                    $mods[] = [
                        'filename' => $name,
                        'name' => preg_replace('/\.jar$/i', '', $name),
                        'size' => (int) ($file['size'] ?? 0),
                        'loader' => $loader,
                    ];
                }
            }
        } catch (Throwable) {
            // /mods is normally created on first launch or install.
        }

        usort($mods, fn (array $a, array $b) => strcasecmp($a['name'], $b['name']));
        return response()->json(['data' => $mods, 'loader' => $loader]);
    }

    public function install(Request $request, Server $server): JsonResponse
    {
        $this->guard($request, $server);
        $loader = $this->detectLoader($server);
        $data = $request->validate([
            'project_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'game_version' => 'nullable|string|max:32',
        ]);

        $query = [
            'loaders' => json_encode([$loader], JSON_UNESCAPED_SLASHES),
            'include_changelog' => 'false',
        ];
        if (!empty($data['game_version'])) {
            $query['game_versions'] = json_encode([$data['game_version']], JSON_UNESCAPED_SLASHES);
        }

        $projectId = $data['project_id'];
        $versionsResponse = $this->modrinth()->get('/project/' . rawurlencode($projectId) . '/version', $query);
        if (!$versionsResponse->successful()) {
            return response()->json(['message' => 'Mod-versioner kunne ikke hentes fra kataloget.'], 502);
        }

        $versions = collect($versionsResponse->json() ?: [])
            ->filter(fn (array $item) => in_array($loader, array_map('strtolower', $item['loaders'] ?? []), true));

        if (!empty($data['game_version'])) {
            $versions = $versions->filter(fn (array $item) => in_array($data['game_version'], $item['game_versions'] ?? [], true));
        }

        if ($versions->isEmpty()) {
            return response()->json([
                'message' => sprintf('Der findes ingen kompatibel %s-version af dette mod%s.', ucfirst($loader), !empty($data['game_version']) ? ' til Minecraft ' . $data['game_version'] : ''),
            ], 422);
        }

        $version = $versions->first(fn (array $item) => ($item['version_type'] ?? null) === 'release') ?? $versions->first();
        $jar = collect($version['files'] ?? [])->first(fn (array $file) => ($file['primary'] ?? false) && Str::endsWith(Str::lower((string) ($file['filename'] ?? '')), '.jar'))
            ?? collect($version['files'] ?? [])->first(fn (array $file) => Str::endsWith(Str::lower((string) ($file['filename'] ?? '')), '.jar'));

        if (!$jar || empty($jar['url'])) {
            return response()->json(['message' => 'Den valgte mod-version indeholder ingen installérbar JAR-fil.'], 422);
        }

        $url = (string) $jar['url'];
        if (Str::lower((string) parse_url($url, PHP_URL_HOST)) !== 'cdn.modrinth.com') {
            return response()->json(['message' => 'Mod-filen kom fra et uventet download-domæne og blev blokeret.'], 422);
        }

        $projectResponse = $this->modrinth()->get('/project/' . rawurlencode($projectId));
        $project = $projectResponse->successful() ? ($projectResponse->json() ?: []) : [];
        $projectLoaders = array_map('strtolower', $project['loaders'] ?? []);
        if ($projectLoaders !== [] && !in_array($loader, $projectLoaders, true)) {
            return response()->json(['message' => 'Dette mod understøtter ikke serverens loader.'], 422);
        }

        $name = (string) ($project['title'] ?? $version['name'] ?? 'Minecraft Mod');
        $filename = basename((string) ($jar['filename'] ?? 'mod.jar'));
        if (!preg_match('/^[A-Za-z0-9._+()\- ]+\.jar$/i', $filename)) {
            $filename = Str::slug($name) . '-' . Str::slug((string) ($version['version_number'] ?? 'latest')) . '.jar';
        }

        $repository = $this->files->setServer($server);
        try {
            $repository->getDirectory('/mods');
        } catch (Throwable) {
            $repository->createDirectory('mods', '/');
        }

        $repository->pull($url, '/mods', [
            'filename' => $filename,
            'use_header' => false,
            'foreground' => true,
        ]);

        Activity::event('server:mod.install')
            ->property('mod', $name)
            ->property('loader', $loader)
            ->property('version', $version['version_number'] ?? null)
            ->property('filename', $filename)
            ->log();

        return response()->json([
            'message' => sprintf('%s blev installeret i /mods til %s. Genstart serveren for at indlæse moddet.', $name, ucfirst($loader)),
            'mod' => [
                'project_id' => $projectId,
                'name' => $name,
                'version_number' => $version['version_number'] ?? null,
                'filename' => $filename,
                'loader' => $loader,
            ],
        ]);
    }

    public function uninstall(Request $request, Server $server, string $filename): JsonResponse
    {
        $this->guard($request, $server);
        $loader = $this->detectLoader($server);
        $filename = basename(rawurldecode($filename));

        if (!preg_match('/^[A-Za-z0-9._+()\- ]+\.jar$/i', $filename)) {
            return response()->json(['message' => 'Ugyldigt mod-filnavn.'], 422);
        }

        $this->files->setServer($server)->deleteFiles('/mods', [$filename]);

        Activity::event('server:mod.uninstall')
            ->property('filename', $filename)
            ->property('loader', $loader)
            ->log();

        return response()->json(['message' => $filename . ' blev fjernet. Genstart serveren for at fuldføre ændringen.']);
    }

    private function modrinth()
    {
        return Http::baseUrl(self::MODRINTH_BASE)
            ->acceptJson()
            ->withHeaders(['User-Agent' => 'Nodexa/0.14.51 (https://github.com/yupthatpandadk/Nodexa)'])
            ->connectTimeout(5)
            ->timeout(15)
            ->retry(2, 250);
    }

    private function guard(Request $request, Server $server): void
    {
        if (!isset($this->addons->enabled()['minecraft-mod-manager'])) {
            abort(404, 'Minecraft Mod Manager addon er ikke installeret eller aktiveret.');
        }

        if (!$request->user()?->can(Permission::ACTION_FILE_CREATE, $server)) {
            abort(403, 'Du har ikke tilladelse til at administrere mods på denne server.');
        }

        $this->detectLoader($server);
    }

    private function detectLoader(Server $server): string
    {
        $egg = Str::lower((string) $server->egg->name);

        if (Str::contains($egg, 'fabric')) {
            return 'fabric';
        }
        if (Str::contains($egg, 'forge')) {
            return 'forge';
        }

        abort(422, 'Mod Manager understøtter kun Forge- og Fabric-servere.');
    }
}
