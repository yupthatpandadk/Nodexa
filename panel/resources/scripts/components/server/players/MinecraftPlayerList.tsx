import React, { useCallback, useEffect, useRef, useState } from 'react';
import { ServerContext } from '@/state/server';
import { SocketEvent, SocketRequest } from '@/components/server/events';
import { usePermissions } from '@/plugins/usePermissions';

const clean = (value: string) => value.replace(/\u00a7[0-9A-FK-OR]/gi, '').replace(/\x1b\[[0-9;]*m/g, '').trim();

const parsePlayers = (line: string): { online: number; max: number | null; players: string[] } | null => {
    const text = clean(line);
    let match = text.match(/There are (\d+) of a max of (\d+) players online:\s*(.*)$/i);
    if (match) return { online: Number(match[1]), max: Number(match[2]), players: match[3] ? match[3].split(',').map(v => v.trim()).filter(Boolean) : [] };
    match = text.match(/There are (\d+)\/([0-9]+) players online:\s*(.*)$/i);
    if (match) return { online: Number(match[1]), max: Number(match[2]), players: match[3] ? match[3].split(',').map(v => v.trim()).filter(Boolean) : [] };
    match = text.match(/Online players \((\d+)\):\s*(.*)$/i);
    if (match) return { online: Number(match[1]), max: null, players: match[2] ? match[2].split(',').map(v => v.trim()).filter(Boolean) : [] };
    return null;
};

const parsePlayerEvent = (line: string): { type: 'join' | 'leave'; player: string } | null => {
    const text = clean(line);
    let match = text.match(/(?:^|:\s)([A-Za-z0-9_]{1,16}) joined the game\b/i);
    if (match) return { type: 'join', player: match[1] };

    match = text.match(/(?:^|:\s)([A-Za-z0-9_]{1,16}) left the game\b/i);
    if (match) return { type: 'leave', player: match[1] };

    match = text.match(/(?:^|:\s)([A-Za-z0-9_]{1,16}) lost connection:/i);
    if (match) return { type: 'leave', player: match[1] };

    match = text.match(/(?:^|:\s)([A-Za-z0-9_]{1,16})\[\/[^\]]+\] logged in with entity id/i);
    if (match) return { type: 'join', player: match[1] };

    return null;
};

export interface MinecraftPlayerListProps { embedded?: boolean; }

