import React, { FormEvent, useEffect, useMemo, useState } from 'react';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import { ServerContext } from '@/state/server';
import { httpErrorToHuman } from '@/api/http';
import {
    getInstalledMinecraftPlugins,
    installMinecraftPlugin,
    InstalledMinecraftPlugin,
    MinecraftPluginSearchResult,
    searchMinecraftPlugins,
    uninstallMinecraftPlugin,
} from '@/api/server/minecraftPlugins';

const loaderFromEgg = (egg: string): string => {
    const value = egg.toLowerCase();
    for (const loader of ['folia', 'purpur', 'paper', 'spigot', 'bukkit', 'velocity', 'waterfall', 'bungeecord']) {
        if (value.includes(loader)) return loader;
    }
    return 'paper';
};

const detectMinecraftVersion = (variables: Array<{ envVariable: string; serverValue: string }>): string => {
    const priority = ['MINECRAFT_VERSION', 'MC_VERSION', 'SERVER_VERSION', 'VERSION'];
    for (const key of priority) {
        const variable = variables.find((item) => item.envVariable.toUpperCase() === key);
        const value = variable?.serverValue?.trim();
        if (value && /^\d+\.\d+(?:\.\d+)?(?:[-+._a-zA-Z0-9]*)?$/.test(value)) return value;
    }
    return '';
};

