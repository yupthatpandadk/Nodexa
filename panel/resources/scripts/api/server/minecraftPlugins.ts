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
    name: item.name,
    versionId: item.version_id || null,
    versionNumber: item.version_number || null,
    loader: item.loader || null,
    gameVersion: item.game_version || null,
});

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
    const { data } = await http.get(`/api/client/servers/${server}/plugins/installed`);
    return (data.data || []).map(mapInstalled);
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
