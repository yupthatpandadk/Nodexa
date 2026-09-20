import React, { useEffect, useState } from 'react';
import tw from 'twin.macro';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import { ServerContext } from '@/state/server';
import { Button } from '@/components/elements/button/index';
import Input from '@/components/elements/Input';
import Spinner from '@/components/elements/Spinner';
import { httpErrorToHuman } from '@/api/http';
import {
    installedPlugins,
    installPlugin,
    ModrinthPlugin,
    searchPlugins,
    uninstallPlugin,
} from '@/api/server/plugins/pluginManager';
import { FileObject } from '@/api/server/files/loadDirectory';

const Card = tw.div`rounded-lg border border-neutral-700 bg-neutral-800 p-4`;
const Tab = tw.button`px-4 py-2 rounded-md text-sm font-semibold transition-colors`;

export default () => {
    const id = ServerContext.useStoreState((state) => state.server.data!.id);
    const variables = ServerContext.useStoreState((state) => state.server.data!.variables);
    const dockerImage = ServerContext.useStoreState((state) => state.server.data!.dockerImage);
    const invocation = ServerContext.useStoreState((state) => state.server.data!.invocation);

    const variableValue = (names: string[]): string | undefined => {
        const match = variables.find((variable) => names.includes(variable.envVariable.toUpperCase()));
        return match?.serverValue || match?.defaultValue || undefined;
    };

    const detectedVersion =
        variableValue(['MINECRAFT_VERSION', 'MC_VERSION', 'VERSION', 'SERVER_VERSION'])?.replace(/^v/i, '') || undefined;

    const source = `${dockerImage} ${invocation} ${variableValue(['SERVER_JARFILE', 'SERVER_TYPE']) || ''}`.toLowerCase();
    const detectedLoader =
        source.includes('purpur') ? 'purpur' :
        source.includes('paper') ? 'paper' :
        source.includes('spigot') ? 'spigot' :
        source.includes('folia') ? 'folia' :
        source.includes('bukkit') ? 'bukkit' : undefined;
    const [tab, setTab] = useState<'browse' | 'installed'>('browse');
    const [query, setQuery] = useState('');
    const [plugins, setPlugins] = useState<ModrinthPlugin[]>([]);
    const [installed, setInstalled] = useState<FileObject[]>([]);
    const [loading, setLoading] = useState(true);
    const [busy, setBusy] = useState<string | null>(null);
    const [message, setMessage] = useState<{ type: 'ok' | 'error'; text: string } | null>(null);

    const refreshInstalled = async () => setInstalled(await installedPlugins(id));

    const browse = async (value = query) => {
        setLoading(true);
        setMessage(null);
        try {
            setPlugins(await searchPlugins(value.trim(), detectedVersion, detectedLoader));
        } catch (error) {
            setMessage({ type: 'error', text: httpErrorToHuman(error) });
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        browse('');
        refreshInstalled();
    }, [id]);

    const install = async (plugin: ModrinthPlugin) => {
        setBusy(plugin.project_id);
        setMessage(null);
        try {
            const result = await installPlugin(id, plugin.project_id, detectedVersion, detectedLoader);
            const filename = result.filename;
            await refreshInstalled();
            setMessage({ type: 'ok', text: `${plugin.title} blev installeret som ${filename}. Genstart serveren for at indlæse pluginet.` });
        } catch (error) {
            setMessage({ type: 'error', text: httpErrorToHuman(error) });
        } finally {
            setBusy(null);
        }
    };

    const remove = async (file: FileObject) => {
        if (!window.confirm(`Vil du afinstallere ${file.name}?`)) return;
        setBusy(file.name);
        setMessage(null);
        try {
            await uninstallPlugin(id, file.name);
            await refreshInstalled();
            setMessage({ type: 'ok', text: `${file.name} blev afinstalleret. Genstart serveren for at fuldføre ændringen.` });
        } catch (error) {
            setMessage({ type: 'error', text: httpErrorToHuman(error) });
        } finally {
            setBusy(null);
        }
    };

    return (
        <ServerContentBlock title={'Plugin Manager'}>
            <div css={tw`mb-5 rounded-lg border border-neutral-700 bg-neutral-800 p-5`}>
                <div css={tw`flex flex-wrap items-center justify-between gap-3`}>
                    <div>
                        <h1 css={tw`text-xl font-bold text-neutral-50`}>Minecraft Plugin Manager</h1>
                        <p css={tw`mt-1 text-sm text-neutral-400`}>Find og installer server-plugins direkte fra Modrinth.</p>
                        <div css={tw`mt-2 flex flex-wrap gap-2 text-xs`}>
                            <span css={tw`rounded bg-neutral-900 px-2 py-1 text-cyan-300`}>Minecraft: {detectedVersion || 'Auto/ukendt'}</span>
                            <span css={tw`rounded bg-neutral-900 px-2 py-1 text-cyan-300`}>Loader: {detectedLoader || 'Plugin-loader'}</span>
                        </div>
                    </div>
                    <div css={tw`flex gap-2 rounded-lg bg-neutral-900 p-1`}>
                        <Tab css={tab === 'browse' ? tw`bg-cyan-600 text-white` : tw`text-neutral-300 hover:bg-neutral-700`} onClick={() => setTab('browse')}>Find plugins</Tab>
                        <Tab css={tab === 'installed' ? tw`bg-cyan-600 text-white` : tw`text-neutral-300 hover:bg-neutral-700`} onClick={() => setTab('installed')}>Installeret ({installed.length})</Tab>
                    </div>
                </div>
            </div>

            {message && (
                <div css={[tw`mb-4 rounded-md border p-3 text-sm`, message.type === 'ok' ? tw`border-green-700 bg-green-900 bg-opacity-30 text-green-200` : tw`border-red-700 bg-red-900 bg-opacity-30 text-red-200`]}>
                    {message.text}
                </div>
            )}

            {tab === 'browse' ? (
                <>
                    <form css={tw`mb-5 flex gap-2`} onSubmit={(e) => { e.preventDefault(); browse(); }}>
                        <Input value={query} onChange={(e) => setQuery(e.currentTarget.value)} placeholder={'Søg efter plugins...'} />
                        <Button type={'submit'}>Søg</Button>
                    </form>
                    {loading ? <Spinner size={'large'} centered /> : (
                        <div css={tw`grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3`}>
                            {plugins.map((plugin) => (
                                <Card key={plugin.project_id}>
                                    <div css={tw`flex items-start gap-3`}>
                                        {plugin.icon_url ? <img src={plugin.icon_url} alt={''} css={tw`h-12 w-12 rounded-lg object-cover`} /> : <div css={tw`h-12 w-12 rounded-lg bg-neutral-700`} />}
                                        <div css={tw`min-w-0 flex-1`}>
                                            <h2 css={tw`truncate font-bold text-neutral-100`}>{plugin.title}</h2>
                                            <p css={tw`text-xs text-neutral-500`}>af {plugin.author} · {plugin.downloads.toLocaleString()} downloads</p>
                                        </div>
                                    </div>
                                    <p css={tw`mt-3 h-10 overflow-hidden text-sm text-neutral-400`}>{plugin.description}</p>
                                    <div css={tw`mt-4 flex items-center justify-between`}>
                                        <span css={tw`text-xs text-neutral-500`}>{plugin.versions.slice(-3).join(' · ')}</span>
                                        <Button size={Button.Sizes.Small} disabled={busy !== null} onClick={() => install(plugin)}>
                                            {busy === plugin.project_id ? 'Installerer...' : 'Installér'}
                                        </Button>
                                    </div>
                                </Card>
                            ))}
                        </div>
                    )}
                </>
            ) : (
                <div css={tw`space-y-3`}>
                    {!installed.length ? <Card><p css={tw`text-center text-sm text-neutral-400`}>Ingen .jar plugins fundet i /plugins.</p></Card> :
                        installed.map((file) => (
                            <Card key={file.key}>
                                <div css={tw`flex items-center justify-between gap-4`}>
                                    <div css={tw`min-w-0`}>
                                        <p css={tw`truncate font-semibold text-neutral-100`}>{file.name}</p>
                                        <p css={tw`mt-1 text-xs text-neutral-500`}>{(file.size / 1024 / 1024).toFixed(2)} MiB · /plugins</p>
                                    </div>
                                    <Button.Danger size={Button.Sizes.Small} disabled={busy !== null} onClick={() => remove(file)}>
                                        {busy === file.name ? 'Fjerner...' : 'Afinstallér'}
                                    </Button.Danger>
                                </div>
                            </Card>
                        ))
                    }
                </div>
            )}
        </ServerContentBlock>
    );
};