const formatDownloads = (value: number) => new Intl.NumberFormat('da-DK', { notation: 'compact' }).format(value);
const formatSize = (bytes: number) => {
    if (!bytes) return '0 B';
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KiB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MiB`;
};

export default () => {
    const server = ServerContext.useStoreState((state) => state.server.data!);
    const isMinecraft = /minecraft|paper|purpur|spigot|bukkit|folia|velocity|waterfall|bungee/i.test(server.eggName);
    const detectedLoader = useMemo(() => loaderFromEgg(server.eggName), [server.eggName]);
    const detectedVersion = useMemo(() => detectMinecraftVersion(server.variables || []), [server.variables]);

    const [query, setQuery] = useState('');
    const [loader, setLoader] = useState(detectedLoader);
    const [gameVersion, setGameVersion] = useState(detectedVersion);
    const [results, setResults] = useState<MinecraftPluginSearchResult[]>([]);
    const [installed, setInstalled] = useState<InstalledMinecraftPlugin[]>([]);
    const [loading, setLoading] = useState(false);
    const [loadingInstalled, setLoadingInstalled] = useState(true);
    const [workingProject, setWorkingProject] = useState<string | null>(null);
    const [message, setMessage] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    const loadInstalled = async () => {
        setLoadingInstalled(true);
        try {
            setInstalled(await getInstalledMinecraftPlugins(server.id));
        } catch (e) {
            setError(httpErrorToHuman(e));
        } finally {
            setLoadingInstalled(false);
        }
    };

    useEffect(() => {
        if (isMinecraft) loadInstalled();
    }, [server.id, isMinecraft]);

    const search = async (event?: FormEvent) => {
        event?.preventDefault();
        setLoading(true);
        setError(null);
        setMessage(null);
        try {
            const response = await searchMinecraftPlugins(server.id, query, gameVersion, loader);
            setResults(response.results);
            if (response.results.length === 0) setMessage('Ingen kompatible plugins blev fundet med de valgte filtre.');
        } catch (e) {
            setError(httpErrorToHuman(e));
        } finally {
            setLoading(false);
        }
    };

    const install = async (plugin: MinecraftPluginSearchResult) => {
        setWorkingProject(plugin.projectId);
        setError(null);
        setMessage(null);
        try {
            setMessage(await installMinecraftPlugin(server.id, plugin.projectId, gameVersion, loader));
            await loadInstalled();
        } catch (e) {
            setError(httpErrorToHuman(e));
        } finally {
            setWorkingProject(null);
        }
    };

    const uninstall = async (plugin: InstalledMinecraftPlugin) => {
        if (!plugin.projectId || !window.confirm(`Fjern ${plugin.name} fra serveren?`)) return;
        setWorkingProject(plugin.projectId);
        setError(null);
        setMessage(null);
        try {
            setMessage(await uninstallMinecraftPlugin(server.id, plugin.projectId));
            await loadInstalled();
        } catch (e) {
            setError(httpErrorToHuman(e));
        } finally {
            setWorkingProject(null);
        }
    };

    if (!isMinecraft) {
        return (
            <ServerContentBlock title={'Plugin Manager'}>
                <div className={'rounded-xl border p-6 text-gray-300'} style={{ borderColor: 'var(--nodexa-border)', background: 'var(--nodexa-surface)' }}>
                    Plugin Manager er kun til Minecraft-servere, der bruger Paper, Purpur, Spigot, Bukkit, Folia eller en understøttet proxy.
                </div>
            </ServerContentBlock>
        );
    }

    return (
        <ServerContentBlock title={'Minecraft Plugin Manager'}>
            <div className={'space-y-5'}>
                <section
                    className={'rounded-2xl border p-5 md:p-6'}
                    style={{
                        borderColor: 'var(--nodexa-border)',
                        background: 'linear-gradient(145deg, rgba(var(--nodexa-accent-rgb), .08), var(--nodexa-surface))',
                    }}
                >
                    <div className={'flex flex-col gap-2 md:flex-row md:items-end md:justify-between'}>
                        <div>
                            <div className={'text-[11px] uppercase tracking-[.16em] font-semibold'} style={{ color: 'var(--nodexa-accent)' }}>
                                Nodexa Plugin Manager
                            </div>
                            <h1 className={'mt-1 text-2xl font-bold text-gray-50'}>Find og installér Minecraft plugins</h1>
                            <p className={'mt-2 max-w-3xl text-sm text-gray-400'}>
                                Søg i Modrinth-kataloget. Nodexa vælger en kompatibel JAR og installerer den automatisk i <code>/plugins</code> via Wings.
                            </p>
                        </div>
                        <div className={'rounded-xl border px-4 py-3 text-sm'} style={{ borderColor: 'var(--nodexa-border)', background: 'rgba(var(--nodexa-accent-rgb), .045)' }}>
                            <span className={'text-gray-500'}>Server:</span> <strong className={'text-gray-200'}>{server.eggName}</strong>
                        </div>
                    </div>
                </section>

                {(message || error) && (
                    <div
                        className={'rounded-xl border px-4 py-3 text-sm'}
                        style={{
                            borderColor: error ? 'rgba(248,113,113,.35)' : 'var(--nodexa-border-strong)',
                            background: error ? 'rgba(127,29,29,.22)' : 'rgba(var(--nodexa-accent-rgb), .08)',
                            color: error ? '#fecaca' : '#e5e7eb',
                        }}
                    >
                        {error || message}
                    </div>
                )}

                <section className={'rounded-2xl border p-5'} style={{ borderColor: 'var(--nodexa-border)', background: 'var(--nodexa-surface)' }}>
                    <div className={'flex items-center justify-between gap-3 mb-4'}>
                        <div>
                            <h2 className={'text-lg font-semibold text-gray-100'}>Installerede plugins</h2>
                            <p className={'text-xs text-gray-500 mt-1'}>Plugins installeret gennem Nodexa kan fjernes her igen.</p>
                        </div>
                        <button
                            type={'button'}
                            onClick={loadInstalled}
                            disabled={loadingInstalled}
                            className={'rounded-lg border px-3 py-2 text-xs font-semibold text-gray-300 disabled:opacity-50'}
                            style={{ borderColor: 'var(--nodexa-border)', background: 'rgba(var(--nodexa-accent-rgb), .05)' }}
                        >
                            {loadingInstalled ? 'Henter…' : 'Opdatér'}
                        </button>
                    </div>

                    {installed.length === 0 && !loadingInstalled ? (
                        <div className={'rounded-xl border border-dashed p-5 text-center text-sm text-gray-500'} style={{ borderColor: 'var(--nodexa-border)' }}>
                            Ingen plugin-JARs fundet i <code>/plugins</code> endnu.
                        </div>
                    ) : (
                        <div className={'grid gap-3 md:grid-cols-2'}>
                            {installed.map((plugin) => (
                                <div key={plugin.filename} className={'rounded-xl border p-4'} style={{ borderColor: 'var(--nodexa-border)', background: 'rgba(var(--nodexa-accent-rgb), .035)' }}>
                                    <div className={'flex items-start justify-between gap-3'}>
                                        <div className={'min-w-0'}>
                                            <div className={'truncate font-semibold text-gray-100'}>{plugin.name}</div>
                                            <div className={'mt-1 text-xs text-gray-500'}>
                                                {plugin.versionNumber ? `v${plugin.versionNumber} · ` : ''}{formatSize(plugin.size)} · {plugin.filename}
                                            </div>
                                            <div className={'mt-2'}>
                                                <span
                                                    className={'inline-flex rounded-full border px-2 py-1 text-[10px] font-semibold uppercase tracking-wide'}
                                                    style={{ borderColor: 'var(--nodexa-border)', color: plugin.managed ? 'var(--nodexa-accent)' : '#9ca3af' }}
                                                >
                                                    {plugin.managed ? 'Managed by Nodexa' : 'Manuelt installeret'}
                                                </span>
                                            </div>
                                        </div>
                                        {plugin.managed && plugin.projectId && (
                                            <button
                                                type={'button'}
                                                onClick={() => uninstall(plugin)}
                                                disabled={workingProject === plugin.projectId}
                                                className={'rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-2 text-xs font-semibold text-red-300 disabled:opacity-50'}
                                            >
                                                {workingProject === plugin.projectId ? 'Fjerner…' : 'Fjern'}
                                            </button>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </section>

                <section className={'rounded-2xl border p-5'} style={{ borderColor: 'var(--nodexa-border)', background: 'var(--nodexa-surface)' }}>
                    <form onSubmit={search} className={'grid gap-3 lg:grid-cols-[1fr_170px_170px_auto]'}>
                        <label className={'block'}>
                            <span className={'mb-1.5 block text-xs font-semibold text-gray-400'}>Søg efter plugin</span>
                            <input
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                placeholder={'LuckPerms, ViaVersion, WorldEdit…'}
                                className={'w-full rounded-xl border px-4 py-3 text-sm text-gray-100 outline-none'}
                                style={{ borderColor: 'var(--nodexa-border)', background: 'var(--nodexa-bg)' }}
                            />
                        </label>
                        <label className={'block'}>
                            <span className={'mb-1.5 block text-xs font-semibold text-gray-400'}>Loader</span>
                            <select
                                value={loader}
                                onChange={(e) => setLoader(e.target.value)}
                                className={'w-full rounded-xl border px-3 py-3 text-sm text-gray-100 outline-none'}
                                style={{ borderColor: 'var(--nodexa-border)', background: 'var(--nodexa-bg)' }}
                            >
                                {['paper', 'purpur', 'spigot', 'bukkit', 'folia', 'velocity', 'waterfall', 'bungeecord'].map((item) => (
                                    <option key={item} value={item}>{item}</option>
                                ))}
                            </select>
                        </label>
                        <label className={'block'}>
                            <span className={'mb-1.5 block text-xs font-semibold text-gray-400'}>Minecraft-version</span>
                            <input
                                value={gameVersion}
                                onChange={(e) => setGameVersion(e.target.value)}
                                placeholder={'fx. 1.21.1'}
                                className={'w-full rounded-xl border px-4 py-3 text-sm text-gray-100 outline-none'}
                                style={{ borderColor: 'var(--nodexa-border)', background: 'var(--nodexa-bg)' }}
                            />
                        </label>
                        <button
                            type={'submit'}
                            disabled={loading}
                            className={'self-end rounded-xl px-5 py-3 text-sm font-bold text-gray-950 disabled:opacity-50'}
                            style={{ background: 'linear-gradient(135deg, var(--nodexa-accent-2), var(--nodexa-accent))' }}
                        >
                            {loading ? 'Søger…' : 'Søg plugins'}
                        </button>
                    </form>
                    {!detectedVersion && (
                        <p className={'mt-3 text-xs text-gray-500'}>
                            Nodexa kunne ikke automatisk læse Minecraft-versionen fra Egg-variablerne. Skriv den manuelt for præcis kompatibilitetsfiltrering.
                        </p>
                    )}
                </section>

                {results.length > 0 && (
                    <section>
                        <div className={'mb-3 flex items-center justify-between'}>
                            <h2 className={'text-lg font-semibold text-gray-100'}>Resultater</h2>
                            <span className={'text-xs text-gray-500'}>{results.length} vist</span>
                        </div>
                        <div className={'grid gap-4 md:grid-cols-2 xl:grid-cols-3'}>
                            {results.map((plugin) => {
                                const isInstalled = installed.some((item) => item.projectId === plugin.projectId);
                                return (
                                    <article
                                        key={plugin.projectId}
                                        className={'flex min-h-[210px] flex-col rounded-2xl border p-5 transition-transform hover:-translate-y-0.5'}
                                        style={{
                                            borderColor: 'var(--nodexa-border)',
                                            background: 'linear-gradient(145deg, rgba(var(--nodexa-accent-rgb), .055), var(--nodexa-surface))',
                                        }}
                                    >
                                        <div className={'flex items-start gap-3'}>
                                            {plugin.iconUrl ? (
                                                <img src={plugin.iconUrl} alt={''} className={'h-12 w-12 rounded-xl object-cover'} loading={'lazy'} />
                                            ) : (
                                                <div
                                                    className={'flex h-12 w-12 items-center justify-center rounded-xl text-lg font-black'}
                                                    style={{ color: 'var(--nodexa-accent)', background: 'rgba(var(--nodexa-accent-rgb), .1)' }}
                                                >
                                                    P
                                                </div>
                                            )}
                                            <div className={'min-w-0 flex-1'}>
                                                <h3 className={'truncate text-base font-bold text-gray-100'}>{plugin.title}</h3>
                                                <p className={'mt-1 text-xs text-gray-500'}>af {plugin.author} · {formatDownloads(plugin.downloads)} downloads</p>
                                            </div>
                                        </div>
                                        <p className={'mt-4 flex-1 text-sm leading-relaxed text-gray-400'}>{plugin.description}</p>
                                        <div className={'mt-4 flex items-center justify-between gap-3'}>
                                            <span className={'truncate text-[11px] text-gray-500'}>
                                                {gameVersion ? `Minecraft ${gameVersion}` : 'Vælg version for bedste match'}
                                            </span>
                                            <button
                                                type={'button'}
                                                onClick={() => install(plugin)}
                                                disabled={workingProject === plugin.projectId}
                                                className={'rounded-lg px-3.5 py-2 text-xs font-bold text-gray-950 disabled:opacity-50'}
                                                style={{ background: 'linear-gradient(135deg, var(--nodexa-accent-2), var(--nodexa-accent))' }}
                                            >
                                                {workingProject === plugin.projectId ? 'Installerer…' : isInstalled ? 'Opdatér' : 'Installér'}
                                            </button>
                                        </div>
                                    </article>
                                );
                            })}
                        </div>
                    </section>
                )}
            </div>
        </ServerContentBlock>
    );
};