export default ({ embedded = false }: MinecraftPlayerListProps) => {
    const socket = ServerContext.useStoreState(state => state.socket.instance);
    const status = ServerContext.useStoreState(state => state.status.value);
    const [canCommand] = usePermissions('control.console');
    const [players, setPlayers] = useState<string[]>([]);
    const [online, setOnline] = useState(0);
    const [max, setMax] = useState<number | null>(null);
    const [loading, setLoading] = useState(false);
    const [updated, setUpdated] = useState<Date | null>(null);
    const refreshTimer = useRef<number | null>(null);

    const refresh = useCallback(() => {
        if (!socket || status !== 'running') return;
        setLoading(true);
        socket.send(SocketRequest.SEND_COMMAND, 'list');
        window.setTimeout(() => setLoading(false), 2500);
    }, [socket, status]);

    const delayedRefresh = useCallback(() => {
        if (refreshTimer.current !== null) window.clearTimeout(refreshTimer.current);
        refreshTimer.current = window.setTimeout(() => { refreshTimer.current = null; refresh(); }, 500);
    }, [refresh]);

    useEffect(() => {
        if (!socket) return;
        const listener = (line: string) => {
            const result = parsePlayers(line);
            if (result) {
                setPlayers(result.players);
                setOnline(result.online);
                if (result.max !== null) setMax(result.max);
                setUpdated(new Date());
                setLoading(false);
                return;
            }

            const event = parsePlayerEvent(line);
            if (!event) return;
            setPlayers(current => {
                const next = event.type === 'join'
                    ? (current.some(p => p.toLowerCase() === event.player.toLowerCase()) ? current : [...current, event.player])
                    : current.filter(p => p.toLowerCase() !== event.player.toLowerCase());
                setOnline(next.length);
                return next;
            });
            setUpdated(new Date());
            setLoading(false);
            delayedRefresh();
        };

        socket.on(SocketEvent.CONSOLE_OUTPUT, listener);
        refresh();
        const timer = window.setInterval(refresh, 30000);
        return () => {
            socket.removeListener(SocketEvent.CONSOLE_OUTPUT, listener);
            window.clearInterval(timer);
            if (refreshTimer.current !== null) window.clearTimeout(refreshTimer.current);
        };
    }, [socket, refresh, delayedRefresh]);

    const command = (action: 'kick' | 'ban' | 'op' | 'deop' | 'whitelist add', player: string) => {
        if (!socket || !canCommand || !/^[A-Za-z0-9_]{1,16}$/.test(player)) return;
        socket.send(SocketRequest.SEND_COMMAND, `${action} ${player}`);
        window.setTimeout(refresh, 700);
    };

    const body = status !== 'running' ? <div className={'flex flex-1 items-center justify-center p-8 text-center text-sm text-gray-400'}>Serveren skal være startet for at vise spillere.</div> : players.length === 0 ? <div className={'flex flex-1 flex-col items-center justify-center p-8 text-center'}><div className={'text-4xl mb-3'}>👥</div><div className={'font-semibold text-gray-200'}>{loading ? 'Henter spillerliste…' : 'Ingen spillere online'}</div><div className={'text-xs text-gray-500 mt-1'}>{updated ? 'Spillere vises her, når de joiner serveren.' : 'Venter på svar fra Minecraft-serveren…'}</div></div> : <div className={'overflow-y-auto flex-1'}>{players.map(player => <div key={player} className={'px-4 py-3 flex flex-wrap items-center justify-between gap-3 border-t'} style={{ borderColor: 'var(--nodexa-border)' }}><div className={'flex items-center gap-3 min-w-0'}><img src={`https://mc-heads.net/avatar/${encodeURIComponent(player)}/40`} width={40} height={40} className={'rounded-lg'} alt={player}/><div className={'min-w-0'}><div className={'font-semibold text-white truncate'}>{player}</div><div className={'text-xs text-green-400'}>● Online</div></div></div>{canCommand && <div className={'flex flex-wrap gap-1.5'}><button onClick={() => command('kick', player)} className={'px-2 py-1 rounded bg-gray-700 text-white text-xs'}>Kick</button><button onClick={() => command('op', player)} className={'px-2 py-1 rounded bg-gray-700 text-white text-xs'}>OP</button><button onClick={() => command('deop', player)} className={'px-2 py-1 rounded bg-gray-700 text-white text-xs'}>De-OP</button><button onClick={() => command('whitelist add', player)} className={'px-2 py-1 rounded bg-gray-700 text-white text-xs'}>Whitelist</button><button onClick={() => command('ban', player)} className={'px-2 py-1 rounded bg-red-700 text-white text-xs'}>Ban</button></div>}</div>)}</div>;

    if (embedded) return <section className={'rounded-xl overflow-hidden flex flex-col min-h-[430px]'} style={{ background: 'var(--nodexa-card)', border: '1px solid var(--nodexa-border)' }}><div className={'px-4 py-3 flex items-center justify-between gap-3'} style={{ borderBottom: '1px solid var(--nodexa-border)' }}><div className={'flex items-center gap-2'}><span>👤</span><h2 className={'font-header font-semibold text-gray-100'}>Players</h2><span className={'text-sm text-gray-400'}>{online} / {max ?? '—'}</span></div><button onClick={refresh} disabled={loading || status !== 'running'} className={'text-xs font-semibold disabled:opacity-40'} style={{ color: 'var(--nodexa-accent)' }}>{loading ? 'Opdaterer…' : '↻ Opdater'}</button></div>{body}<div className={'grid grid-cols-2 gap-2 px-4 py-3 text-center'} style={{ borderTop: '1px solid var(--nodexa-border)' }}><div><div className={'text-white font-semibold'}>{online} / {max ?? '—'}</div><div className={'text-xs text-gray-500'}>Players</div></div><div><div className={'text-white font-semibold'}>{updated ? updated.toLocaleTimeString() : '—'}</div><div className={'text-xs text-gray-500'}>Senest opdateret</div></div></div></section>;

    return <div className={'p-4 md:p-8 max-w-6xl mx-auto'}><div className={'rounded-xl p-5'} style={{ background: 'var(--nodexa-card)', border: '1px solid var(--nodexa-border)' }}><div className={'flex items-center justify-between gap-4 mb-4'}><div><h1 className={'text-2xl font-bold text-white'}>Minecraft Players</h1><p className={'text-sm text-gray-400 mt-1'}>Live spillerliste direkte fra serverkonsollen.</p></div><button onClick={refresh} disabled={loading || status !== 'running'} className={'px-4 py-2 rounded-lg font-semibold text-white disabled:opacity-50'} style={{ background: 'var(--nodexa-accent)' }}>{loading ? 'Opdaterer…' : 'Opdater'}</button></div>{body}</div></div>;
};
