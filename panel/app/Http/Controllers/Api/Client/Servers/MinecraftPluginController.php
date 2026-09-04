<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Throwable;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Pterodactyl\Facades\Activity;
use Pterodactyl\Models\Permission;
use Pterodactyl\Models\Server;
use Pterodactyl\Services\Nodexa\AddonManager;
use Pterodactyl\Repositories\Wings\DaemonFileRepository;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;

class MinecraftPluginController extends ClientApiController
{
    private const MODRINTH_BASE = 'https://api.modrinth.com/v2';

    private const LOADERS = [
        'paper', 'purpur', 'spigot', 'bukkit', 'folia',
        'velocity', 'waterfall', 'bungeecord',
    ];

    public function __construct(
        private DaemonFileRepository $files,
        private AddonManager $addons,
    ) {
        parent::__construct();
    }

    public function search(Request $request, Server $server): JsonResponse
    {
        $this->guard($request, $server);
        $this->guardMinecraft($server);

        $data = $request->validate([
            'query' => 'nullable|string|max:80',
            'game_version' => 'nullable|string|max:32',
            'loader' => 'nullable|string|in:' . implode(',', self::LOADERS),
            'offset' => 'nullable|integer|min:0|max:1000',
        ]);

        $loader = $data['loader'] ?? $this->detectLoader($server);
        $facets = [['all_project_types:plugin']];

        if (!empty($data['game_version'])) {
            $facets[] = ['versions:' . $data['game_version']];
        }

        $loaderFacets = array_map(fn (string $value) => 'categories:' . $value, $this->compatibleLoaders($loader));
        if ($loaderFacets !== []) {
            $facets[] = $loaderFacets;
        }

        $response = $this->modrinth()->get('/search', [
            'query' => trim((string) ($data['query'] ?? '')),
            'facets' => json_encode($facets, JSON_UNESCAPED_SLASHES),
            'index' => 'relevance',
            'offset' => (int) ($data['offset'] ?? 0),
            'limit' => 24,
        ]);

        if (!$response->successful()) {
            return response()->json(['message' => 'Plugin-kataloget kunne ikke kontaktes lige nu.'], 502);
        }

        $payload = $response->json();
        $hits = collect($payload['hits'] ?? [])
            ->filter(fn (array $hit) => in_array('plugin', $hit['all_project_types'] ?? [], true))
            ->map(fn (array $hit) => [
                'project_id' => $hit['project_id'] ?? '',
                'slug' => $hit['slug'] ?? null,
                'title' => $hit['title'] ?? 'Ukendt plugin',
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
        $this->guardMinecraft($server);

        $managed = DB::table('nodexa_minecraft_plugins')
            ->where('server_id', $server->id)
            ->orderBy('name')
            ->get()
            ->keyBy('filename');

        $actual = [];
        try {
            foreach ($this->files->setServer($server)->getDirectory('/plugins') as $file) {
                $name = (string) ($file['name'] ?? '');
                if (($file['is_file'] ?? false) && Str::endsWith(Str::lower($name), '.jar')) {
                    $entry = $managed->get($name);
                    $actual[] = [
                        'filename' => $name,
                        'size' => (int) ($file['size'] ?? 0),
                        'managed' => $entry !== null,
                        'project_id' => $entry?->project_id,
                        'slug' => $entry?->slug,
                        'name' => $entry?->name ?? preg_replace('/\.jar$/i', '', $name),
                        'version_id' => $entry?->version_id,
                        'version_number' => $entry?->version_number,
                        'loader' => $entry?->loader,
                        'game_version' => $entry?->game_version,
                    ];
                }
            }
        } catch (Throwable) {
            // A brand new plugin server may not have created /plugins yet.
        }

        return response()->json(['data' => $actual]);
    }

    public function install(Request $request, Server $server): JsonResponse
    {
        $this->guard($request, $server);
        $this->guardMinecraft($server);

        $data = $request->validate([
            'project_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]+$/'],
            'game_version' => 'nullable|string|max:32',
            'loader' => 'nullable|string|in:' . implode(',', self::LOADERS),
        ]);

        $projectId = $data['project_id'];
        $loader = $data['loader'] ?? $this->detectLoader($server);
        $loaders = $this->compatibleLoaders($loader);

        $query = [
            'loaders' => json_encode($loaders, JSON_UNESCAPED_SLASHES),
            'include_changelog' => 'false',
        ];
        if (!empty($data['game_version'])) {
            $query['game_versions'] = json_encode([$data['game_version']], JSON_UNESCAPED_SLASHES);
        }

        $versionsResponse = $this->modrinth()->get('/project/' . rawurlencode($projectId) . '/version', $query);
        if (!$versionsResponse->successful()) {
            return response()->json(['message' => 'Plugin-versioner kunne ikke hentes fra kataloget.'], 502);
        }

        $versions = collect($versionsResponse->json() ?: []);
        if ($versions->isEmpty()) {
            return response()->json([
                'message' => 'Der blev ikke fundet en kompatibel version til den valgte Minecraft-version og loader.',
            ], 422);
        }

        $version = $versions->first(fn (array $item) => ($item['version_type'] ?? null) === 'release') ?? $versions->first();
        $jar = collect($version['files'] ?? [])->first(fn (array $file) => ($file['primary'] ?? false) && Str::endsWith(Str::lower((string) ($file['filename'] ?? '')), '.jar'))
            ?? collect($version['files'] ?? [])->first(fn (array $file) => Str::endsWith(Str::lower((string) ($file['filename'] ?? '')), '.jar'));

        if (!$jar || empty($jar['url'])) {
            return response()->json(['message' => 'Den valgte plugin-version indeholder ingen installérbar JAR-fil.'], 422);
        }

        $url = (string) $jar['url'];
        $host = Str::lower((string) parse_url($url, PHP_URL_HOST));
        if ($host !== 'cdn.modrinth.com') {
            return response()->json(['message' => 'Plugin-filen kom fra et uventet download-domæne og blev blokeret.'], 422);
        }

        $projectResponse = $this->modrinth()->get('/project/' . rawurlencode($projectId));
        $project = $projectResponse->successful() ? ($projectResponse->json() ?: []) : [];
        $name = (string) ($project['title'] ?? $version['name'] ?? 'Minecraft Plugin');
        $slug = $project['slug'] ?? null;

        $filename = basename((string) ($jar['filename'] ?? 'plugin.jar'));
        if (!preg_match('/^[A-Za-z0-9._+()\- ]+\.jar$/i', $filename)) {
            $filename = Str::slug($name) . '-' . Str::slug((string) ($version['version_number'] ?? 'latest')) . '.jar';
        }

        $repository = $this->files->setServer($server);
        try {
            $repository->getDirectory('/plugins');
        } catch (Throwable) {
            $repository->createDirectory('plugins', '/');
        }

        $existing = DB::table('nodexa_minecraft_plugins')
            ->where('server_id', $server->id)
            ->where('project_id', $projectId)
            ->first();

        $repository->pull($url, '/plugins', [
            'filename' => $filename,
            'use_header' => false,
            'foreground' => true,
        ]);

        if ($existing && $existing->filename !== $filename) {
            try {
                $repository->deleteFiles('/plugins', [$existing->filename]);
            } catch (Throwable) {
                // The old file may already have been removed manually.
            }
        }

        DB::table('nodexa_minecraft_plugins')->updateOrInsert(
            ['server_id' => $server->id, 'project_id' => $projectId],
            [
                'slug' => $slug,
                'name' => $name,
                'version_id' => (string) ($version['id'] ?? ''),
                'version_number' => (string) ($version['version_number'] ?? ''),
                'filename' => $filename,
                'loader' => $loader,
                'game_version' => $data['game_version'] ?? null,
                'updated_at' => now(),
                'created_at' => $existing?->created_at ?? now(),
            ]
        );

        Activity::event('server:plugin.install')
            ->property('plugin', $name)
            ->property('version', $version['version_number'] ?? null)
            ->property('filename', $filename)
            ->log();

        return response()->json([
            'message' => sprintf('%s blev installeret i /plugins. Genstart serveren for at indlæse pluginet.', $name),
            'plugin' => [
                'project_id' => $projectId,
                'name' => $name,
                'version_number' => $version['version_number'] ?? null,
                'filename' => $filename,
            ],
        ]);
    }

    public function uninstall(Request $request, Server $server, string $projectId): JsonResponse
    {
        $this->guard($request, $server);
        $this->guardMinecraft($server);

        $entry = DB::table('nodexa_minecraft_plugins')
            ->where('server_id', $server->id)
            ->where('project_id', $projectId)
            ->first();

        if (!$entry) {
            return response()->json(['message' => 'Pluginet er ikke registreret som installeret af Nodexa.'], 404);
        }

        $this->files->setServer($server)->deleteFiles('/plugins', [$entry->filename]);
        DB::table('nodexa_minecraft_plugins')->where('id', $entry->id)->delete();

        Activity::event('server:plugin.uninstall')
            ->property('plugin', $entry->name)
            ->property('filename', $entry->filename)
            ->log();

        return response()->json(['message' => $entry->name . ' blev fjernet. Genstart serveren for at fuldføre ændringen.']);
    }

    private function modrinth()
    {
        return Http::baseUrl(self::MODRINTH_BASE)
            ->acceptJson()
            ->withHeaders(['User-Agent' => 'Nodexa/0.14.45 (https://github.com/yupthatpandadk/Nodexa)'])
            ->connectTimeout(5)
            ->timeout(15)
            ->retry(2, 250);
    }

    private function guard(Request $request, Server $server): void
    {
        if (!isset($this->addons->enabled()['minecraft-plugin-manager'])) {
            abort(404, 'Minecraft Plugin Manager addon er ikke installeret eller aktiveret.');
        }

        if (!$request->user()?->can(Permission::ACTION_FILE_CREATE, $server)) {
            abort(403, 'Du har ikke tilladelse til at installere plugins på denne server.');
        }
    }

    private function guardMinecraft(Server $server): void
    {
        $egg = Str::lower((string) $server->egg->name);
        if (!preg_match('/minecraft|paper|purpur|spigot|bukkit|folia|velocity|waterfall|bungee/', $egg)) {
            abort(422, 'Plugin Manager er kun til Minecraft plugin-servere.');
        }
    }

    private function detectLoader(Server $server): string
    {
        $egg = Str::lower((string) $server->egg->name);
        foreach (['folia', 'purpur', 'paper', 'spigot', 'bukkit', 'velocity', 'waterfall', 'bungeecord'] as $loader) {
            if (Str::contains($egg, $loader)) {
                return $loader;
            }
        }

        return 'paper';
    }

    private function compatibleLoaders(string $loader): array
    {
        return match ($loader) {
            'purpur' => ['purpur', 'paper', 'spigot', 'bukkit'],
            'paper' => ['paper', 'spigot', 'bukkit'],
            'spigot' => ['spigot', 'bukkit'],
            'bukkit' => ['bukkit'],
            'folia' => ['folia'],
            'velocity' => ['velocity'],
            'waterfall' => ['waterfall', 'bungeecord'],
            'bungeecord' => ['bungeecord'],
            default => ['paper', 'spigot', 'bukkit'],
        };
    }
}
