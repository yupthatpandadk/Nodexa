import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { ServerContext } from '@/state/server';
import { SocketEvent, SocketRequest } from '@/components/server/events';
import { usePermissions } from '@/plugins/usePermissions';

interface PlayerListState {
    online: number;
    max: number;
    players: string[];
    updatedAt: Date | null;
}

const initialState: PlayerListState = {
    online: 0,
    max: 0,
    players: [],
    updatedAt: null,
};

const stripConsoleFormatting = (line: string): string =>
    line
        .replace(/\u001b\[[0-9;]*m/g, '')
        .replace(/§[0-9A-FK-OR]/gi, '')
        .trim();

const parsePlayerList = (line: string): Omit<PlayerListState, 'updatedAt'> | null => {
    const clean = stripConsoleFormatting(line);

    const java = clean.match(/There are\s+(\d+)\s+of a max of\s+(\d+)\s+players online(?:\s*:\s*(.*))?/i);
    const bedrock = clean.match(/There are\s+(\d+)\s*\/\s*(\d+)\s+players online(?:\s*:\s*(.*))?/i);
    const match = java || bedrock;

    if (!match) {
        return null;
    }

    const online = Number(match[1] || 0);
    const max = Number(match[2] || 0);
    const rawPlayers = (match[3] || '').trim();

    const players = online > 0 && rawPlayers.length > 0
        ? rawPlayers
            .split(',')
            .map((name) => name.trim())
            .filter((name) => /^[A-Za-z0-9_]{1,32}$/.test(name))
        : [];

    return { online, max, players };
};

export default () => {
    const { connected, instance } = ServerContext.useStoreState((state) => state.socket);
    const status = ServerContext.useStoreState((state) => state.status.value);
    const serverName = ServerContext.useStoreState((state) => state.server.data!.name);
    const [canUseConsole] = usePermissions(['control.console']);

    const [data, setData] = useState<PlayerListState>(initialState);
    const [waiting, setWaiting] = useState(false);
    const [message, setMessage] = useState('');
    const requestTimeout = useRef<number | null>(null);

    const clearRequestTimeout = () => {
        if (requestTimeout.current !== null) {
            window.clearTimeout(requestTimeout.current);
            requestTimeout.current = null;
        }
    };

    const refresh = useCallback(() => {
        if (!instance || !connected || status !== 'running' || !canUseConsole) {
            return;
        }

        setWaiting(true);
        setMessage('');
        instance.send('send command', 'list');

        clearRequestTimeout();
        requestTimeout.current = window.setTimeout(() => {
            setWaiting(false);
            setMessage('Minecraft svarede ikke på list-kommandoen. Kontrollér at serveren er helt startet.');
        }, 5000);
    }, [instance, connected, status, canUseConsole]);

    useEffect(() => {
        const handleConsole = (line: string) => {
            const parsed = parsePlayerList(line);
            if (!parsed) {
                return;
            }

            clearRequestTimeout();
            setWaiting(false);
            setMessage('');
            setData({
                ...parsed,
                updatedAt: new Date(),
            });
        };

        if (instance && connected) {
            instance.addListener(SocketEvent.CONSOLE_OUTPUT, handleConsole);
            instance.send(SocketRequest.SEND_LOGS);

            if (status === 'running' && canUseConsole) {
                window.setTimeout(refresh, 350);
            }
        }

        return () => {
            clearRequestTimeout();
            if (instance) {
                instance.removeListener(SocketEvent.CONSOLE_OUTPUT, handleConsole);
            }
        };
    }, [instance, connected, status, canUseConsole, refresh]);

    useEffect(() => {
        if (!connected || status !== 'running' || !canUseConsole) {
            return;
        }

        const interval = window.setInterval(refresh, 15000);
        return () => window.clearInterval(interval);
    }, [connected, status, canUseConsole, refresh]);

    const sortedPlayers = useMemo(
        () => [...data.players].sort((a, b) => a.localeCompare(b)),
        [data.players]
    );

    const serverReady = connected && status === 'running';

    return (
        <div className={'rounded-xl border border-gray-700 bg-gray-800 bg-opacity-40 shadow-lg overflow-hidden'}>
                <div className={'px-5 py-5 border-b border-gray-700'}>
                    <div className={'flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between'}>
                        <div>
                            <div className={'flex items-center gap-3'}>
                                <h1 className={'font-header text-xl font-semibold text-gray-50'}>Player List</h1>
                                <span className={`inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold ${serverReady ? 'border-green-500 text-green-400' : 'border-gray-600 text-gray-400'}`}>
                                    <span className={`mr-2 h-2 w-2 rounded-full ${serverReady ? 'bg-green-400' : 'bg-gray-500'}`} />
                                    {serverReady ? 'Live' : 'Server offline'}
                                </span>
                            </div>
                            <p className={'mt-1 text-xs text-gray-400'}>
                                Live spillere på {serverName}. Listen opdateres automatisk hvert 15. sekund.
                            </p>
                        </div>
                        <button
                            type={'button'}
                            onClick={refresh}
                            disabled={!serverReady || !canUseConsole || waiting}
                            className={'rounded-lg border border-gray-600 bg-gray-700 px-4 py-2 text-sm font-semibold text-gray-100 transition hover:bg-gray-600 disabled:cursor-not-allowed disabled:opacity-40'}
                        >
                            {waiting ? 'Opdaterer…' : 'Opdatér nu'}
                        </button>
                    </div>
                </div>

                <div className={'grid grid-cols-1 gap-3 p-5 sm:grid-cols-3'}>
                    <div className={'rounded-xl border border-gray-700 bg-gray-900 bg-opacity-40 p-4'}>
                        <div className={'text-xs font-semibold uppercase tracking-wider text-gray-500'}>Online</div>
                        <div className={'mt-2 text-3xl font-bold text-green-400'}>{data.online}</div>
                    </div>
                    <div className={'rounded-xl border border-gray-700 bg-gray-900 bg-opacity-40 p-4'}>
                        <div className={'text-xs font-semibold uppercase tracking-wider text-gray-500'}>Max spillere</div>
                        <div className={'mt-2 text-3xl font-bold text-gray-100'}>{data.max || '—'}</div>
                    </div>
                    <div className={'rounded-xl border border-gray-700 bg-gray-900 bg-opacity-40 p-4'}>
                        <div className={'text-xs font-semibold uppercase tracking-wider text-gray-500'}>Senest opdateret</div>
                        <div className={'mt-2 text-sm font-semibold text-gray-200'}>
                            {data.updatedAt ? data.updatedAt.toLocaleTimeString() : 'Afventer data'}
                        </div>
                    </div>
                </div>

                {message && (
                    <div className={'mx-5 mb-5 rounded-lg border border-yellow-600 bg-yellow-900 bg-opacity-20 px-4 py-3 text-sm text-yellow-300'}>
                        {message}
                    </div>
                )}

                {!canUseConsole ? (
                    <div className={'mx-5 mb-5 rounded-lg border border-red-700 bg-red-900 bg-opacity-20 px-4 py-3 text-sm text-red-300'}>
                        Du mangler permission <code>control.console</code> for at hente Player List.
                    </div>
                ) : sortedPlayers.length > 0 ? (
                    <div className={'grid grid-cols-1 gap-2 px-5 pb-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4'}>
                        {sortedPlayers.map((player) => (
                            <div
                                key={player}
                                className={'flex items-center gap-3 rounded-xl border border-gray-700 bg-gray-900 bg-opacity-35 px-4 py-3'}
                            >
                                <div className={'flex h-9 w-9 flex-none items-center justify-center rounded-lg bg-blue-600 bg-opacity-20 font-bold text-blue-300'}>
                                    {player.slice(0, 1).toUpperCase()}
                                </div>
                                <div className={'min-w-0'}>
                                    <div className={'truncate text-sm font-semibold text-gray-100'}>{player}</div>
                                    <div className={'mt-0.5 text-xs text-green-400'}>● Online</div>
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className={'mx-5 mb-5 rounded-xl border border-dashed border-gray-700 bg-gray-900 bg-opacity-20 px-5 py-10 text-center'}>
                        <div className={'text-sm font-semibold text-gray-300'}>
                            {serverReady ? (data.updatedAt ? 'Ingen spillere er online.' : 'Henter spillerlisten…') : 'Start serveren for at se spillerlisten.'}
                        </div>
                        <div className={'mt-1 text-xs text-gray-500'}>
                            Player List bruger Minecrafts indbyggede <code>list</code>-kommando.
                        </div>
                    </div>
                )}
        </div>
    );
};
