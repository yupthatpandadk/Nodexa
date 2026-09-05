import http from '@/api/http';

export interface MinecraftModSearchResult {
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

export interface InstalledMinecraftMod {
    filename: string;
    name: string;
    size: number;
    loader: 'forge' | 'fabric';
}

const mapSearchResult = (item: any): MinecraftModSearchResult => ({
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

export const searchMinecraftMods = async (
    server: string,
    query: string,
    gameVersion: string
): Promise<{ results: MinecraftModSearchResult[]; totalHits: number; loader: 'forge' | 'fabric' }> => {
    const { data } = await http.get(`/api/client/servers/${server}/mods/search`, {
        params: { query, game_version: gameVersion || undefined },
    });

    return {
        results: (data.data || []).map(mapSearchResult),
        totalHits: Number(data.total_hits || 0),
        loader: data.loader,
    };
};

export const getInstalledMinecraftMods = async (
    server: string
): Promise<{ mods: InstalledMinecraftMod[]; loader: 'forge' | 'fabric' }> => {
    const { data } = await http.get(`/api/client/servers/${server}/mods/installed`);
    return {
        mods: (data.data || []).map((item: any) => ({
            filename: item.filename,
            name: item.name,
            size: Number(item.size || 0),
            loader: item.loader,
        })),
        loader: data.loader,
    };
};

export const installMinecraftMod = async (
    server: string,
    projectId: string,
    gameVersion: string
): Promise<string> => {
    const { data } = await http.post(`/api/client/servers/${server}/mods/install`, {
        project_id: projectId,
        game_version: gameVersion || undefined,
    });
    return data.message;
};

export const uninstallMinecraftMod = async (server: string, filename: string): Promise<string> => {
    const { data } = await http.delete(`/api/client/servers/${server}/mods/${encodeURIComponent(filename)}`);
    return data.message;
};
