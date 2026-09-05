import React, { FormEvent, useEffect, useMemo, useState } from 'react';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import { ServerContext } from '@/state/server';
import { httpErrorToHuman } from '@/api/http';
import {
    getInstalledMinecraftMods,
    installMinecraftMod,
    InstalledMinecraftMod,
    MinecraftModSearchResult,
    searchMinecraftMods,
    uninstallMinecraftMod,
} from '@/api/server/minecraftMods';

const loaderFromEgg = (egg: string): 'forge' | 'fabric' | null => {
    const value = egg.toLowerCase();
    if (value.includes('fabric')) return 'fabric';
    if (value.includes('forge')) return 'forge';
    return null;
};

const detectMinecraftVersion = (variables: Array<{ envVariable: string; serverValue: string | null }>): string => {
    for (const key of ['MINECRAFT_VERSION', 'MC_VERSION', 'SERVER_VERSION', 'VERSION']) {
        const variable = variables.find((item) => item.envVariable.toUpperCase() === key);
        const value = variable?.serverValue?.trim();
        if (value && /^\d+\.\d+(?:\.\d+)?(?:[-+._a-zA-Z0-9]*)?$/.test(value)) return value;
    }
    return '';
};

const formatDownloads = (value: number) => new Intl.NumberFormat('da-DK', { notation: 'compact' }).format(value);
const formatSize = (bytes: number) => bytes < 1024 * 1024 ? `${Math.max(1, Math.round(bytes / 1024))} KiB` : `${(bytes / 1024 / 1024).toFixed(1)} MiB`;

