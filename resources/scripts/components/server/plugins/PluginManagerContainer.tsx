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

const Card = tw.div`rounded-xl border border-neutral-700 bg-neutral-800 p-5 shadow-md transition-all duration-200 hover:border-cyan-700`;
const Tab = tw.button`px-4 py-2 rounded-lg text-sm font-semibold transition-all duration-200`;

export default () => {
    const id = ServerContext.useStoreState((state) => state.server.data!.id);
    const variables = ServerContext.useStoreState((state) => state.server.data!.variables);
    const dockerImage = ServerContext.useStoreState((state) => state.server.data!.dockerImage);
    const invocation = ServerContext.useStoreState((state) => state.server.data!.invocation);

    const variableValue = (names: string[]): string | undefined => {
        const match = variables.find((variable) => names.includes(variable.envVariable.toUpperCase()));
        return match?.serverValue || match?.defaultValue || undefined;
    };

    const configuredVersion =
        variableValue(['MINECRAFT_VERSION', 'MC_VERSION', 'VERSION', 'SERVER_VERSION'])?.replace(/^v/i, '') || undefined;
    const detectedVersion = configuredVersion && configuredVersion.toLowerCase() !== 'latest' ? configuredVersion : undefined;

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
            <div css={tw`mb-6 overflow-hidden rounded-xl border border-neutral-700 bg-neutral-800 shadow-lg`}>
                <div css={tw`border-b border-neutral-700 px-5 py-5 md:px-6`}>
                    <div css={tw`flex flex-col gap-5 md:flex-row md:items-center md:justify-between`}>
                        <div css={tw`flex items-start gap-4`}>
                            <div css={tw`flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-xl bg-cyan-900 bg-opacity-40 text-xl font-bold text-cyan-300`}>P</div>
                            <div>
                                <h1 css={tw`text-xl font-bold text-neutral-50 md:text-2xl`}>Minecraft Plugin Manager</h1>
                                <p css={tw`mt-1 max-w-2xl text-sm leading-relaxed text-neutral-400`}>
                                    Find, installer og administrer plugins direkte fra Modrinth.
                                </p>
                                <div css={tw`mt-3 flex flex-wrap gap-2 text-xs font-medium`}>
                                    <span css={tw`rounded-full border border-neutral-700 bg-neutral-900 px-3 py-1 text-neutral-300`}>
                                        Minecraft: {detectedVersion || (configuredVersion === 'latest' ? 'Latest' : 'Auto')}
                                    </span>
                                    <span css={tw`rounded-full border border-neutral-700 bg-neutral-900 px-3 py-1 text-neutral-300`}>
                                        Loader: {detectedLoader ? detectedLoader.charAt(0).toUpperCase() + detectedLoader.slice(1) : 'Auto'}
                                    </span>
                                    <span css={tw`rounded-full border border-neutral-700 bg-neutral-900 px-3 py-1 text-neutral-300`}>Kilde: Modrinth</span>
                                </div>
                            </div>
                        </div>
                        <div css={tw`grid grid-cols-2 gap-1 rounded-xl border border-neutral-700 bg-neutral-900 p-1`}>
                            <Tab css={tab === 'browse' ? tw`bg-cyan-700 text-white shadow` : tw`text-neutral-400 hover:bg-neutral-800 hover:text-white`} onClick={() => setTab('browse')}>
                                Find plugins
                            </Tab>
                            <Tab css={tab === 'installed' ? tw`bg-cyan-700 text-white shadow` : tw`text-neutral-400 hover:bg-neutral-800 hover:text-white`} onClick={() => setTab('installed')}>
                                Installeret <span css={tw`ml-1 rounded-full bg-neutral-800 px-2 py-0.5 text-xs`}>{installed.length}</span>
                            </Tab>
                        </div>
                    </div>
                </div>
            </div>

            {message && (
                <div css={[tw`mb-5 rounded-xl border p-4 text-sm`, message.type === 'ok' ? tw`border-green-700 bg-green-900 bg-opacity-20 text-green-200` : tw`border-red-700 bg-red-900 bg-opacity-20 text-red-200`]}>
                    {message.text}
                </div>
            )}

            {tab === 'browse' ? (
                <>
                    <div css={tw`mb-5 rounded-xl border border-neutral-700 bg-neutral-800 p-4 shadow-md`}>
                        <form css={tw`flex flex-col gap-3 sm:flex-row`} onSubmit={(e) => { e.preventDefault(); browse(); }}>
                            <div css={tw`flex-1`}>
                                <Input value={query} onChange={(e) => setQuery(e.currentTarget.value)} placeholder={'Søg fx EssentialsX, LuckPerms eller WorldEdit...'} />
                            </div>
                            <Button type={'submit'}>Søg plugins</Button>
                        </form>
                    </div>

                    {loading ? (
                        <div css={tw`rounded-xl border border-neutral-700 bg-neutral-800 py-16`}><Spinner size={'large'} centered /></div>
                    ) : plugins.length === 0 ? (
                        <Card>
                            <div css={tw`py-8 text-center`}>
                                <div css={tw`text-lg font-semibold text-neutral-200`}>Ingen plugins fundet</div>
                                <p css={tw`mt-2 text-sm text-neutral-500`}>Prøv en anden søgning eller fjern versionsfilteret.</p>
                            </div>
                        </Card>
                    ) : (
                        <div css={tw`grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3`}>
                            {plugins.map((plugin) => (
                                <Card key={plugin.project_id}>
                                    <div css={tw`flex items-start gap-4`}>
                                        {plugin.icon_url ? (
                                            <img src={plugin.icon_url} alt={''} css={tw`h-14 w-14 flex-shrink-0 rounded-xl object-cover`} />
                                        ) : (
                                            <div css={tw`flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-xl bg-neutral-700 font-bold text-neutral-400`}>P</div>
                                        )}
                                        <div css={tw`min-w-0 flex-1`}>
                                            <h2 css={tw`truncate text-base font-bold text-neutral-100`}>{plugin.title}</h2>
                                            <p css={tw`mt-1 truncate text-xs text-neutral-500`}>af {plugin.author}</p>
                                            <p css={tw`mt-1 text-xs text-neutral-500`}>{plugin.downloads.toLocaleString()} downloads</p>
                                        </div>
                                    </div>
                                    <p css={tw`mt-4 h-10 overflow-hidden text-sm leading-relaxed text-neutral-400`}>{plugin.description}</p>
                                    <div css={tw`mt-5 flex items-end justify-between gap-3 border-t border-neutral-700 pt-4`}>
                                        <div css={tw`min-w-0`}>
                                            <p css={tw`text-xs uppercase tracking-wide text-neutral-600`}>Versioner</p>
                                            <p css={tw`mt-1 truncate text-xs text-neutral-400`}>{plugin.versions.slice(-3).join(' · ') || 'Automatisk'}</p>
                                        </div>
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
                <>
                    <div css={tw`mb-4 flex items-center justify-between`}>
                        <div>
                            <h2 css={tw`text-lg font-bold text-neutral-100`}>Installerede plugins</h2>
                            <p css={tw`mt-1 text-sm text-neutral-500`}>Plugins fundet i serverens /plugins-mappe.</p>
                        </div>
                        <span css={tw`rounded-full border border-neutral-700 bg-neutral-800 px-3 py-1 text-xs text-neutral-300`}>{installed.length} installeret</span>
                    </div>
                    <div css={tw`space-y-3`}>
                        {!installed.length ? (
                            <Card>
                                <div css={tw`py-8 text-center`}>
                                    <p css={tw`font-semibold text-neutral-200`}>Ingen plugins installeret endnu</p>
                                    <p css={tw`mt-2 text-sm text-neutral-500`}>Find et plugin under “Find plugins” og installér det med ét klik.</p>
                                </div>
                            </Card>
                        ) : installed.map((file) => (
                            <Card key={file.key}>
                                <div css={tw`flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between`}>
                                    <div css={tw`flex min-w-0 items-center gap-3`}>
                                        <div css={tw`flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-neutral-900 font-bold text-cyan-300`}>JAR</div>
                                        <div css={tw`min-w-0`}>
                                            <p css={tw`truncate font-semibold text-neutral-100`}>{file.name}</p>
                                            <p css={tw`mt-1 text-xs text-neutral-500`}>{(file.size / 1024 / 1024).toFixed(2)} MiB · /plugins</p>
                                        </div>
                                    </div>
                                    <Button.Danger size={Button.Sizes.Small} disabled={busy !== null} onClick={() => remove(file)}>
                                        {busy === file.name ? 'Fjerner...' : 'Afinstallér'}
                                    </Button.Danger>
                                </div>
                            </Card>
                        ))}
                    </div>
                </>
            )}
        </ServerContentBlock>
    );
};
