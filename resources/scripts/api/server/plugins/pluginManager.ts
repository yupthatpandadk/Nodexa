import axios from 'axios';
import http from '@/api/http';
import loadDirectory, { FileObject } from '@/api/server/files/loadDirectory';
import deleteFiles from '@/api/server/files/deleteFiles';

export interface ModrinthPlugin {
    project_id: string;
    slug: string;
    title: string;
    description: string;
    author: string;
    downloads: number;
    icon_url: string | null;
    versions: string[];
    categories: string[];
}

export interface ModrinthVersionFile {
    url: string;
    filename: string;
    primary: boolean;
}

export interface ModrinthVersion {
    id: string;
    project_id: string;
    version_number: string;
    game_versions: string[];
    loaders: string[];
    version_type: 'release' | 'beta' | 'alpha';
    files: ModrinthVersionFile[];
}

const modrinth = axios.create({
    baseURL: 'https://api.modrinth.com/v2',
    timeout: 15000,
    headers: { Accept: 'application/json' },
});

export const searchPlugins = async (query: string, minecraftVersion?: string, loader?: string): Promise<ModrinthPlugin[]> => {
    const facets: string[][] = [['project_type:plugin']];
    if (minecraftVersion) facets.push([`versions:${minecraftVersion}`]);
    if (loader) facets.push([`categories:${loader}`]);

    const { data } = await modrinth.get('/search', {
        params: { query, facets: JSON.stringify(facets), limit: 24, index: query ? 'relevance' : 'downloads' },
    });
    return data.hits || [];
};

export const getPluginVersions = async (
    projectId: string,
    minecraftVersion?: string,
    loader?: string
): Promise<ModrinthVersion[]> => {
    const params: Record<string, string> = {};
    if (minecraftVersion) params.game_versions = JSON.stringify([minecraftVersion]);
    if (loader) params.loaders = JSON.stringify([loader]);

    const { data } = await modrinth.get(`/project/${projectId}/version`, { params });
    return (data || []).filter((version: ModrinthVersion) =>
        version.files?.length &&
        version.loaders?.some((value) => ['paper', 'spigot', 'purpur', 'bukkit', 'folia'].includes(value.toLowerCase()))
    );
};

const chooseVersion = (versions: ModrinthVersion[]): ModrinthVersion | undefined =>
    versions.find((item) => item.version_type === 'release') || versions[0];

export const installPlugin = async (
    serverId: string,
    projectId: string,
    minecraftVersion?: string,
    loader?: string
): Promise<{ filename: string; version: string }> => {
    const version = chooseVersion(await getPluginVersions(projectId, minecraftVersion, loader));
    if (!version) throw new Error('Der blev ikke fundet en kompatibel plugin-version til denne Minecraft-server.');

    const file = version.files.find((item) => item.primary) || version.files[0];
    if (!file || !file.filename.toLowerCase().endsWith('.jar')) throw new Error('Plugin-versionen indeholder ikke en gyldig JAR-fil.');

    await http.post(`/api/client/servers/${serverId}/files/pull`, {
        url: file.url,
        directory: '/plugins',
        filename: file.filename,
        foreground: true,
    });
    return { filename: file.filename, version: version.version_number };
};

export const installedPlugins = async (serverId: string): Promise<FileObject[]> => {
    try {
        return (await loadDirectory(serverId, '/plugins')).filter((file) => file.isFile && file.name.toLowerCase().endsWith('.jar'));
    } catch {
        return [];
    }
};

export const uninstallPlugin = (serverId: string, filename: string): Promise<void> =>
    deleteFiles(serverId, '/plugins', [filename]);

export const updatePlugin = async (
    serverId: string,
    projectId: string,
    oldFilename: string,
    minecraftVersion?: string,
    loader?: string
): Promise<{ filename: string; version: string }> => {
    const result = await installPlugin(serverId, projectId, minecraftVersion, loader);
    if (result.filename !== oldFilename) {
        await uninstallPlugin(serverId, oldFilename);
    }
    return result;
};
