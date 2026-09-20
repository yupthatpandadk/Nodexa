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

interface ModrinthVersionFile {
    url: string;
    filename: string;
    primary: boolean;
}

interface ModrinthVersion {
    id: string;
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

export const searchPlugins = async (query: string): Promise<ModrinthPlugin[]> => {
    const facets = JSON.stringify([
        ['all_project_types:plugin'],
        ['environment:server_only', 'environment:server_only_client_optional', 'environment:client_and_server', 'environment:client_or_server'],
    ]);
    const { data } = await modrinth.get('/search', {
        params: { query, facets, limit: 24, index: query ? 'relevance' : 'downloads' },
    });
    return data.hits || [];
};

export const getPluginVersions = async (projectId: string): Promise<ModrinthVersion[]> => {
    const { data } = await modrinth.get(`/project/${projectId}/version`, {
        params: { include_changelog: false },
    });
    return (data || []).filter((version: ModrinthVersion) =>
        version.files?.length && version.loaders?.some((loader) => ['paper', 'spigot', 'purpur', 'bukkit', 'folia'].includes(loader.toLowerCase()))
    );
};

export const installPlugin = async (serverId: string, projectId: string): Promise<string> => {
    const versions = await getPluginVersions(projectId);
    const version = versions.find((item) => item.version_type === 'release') || versions[0];
    if (!version) throw new Error('Der blev ikke fundet en kompatibel Paper/Spigot/Purpur-version af dette plugin.');

    const file = version.files.find((item) => item.primary) || version.files[0];
    if (!file || !file.filename.toLowerCase().endsWith('.jar')) throw new Error('Plugin-versionen indeholder ikke en gyldig JAR-fil.');

    await http.post(`/api/client/servers/${serverId}/files/pull`, {
        url: file.url,
        directory: '/plugins',
        filename: file.filename,
        foreground: true,
    });
    return file.filename;
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
