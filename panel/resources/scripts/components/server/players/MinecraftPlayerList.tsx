import React, { useCallback, useEffect, useState } from 'react';
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

export default () => {
    const socket = ServerContext.useStoreState(state => state.socket.instance);
    const status = ServerContext.useStoreState(state => state.status.value);
    const [canCommand] = usePermissions('control.console');
    const [players, setPlayers] = useState<string[]>([]);
    const [online, setOnline] = useState(0);
    const [max, setMax] = useState<number | null>(null);
    const [loading, setLoading] = useState(false);
    const [updated, setUpdated] = useState<Date | null>(null);

    const refresh = useCallback(() => {
        if (!socket || status !== 'running') return;
        setLoading(true);
        socket.send(SocketRequest.SEND_COMMAND, 'list');
        window.setTimeout(() => setLoading(false), 2500);
    }, [socket, status]);

    useEffect(() => {
        if (!socket) return;
        const listener = (line: string) => {
            const result = parsePlayers(line);
            if (!result) return;
            setPlayers(result.players);
            setOnline(result.online);
            if (result.max !== null) setMax(result.max);
            setUpdated(new Date());
            setLoading(false);
        };
        socket.on(SocketEvent.CONSOLE_OUTPUT, listener);
        refresh();
        const timer = window.setInterval(refresh, 15000);
        return () => { socket.removeListener(SocketEvent.CONSOLE_OUTPUT, listener); window.clearInterval(timer); };
    }, [socket, refresh]);

    const command = (action: 'kick' | 'ban' | 'op' | 'deop' | 'whitelist add', player: string) => {
        if (!socket || !canCommand || !/^[A-Za-z0-9_]{1,16}$/.test(player)) return;
        socket.send(SocketRequest.SEND_COMMAND, `${action} ${player}`);
        window.setTimeout(refresh, 700);
    };

    return <div className={'p-4 md:p-8 max-w-6xl mx-auto'}>
        <div className={'rounded-xl p-5 mb-5'} style={{ background: 'var(--nodexa-card)', border: '1px solid var(--nodexa-border)' }}>
            <div className={'flex flex-wrap items-center justify-between gap-4'}>
                <div><h1 className={'text-2xl font-bold text-white'}>Minecraft Players</h1><p className={'text-sm text-gray-400 mt-1'}>Live spillerliste direkte fra serverkonsollen.</p></div>
                <button onClick={refresh} disabled={loading || status !== 'running'} className={'px-4 py-2 rounded-lg font-semibold text-white disabled:opacity-50'} style={{ background: 'var(--nodexa-accent)' }}>{loading ? 'Opdaterer…' : 'Opdater'}</button>
            </div>
            <div className={'grid grid-cols-2 md:grid-cols-3 gap-3 mt-5'}>
                <div className={'rounded-lg p-4'} style={{ background: 'var(--nodexa-bg)', border: '1px solid var(--nodexa-border)' }}><div className={'text-xs uppercase text-gray-500'}>Online</div><div className={'text-2xl font-bold text-white'}>{online}</div></div>
                <div className={'rounded-lg p-4'} style={{ background: 'var(--nodexa-bg)', border: '1px solid var(--nodexa-border)' }}><div className={'text-xs uppercase text-gray-500'}>Slots</div><div className={'text-2xl font-bold text-white'}>{max ?? '—'}</div></div>
                <div className={'rounded-lg p-4 col-span-2 md:col-span-1'} style={{ background: 'var(--nodexa-bg)', border: '1px solid var(--nodexa-border)' }}><div className={'text-xs uppercase text-gray-500'}>Senest opdateret</div><div className={'text-sm font-semibold text-white mt-2'}>{updated ? updated.toLocaleTimeString() : 'Venter på data'}</div></div>
            </div>
        </div>
        {status !== 'running' ? <div className={'rounded-xl p-8 text-center text-gray-400'} style={{ background: 'var(--nodexa-card)', border: '1px solid var(--nodexa-border)' }}>Serveren skal være startet for at vise spillere.</div> : players.length === 0 ? <div className={'rounded-xl p-8 text-center text-gray-400'} style={{ background: 'var(--nodexa-card)', border: '1px solid var(--nodexa-border)' }}>{loading ? 'Henter spillerliste…' : online === 0 && updated ? 'Ingen spillere er online.' : 'Venter på svar fra Minecraft-serveren…'}</div> : <div className={'grid gap-3'}>{players.map(player => <div key={player} className={'rounded-xl p-4 flex flex-wrap items-center justify-between gap-3'} style={{ background: 'var(--nodexa-card)', border: '1px solid var(--nodexa-border)' }}><div className={'flex items-center gap-3'}><img src={`https://mc-heads.net/avatar/${encodeURIComponent(player)}/48`} width={48} height={48} className={'rounded-lg'} alt={player}/><div><div className={'font-bold text-white'}>{player}</div><div className={'text-xs text-green-400'}>● Online</div></div></div>{canCommand && <div className={'flex flex-wrap gap-2'}><button onClick={() => command('kick', player)} className={'px-3 py-2 rounded-md bg-gray-700 text-white text-xs'}>Kick</button><button onClick={() => command('op', player)} className={'px-3 py-2 rounded-md bg-gray-700 text-white text-xs'}>OP</button><button onClick={() => command('deop', player)} className={'px-3 py-2 rounded-md bg-gray-700 text-white text-xs'}>De-OP</button><button onClick={() => command('whitelist add', player)} className={'px-3 py-2 rounded-md bg-gray-700 text-white text-xs'}>Whitelist</button><button onClick={() => command('ban', player)} className={'px-3 py-2 rounded-md bg-red-700 text-white text-xs'}>Ban</button></div>}</div>)}</div>}
        <p className={'text-xs text-gray-500 mt-4'}>Listen opdateres automatisk hvert 15. sekund. Admin-knapper vises kun for brugere med console command-rettighed.</p>
    </div>;
};
