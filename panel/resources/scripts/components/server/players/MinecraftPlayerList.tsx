import React, { useCallback, useEffect, useState } from 'react';
import { ServerContext } from '@/state/server';
import { SocketEvent } from '@/components/server/events';
import { usePermissions } from '@/plugins/usePermissions';
import getFileContents from '@/api/server/files/getFileContents';

const clean = (value: string) => value.replace(/\u00a7[0-9A-FK-OR]/gi, '').replace(/\x1b\[[0-9;]*m/g, '').trim();
const names = (value: string) => value.split(',').map(v => v.trim()).filter(v => /^[A-Za-z0-9_]{1,16}$/.test(v));
const validPlayer = (player: string) => /^[A-Za-z0-9_]{1,16}$/.test(player);

const parsePlayers = (line: string): { online: number; max: number | null; players: string[] } | null => {
    const text = clean(line);
    let m = text.match(/There are\s+(\d+)\s+of a max of\s+(\d+)\s+players online:?\s*(.*)$/i);
    if (m) return { online: +m[1], max: +m[2], players: names(m[3]) };
    m = text.match(/There are\s+(\d+)\s*\/\s*(\d+)\s+players online:?\s*(.*)$/i);
    if (m) return { online: +m[1], max: +m[2], players: names(m[3]) };
    m = text.match(/Online players\s*\((\d+)\)\s*:?\s*(.*)$/i);
    if (m) return { online: +m[1], max: null, players: names(m[2]) };
    return null;
};

const parsePlayerEvent = (line: string): { type: 'join' | 'leave'; player: string } | null => {
    const text = clean(line);
    let m = text.match(/(?:^|:\s)([A-Za-z0-9_]{1,16}) joined the game\b/i);
    if (m) return { type: 'join', player: m[1] };
    m = text.match(/(?:^|:\s)([A-Za-z0-9_]{1,16}) left the game\b/i);
    if (m) return { type: 'leave', player: m[1] };
    m = text.match(/(?:^|:\s)([A-Za-z0-9_]{1,16}) lost connection:/i);
    if (m) return { type: 'leave', player: m[1] };
    m = text.match(/(?:^|:\s)([A-Za-z0-9_]{1,16})\[\/[^\]]+\] logged in with entity id/i);
    if (m) return { type: 'join', player: m[1] };
    return null;
};

const parseMaxPlayers = (properties: string): number | null => {
    const match = properties.match(/^\s*max[-_]players\s*=\s*(\d+)\s*$/im);
    if (!match) return null;
    const value = Number(match[1]);
    return Number.isFinite(value) && value >= 0 ? value : null;
};

type Punishment = { action: 'kick' | 'ban'; player: string } | null;
export interface MinecraftPlayerListProps { embedded?: boolean; }