export default () => {
    const server = ServerContext.useStoreState((state) => state.server.data!);
    const detectedLoader = useMemo(() => loaderFromEgg(server.eggName), [server.eggName]);
    const detectedVersion = useMemo(() => detectMinecraftVersion(server.variables || []), [server.variables]);

    const [query, setQuery] = useState('');
    const [gameVersion, setGameVersion] = useState(detectedVersion);
    const [results, setResults] = useState<MinecraftModSearchResult[]>([]);
    const [installed, setInstalled] = useState<InstalledMinecraftMod[]>([]);
    const [loading, setLoading] = useState(false);
    const [loadingInstalled, setLoadingInstalled] = useState(false);
    const [working, setWorking] = useState<string | null>(null);
    const [message, setMessage] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    const loadInstalled = async () => {
        setLoadingInstalled(true);
        try {
            const response = await getInstalledMinecraftMods(server.id);
            setInstalled(response.mods);
        } catch (e) {
            setError(httpErrorToHuman(e));
        } finally {
            setLoadingInstalled(false);
        }
    };

    const loadMods = async (search = '') => {
        setLoading(true);
        setError(null);
        setMessage(null);
        try {
            const response = await searchMinecraftMods(server.id, search.trim(), gameVersion);
            setResults(response.results);
            if (!response.results.length) setMessage('Ingen kompatible mods blev fundet til denne server.');
        } catch (e) {
            setError(httpErrorToHuman(e));
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (!detectedLoader) return;
        loadInstalled();
        loadMods('');
    }, [server.id, detectedLoader]);

    const search = async (event: FormEvent) => {
        event.preventDefault();
        await loadMods(query);
    };

    const install = async (mod: MinecraftModSearchResult) => {
        setWorking(mod.projectId);
        setError(null);
        setMessage(null);
        try {
            setMessage(await installMinecraftMod(server.id, mod.projectId, gameVersion));
            await loadInstalled();
        } catch (e) {
            setError(httpErrorToHuman(e));
        } finally {
            setWorking(null);
        }
    };

    const remove = async (mod: InstalledMinecraftMod) => {
        if (!window.confirm(`Fjern ${mod.name} fra serveren?`)) return;
        setWorking(mod.filename);
        try {
            setMessage(await uninstallMinecraftMod(server.id, mod.filename));
            await loadInstalled();
        } catch (e) {
            setError(httpErrorToHuman(e));
        } finally {
            setWorking(null);
        }
    };

    if (!detectedLoader) {
        return (
            <ServerContentBlock title={'Mod Manager'}>
                <div className={'rounded-xl border p-6 text-gray-300'} style={{ borderColor: 'var(--nodexa-border)', background: 'var(--nodexa-surface)' }}>
                    Mod Manager vises kun på Forge- og Fabric-servere.
                </div>
            </ServerContentBlock>
        );
    }

    return (
        <ServerContentBlock title={'Minecraft Mod Manager'}>
            <div className={'space-y-5'}>
                <section className={'rounded-2xl border p-5 md:p-6'} style={{ borderColor: 'var(--nodexa-border)', background: 'linear-gradient(145deg, rgba(var(--nodexa-accent-rgb), .08), var(--nodexa-surface))' }}>
                    <div className={'flex flex-col gap-3 md:flex-row md:items-end md:justify-between'}>
                        <div>
                            <div className={'text-[11px] uppercase tracking-[.16em] font-semibold'} style={{ color: 'var(--nodexa-accent)' }}>Nodexa Mod Manager</div>
                            <h1 className={'mt-1 text-2xl font-bold text-gray-50'}>Find og installér {detectedLoader === 'forge' ? 'Forge' : 'Fabric'} mods</h1>
                            <p className={'mt-2 max-w-3xl text-sm text-gray-400'}>
                                Mods hentes fra Modrinth og installeres direkte i <code>/mods</code> via Wings. Loaderen bestemmes af serveren og kan ikke ændres manuelt.
                            </p>
                        </div>
                        <div className={'rounded-xl border px-4 py-3 text-sm'} style={{ borderColor: 'var(--nodexa-border)', background: 'rgba(var(--nodexa-accent-rgb), .045)' }}>
                            <span className={'text-gray-500'}>Loader:</span> <strong className={'uppercase'} style={{ color: 'var(--nodexa-accent)' }}>{detectedLoader}</strong>
                        </div>
                    </div>
                </section>

                {(message || error) && <div className={'rounded-xl border px-4 py-3 text-sm'} style={{ borderColor: error ? 'rgba(248,113,113,.35)' : 'var(--nodexa-border-strong)', background: error ? 'rgba(127,29,29,.22)' : 'rgba(var(--nodexa-accent-rgb), .08)', color: error ? '#fecaca' : '#e5e7eb' }}>{error || message}</div>}

                <section className={'rounded-2xl border p-5'} style={{ borderColor: 'var(--nodexa-border)', background: 'var(--nodexa-surface)' }}>
                    <div className={'mb-4 flex items-center justify-between gap-3'}>
                        <div><h2 className={'text-lg font-semibold text-gray-100'}>Installerede mods</h2><p className={'mt-1 text-xs text-gray-500'}>JAR-filer fundet i serverens /mods-mappe.</p></div>
                        <button type={'button'} onClick={loadInstalled} disabled={loadingInstalled} className={'rounded-lg border px-3 py-2 text-xs font-semibold text-gray-300 disabled:opacity-50'} style={{ borderColor: 'var(--nodexa-border)' }}>{loadingInstalled ? 'Henter…' : 'Opdatér'}</button>
                    </div>
                    {!installed.length && !loadingInstalled ? <div className={'rounded-xl border border-dashed p-5 text-center text-sm text-gray-500'} style={{ borderColor: 'var(--nodexa-border)' }}>Ingen mods fundet endnu.</div> : (
                        <div className={'grid gap-3 md:grid-cols-2'}>{installed.map((mod) => <div key={mod.filename} className={'rounded-xl border p-4'} style={{ borderColor: 'var(--nodexa-border)', background: 'rgba(var(--nodexa-accent-rgb), .035)' }}><div className={'flex items-start justify-between gap-3'}><div className={'min-w-0'}><div className={'truncate font-semibold text-gray-100'}>{mod.name}</div><div className={'mt-1 text-xs text-gray-500'}>{formatSize(mod.size)} · {mod.filename}</div></div><button type={'button'} onClick={() => remove(mod)} disabled={working === mod.filename} className={'rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-2 text-xs font-semibold text-red-300 disabled:opacity-50'}>{working === mod.filename ? 'Fjerner…' : 'Fjern'}</button></div></div>)}</div>
                    )}
                </section>

                <section className={'rounded-2xl border p-5'} style={{ borderColor: 'var(--nodexa-border)', background: 'var(--nodexa-surface)' }}>
                    <form onSubmit={search} className={'grid gap-3 lg:grid-cols-[1fr_190px_auto]'}>
                        <label><span className={'mb-1.5 block text-xs font-semibold text-gray-400'}>Søg efter mod</span><input value={query} onChange={(e) => setQuery(e.target.value)} placeholder={'Sodium, JourneyMap, JEI…'} className={'w-full rounded-xl border px-4 py-3 text-sm text-gray-100 outline-none'} style={{ borderColor: 'var(--nodexa-border)', background: 'var(--nodexa-bg)' }} /></label>
                        <label><span className={'mb-1.5 block text-xs font-semibold text-gray-400'}>Minecraft-version</span><input value={gameVersion} onChange={(e) => setGameVersion(e.target.value)} placeholder={'fx. 1.21.1'} className={'w-full rounded-xl border px-4 py-3 text-sm text-gray-100 outline-none'} style={{ borderColor: 'var(--nodexa-border)', background: 'var(--nodexa-bg)' }} /></label>
                        <button type={'submit'} disabled={loading} className={'self-end rounded-xl px-5 py-3 text-sm font-bold text-gray-950 disabled:opacity-50'} style={{ background: 'linear-gradient(135deg, var(--nodexa-accent-2), var(--nodexa-accent))' }}>{loading ? 'Søger…' : 'Søg mods'}</button>
                    </form>
                    {!detectedVersion && <p className={'mt-3 text-xs text-gray-500'}>Minecraft-versionen kunne ikke aflæses automatisk. Angiv den manuelt for præcis kompatibilitetsfiltrering.</p>}
                </section>

                {!!results.length && <section><div className={'mb-3 flex items-center justify-between'}><h2 className={'text-lg font-semibold text-gray-100'}>{query.trim() ? 'Søgeresultater' : 'Populære mods'}</h2><span className={'text-xs text-gray-500'}>{results.length} vist · kun {detectedLoader}</span></div><div className={'grid gap-4 md:grid-cols-2 xl:grid-cols-3'}>{results.map((mod) => <article key={mod.projectId} className={'flex min-h-[220px] flex-col rounded-2xl border p-5'} style={{ borderColor: 'var(--nodexa-border)', background: 'linear-gradient(145deg, rgba(var(--nodexa-accent-rgb), .055), var(--nodexa-surface))' }}><div className={'flex items-start gap-3'}>{mod.iconUrl ? <img src={mod.iconUrl} alt={''} className={'h-12 w-12 rounded-xl object-cover'} /> : <div className={'flex h-12 w-12 items-center justify-center rounded-xl font-bold'} style={{ background: 'var(--nodexa-accent-soft)', color: 'var(--nodexa-accent)' }}>M</div>}<div className={'min-w-0'}><h3 className={'truncate font-semibold text-gray-100'}>{mod.title}</h3><div className={'mt-1 text-xs text-gray-500'}>af {mod.author} · {formatDownloads(mod.downloads)} downloads</div></div></div><p className={'mt-3 flex-1 text-sm text-gray-400'}>{mod.description}</p><div className={'mt-4 flex items-center justify-between gap-3'}><span className={'rounded-full border px-2 py-1 text-[10px] font-bold uppercase'} style={{ borderColor: 'var(--nodexa-border)', color: 'var(--nodexa-accent)' }}>{detectedLoader}</span><button type={'button'} onClick={() => install(mod)} disabled={working === mod.projectId} className={'rounded-lg px-4 py-2 text-xs font-bold text-gray-950 disabled:opacity-50'} style={{ background: 'var(--nodexa-accent)' }}>{working === mod.projectId ? 'Installerer…' : 'Installér'}</button></div></article>)}</div></section>}
            </div>
        </ServerContentBlock>
    );
};
