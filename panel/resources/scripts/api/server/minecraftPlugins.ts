import http from '@/api/http';

export interface MinecraftPluginSearchResult {
    projectId: string;
    slug: string | null;
    title: string;
    description: string;
    author: string;
    iconUrl: string | null;
    downloads: number;
    versions: string[];
    categories: string[];
}

export interface InstalledMinecraftPlugin {
    filename: string;
    size: number;
    managed: boolean;
    projectId: string | null;
    slug: string | null;
    name: string;
    versionId: string | null;
    versionNumber: string | null;
    loader: string | null;
    gameVersion: string | null;
}

const mapSearchResult = (item: any): MinecraftPluginSearchResult => ({
    projectId: item.project_id,
    slug: item.slug || null,
    title: item.title,
    description: item.description || '',
    author: item.author || 'Ukendt',
    iconUrl: item.icon_url || null,
    downloads: Number(item.downloads || 0),
    versions: item.versions || [],
    categories: item.categories || [],
});

const mapInstalled = (item: any): InstalledMinecraftPlugin => ({
    filename: item.filename,
    size: Number(item.size || 0),
    managed: Boolean(item.managed),
    projectId: item.project_id || null,
    slug: item.slug || null,
    name: item.name || String(item.filename || '').replace(/\.jar$/i, ''),
    versionId: item.version_id || null,
    versionNumber: item.version_number || null,
    loader: item.loader || null,
    gameVersion: item.game_version || null,
});

const mapPluginFile = (item: any): InstalledMinecraftPlugin | null => {
    const attributes = item?.attributes || item || {};
    const filename = String(attributes.name || '');

    if (!filename || attributes.is_file === false || !/\.jar$/i.test(filename)) return null;

    return {
        filename,
        size: Number(attributes.size || 0),
        managed: false,
        projectId: null,
        slug: null,
        name: filename.replace(/\.jar$/i, ''),
        versionId: null,
        versionNumber: null,
        loader: null,
        gameVersion: null,
    };
};

export const searchMinecraftPlugins = async (
    server: string,
    query: string,
    gameVersion: string,
    loader: string
): Promise<{ results: MinecraftPluginSearchResult[]; totalHits: number }> => {
    const { data } = await http.get(`/api/client/servers/${server}/plugins/search`, {
        params: {
            query,
            game_version: gameVersion || undefined,
            loader,
        },
    });

    return {
        results: (data.data || []).map(mapSearchResult),
        totalHits: Number(data.total_hits || 0),
    };
};

export const getInstalledMinecraftPlugins = async (server: string): Promise<InstalledMinecraftPlugin[]> => {
    // Read the actual /plugins directory from Wings/Pterodactyl. This makes manually
    // uploaded JARs visible too, instead of relying only on Nodexa's managed metadata.
    const { data: filesResponse } = await http.get(`/api/client/servers/${server}/files/list`, {
        params: { directory: '/plugins' },
    });

    const files = (filesResponse.data || [])
        .map(mapPluginFile)
        .filter((plugin: InstalledMinecraftPlugin | null): plugin is InstalledMinecraftPlugin => plugin !== null);

    // Enrich files installed through Nodexa with Modrinth/managed metadata when that
    // endpoint is available. A failure here must never hide real JAR files.
    let managed: InstalledMinecraftPlugin[] = [];
    try {
        const { data } = await http.get(`/api/client/servers/${server}/plugins/installed`);
        managed = (data.data || []).map(mapInstalled);
    } catch (_) {
        managed = [];
    }

    const managedByFilename = new Map(managed.map((plugin) => [plugin.filename.toLowerCase(), plugin]));

    return files
        .map((file) => managedByFilename.get(file.filename.toLowerCase()) || file)
        .sort((a, b) => a.name.localeCompare(b.name, undefined, { sensitivity: 'base' }));
};

export const installMinecraftPlugin = async (
    server: string,
    projectId: string,
    gameVersion: string,
    loader: string
): Promise<string> => {
    const { data } = await http.post(`/api/client/servers/${server}/plugins/install`, {
        project_id: projectId,
        game_version: gameVersion || undefined,
        loader,
    });

    return data.message;
};

export const uninstallMinecraftPlugin = async (server: string, projectId: string): Promise<string> => {
    const { data } = await http.delete(`/api/client/servers/${server}/plugins/${projectId}`);
    return data.message;
};