export default ({ embedded = false }: MinecraftPlayerListProps) => {
    const socket = ServerContext.useStoreState(state => state.socket.instance);
    const status = ServerContext.useStoreState(state => state.status.value);
    const serverId = ServerContext.useStoreState(state => state.server.data!.id as string);
    const [canCommand] = usePermissions('control.console');
    const [players, setPlayers] = useState<string[]>([]);
    const [online, setOnline] = useState(0);
    const [max, setMax] = useState<number | null>(null);
    const [whitelisted, setWhitelisted] = useState<string[]>([]);
    const [loading, setLoading] = useState(false);
    const [updated, setUpdated] = useState<Date | null>(null);
    const [punishment, setPunishment] = useState<Punishment>(null);
    const [reason, setReason] = useState('');

    const loadMaxPlayers = useCallback(() => {
        getFileContents(serverId, '/server.properties').then(contents => {
            const configuredMax = parseMaxPlayers(contents);
            if (configuredMax !== null) setMax(configuredMax);
        }).catch(() => undefined);
    }, [serverId]);

    const loadWhitelist = useCallback(() => {
        getFileContents(serverId, '/whitelist.json').then(contents => {
            const entries = JSON.parse(contents) as Array<{ name?: string }>;
            setWhitelisted(entries.map(entry => entry.name || '').filter(validPlayer));
        }).catch(() => setWhitelisted([]));
    }, [serverId]);

    const send = useCallback((command: string) => {
        if (!socket || status !== 'running') return false;
        socket.send('send command', command);
        return true;
    }, [socket, status]);

    const refresh = useCallback(() => {
        loadMaxPlayers();
        loadWhitelist();
        if (!send('list')) return;
        setLoading(true);
        window.setTimeout(() => setLoading(false), 2500);
    }, [send, loadMaxPlayers, loadWhitelist]);

    useEffect(() => { loadMaxPlayers(); loadWhitelist(); }, [loadMaxPlayers, loadWhitelist]);

    useEffect(() => {
        if (!socket) return;
        const listener = (line: string) => {
            const result = parsePlayers(line);
            if (result) {
                if (result.players.length > 0 || result.online === 0) setPlayers(result.players);
                setOnline(result.online); setUpdated(new Date()); setLoading(false); return;
            }
            const event = parsePlayerEvent(line);
            if (!event) return;
            setPlayers(current => {
                const exists = current.some(p => p.toLowerCase() === event.player.toLowerCase());
                const next = event.type === 'join' ? (exists ? current : [...current, event.player]) : current.filter(p => p.toLowerCase() !== event.player.toLowerCase());
                setOnline(next.length); return next;
            });
            setUpdated(new Date()); setLoading(false);
        };
        socket.on(SocketEvent.CONSOLE_OUTPUT, listener);
        return () => socket.removeListener(SocketEvent.CONSOLE_OUTPUT, listener);
    }, [socket]);

    const command = (action: 'op' | 'deop', player: string) => {
        if (!canCommand || !validPlayer(player)) return;
        send(`${action} ${player}`);
    };

    const toggleWhitelist = (player: string) => {
        if (!canCommand || !validPlayer(player)) return;
        const isWhitelisted = whitelisted.some(name => name.toLowerCase() === player.toLowerCase());
        if (!send(`whitelist ${isWhitelisted ? 'remove' : 'add'} ${player}`)) return;
        setWhitelisted(current => isWhitelisted ? current.filter(name => name.toLowerCase() !== player.toLowerCase()) : [...current, player]);
        window.setTimeout(loadWhitelist, 800);
    };

    const openPunishment = (action: 'kick' | 'ban', player: string) => {
        if (!canCommand || !validPlayer(player)) return;
        setReason(''); setPunishment({ action, player });
    };

    const confirmPunishment = () => {
        if (!punishment) return;
        const message = reason.replace(/[\r\n]+/g, ' ').trim();
        if (!message) return;
        if (send(`${punishment.action} ${punishment.player} ${message}`)) { setPunishment(null); setReason(''); }
    };

    const body = status !== 'running' ? <div className={'flex flex-1 items-center justify-center p-8 text-center text-sm text-gray-400'}>Serveren skal være startet for at vise spillere.</div> : players.length === 0 ? <div className={'flex flex-1 flex-col items-center justify-center p-8 text-center'}><div className={'text-4xl mb-3'}>👥</div><div className={'font-semibold text-gray-200'}>{loading ? 'Henter spillerliste…' : 'Ingen spillere online'}</div><div className={'text-xs text-gray-500 mt-1'}>{updated ? 'Spillere vises her, når de joiner serveren.' : 'Spillerlisten opdateres automatisk ved join og disconnect.'}</div></div> : <div className={'overflow-y-auto flex-1'}>{players.map(player => { const isWhitelisted = whitelisted.some(name => name.toLowerCase() === player.toLowerCase()); return <div key={player} className={'px-4 py-3 flex flex-wrap items-center justify-between gap-3 border-t'} style={{ borderColor: 'var(--nodexa-border)' }}><div className={'flex items-center gap-3 min-w-0'}><img src={`https://mc-heads.net/avatar/${encodeURIComponent(player)}/40`} width={40} height={40} className={'rounded-lg'} alt={player}/><div className={'min-w-0'}><div className={'font-semibold text-white truncate'}>{player}</div><div className={'text-xs text-green-400'}>● Online</div></div></div>{canCommand && <div className={'flex flex-wrap gap-1.5'}><button type={'button'} onClick={() => openPunishment('kick', player)} className={'px-2 py-1 rounded bg-gray-700 text-white text-xs'}>Kick</button><button type={'button'} onClick={() => command('op', player)} className={'px-2 py-1 rounded bg-gray-700 text-white text-xs'}>OP</button><button type={'button'} onClick={() => command('deop', player)} className={'px-2 py-1 rounded bg-gray-700 text-white text-xs'}>De-OP</button><button type={'button'} onClick={() => toggleWhitelist(player)} className={'px-2 py-1 rounded bg-gray-700 text-white text-xs'}>{isWhitelisted ? 'Unwhitelist' : 'Whitelist'}</button><button type={'button'} onClick={() => openPunishment('ban', player)} className={'px-2 py-1 rounded bg-red-700 text-white text-xs'}>Ban</button></div>}</div>; })}</div>;

    const modal = punishment && <div className={'fixed inset-0 z-50 flex items-center justify-center p-4'} style={{ background: 'rgba(0,0,0,.72)' }} onMouseDown={e => { if (e.target === e.currentTarget) setPunishment(null); }}><div className={'w-full max-w-md rounded-xl p-5 shadow-2xl'} style={{ background: 'var(--nodexa-card)', border: '1px solid var(--nodexa-border)' }}><div className={'flex items-start justify-between gap-4 mb-4'}><div><h3 className={'text-lg font-bold text-white'}>{punishment.action === 'ban' ? 'Ban spiller' : 'Kick spiller'}</h3><p className={'text-sm text-gray-400 mt-1'}>{punishment.player}</p></div><button type={'button'} onClick={() => setPunishment(null)} className={'text-gray-400 hover:text-white text-xl'}>×</button></div><label className={'block text-sm font-semibold text-gray-200 mb-2'}>Begrundelse</label><textarea autoFocus value={reason} onChange={e => setReason(e.target.value)} onKeyDown={e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); confirmPunishment(); } }} rows={4} maxLength={180} placeholder={'Skriv begrundelsen her…'} className={'w-full rounded-lg px-3 py-2 text-white outline-none resize-none'} style={{ background: 'rgba(0,0,0,.25)', border: '1px solid var(--nodexa-border)' }}/><div className={'flex justify-end gap-2 mt-4'}><button type={'button'} onClick={() => setPunishment(null)} className={'px-4 py-2 rounded-lg bg-gray-700 text-white text-sm font-semibold'}>Annuller</button><button type={'button'} disabled={!reason.trim()} onClick={confirmPunishment} className={`px-4 py-2 rounded-lg text-white text-sm font-semibold disabled:opacity-40 ${punishment.action === 'ban' ? 'bg-red-700' : 'bg-blue-700'}`}>{punishment.action === 'ban' ? 'Ban spiller' : 'Kick spiller'}</button></div></div></div>;

    if (embedded) return <><section className={'rounded-xl overflow-hidden flex flex-col min-h-[430px]'} style={{ background: 'var(--nodexa-card)', border: '1px solid var(--nodexa-border)' }}><div className={'px-4 py-3 flex items-center justify-between gap-3'} style={{ borderBottom: '1px solid var(--nodexa-border)' }}><div className={'flex items-center gap-2'}><span>👤</span><h2 className={'font-header font-semibold text-gray-100'}>Players</h2><span className={'text-sm text-gray-400'}>{online} / {max ?? '…'}</span></div><button type={'button'} onClick={refresh} disabled={loading || status !== 'running'} className={'text-xs font-semibold disabled:opacity-40'} style={{ color: 'var(--nodexa-accent)' }}>{loading ? 'Opdaterer…' : '↻ Opdater'}</button></div>{body}<div className={'grid grid-cols-2 gap-2 px-4 py-3 text-center'} style={{ borderTop: '1px solid var(--nodexa-border)' }}><div><div className={'text-white font-semibold'}>{online} / {max ?? '…'}</div><div className={'text-xs text-gray-500'}>Players</div></div><div><div className={'text-white font-semibold'}>{updated ? updated.toLocaleTimeString() : '—'}</div><div className={'text-xs text-gray-500'}>Senest opdateret</div></div></div></section>{modal}</>;
    return <><div className={'p-4 md:p-8 max-w-6xl mx-auto'}><div className={'rounded-xl p-5'} style={{ background: 'var(--nodexa-card)', border: '1px solid var(--nodexa-border)' }}><div className={'flex items-center justify-between gap-4 mb-4'}><div><h1 className={'text-2xl font-bold text-white'}>Minecraft Players</h1><p className={'text-sm text-gray-400 mt-1'}>Live spillerliste direkte fra serverkonsollen.</p></div><button type={'button'} onClick={refresh} disabled={loading || status !== 'running'} className={'px-4 py-2 rounded-lg font-semibold text-white disabled:opacity-50'} style={{ background: 'var(--nodexa-accent)' }}>{loading ? 'Opdaterer…' : 'Opdater'}</button></div>{body}</div></div>{modal}</>;
};
