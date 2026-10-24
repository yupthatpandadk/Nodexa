import React, { FormEvent, useEffect, useRef, useState } from 'react';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import { ServerContext } from '@/state/server';
import { SocketEvent, SocketRequest } from '@/components/server/events';
import { usePermissions } from '@/plugins/usePermissions';

type ChatMessage = {
    id: number;
    type: 'chat' | 'join' | 'leave';
    player: string;
    message: string;
    time: Date;
};

let sequence = 0;

const stripConsoleFormatting = (line: string): string =>
    line
        .replace(/\u001b\[[0-9;]*m/g, '')
        .replace(/§[0-9A-FK-OR]/gi, '')
        .trim();

const parseMinecraftLine = (line: string): Omit<ChatMessage, 'id' | 'time'> | null => {
    const clean = stripConsoleFormatting(line);

    const chat = clean.match(/(?:^|:\s)(?:\[Not Secure\]\s*)?<([A-Za-z0-9_]{1,32})>\s+(.+)$/);
    if (chat) {
        return {
            type: 'chat',
            player: chat[1],
            message: chat[2].trim(),
        };
    }

    const join = clean.match(/(?:^|:\s)([A-Za-z0-9_]{1,32}) joined the game$/i);
    if (join) {
        return {
            type: 'join',
            player: join[1],
            message: 'joined the game',
        };
    }

    const leave = clean.match(/(?:^|:\s)([A-Za-z0-9_]{1,32}) left the game$/i);
    if (leave) {
        return {
            type: 'leave',
            player: leave[1],
            message: 'left the game',
        };
    }

    return null;
};

export default () => {
    const { connected, instance } = ServerContext.useStoreState((state) => state.socket);
    const status = ServerContext.useStoreState((state) => state.status.value);
    const serverName = ServerContext.useStoreState((state) => state.server.data!.name);
    const [canUseConsole] = usePermissions(['control.console']);

    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [draft, setDraft] = useState('');
    const scrollRef = useRef<HTMLDivElement>(null);

    const serverReady = connected && status === 'running';

    useEffect(() => {
        const handleConsole = (line: string) => {
            const parsed = parseMinecraftLine(line);
            if (!parsed) {
                return;
            }

            const next: ChatMessage = {
                ...parsed,
                id: ++sequence,
                time: new Date(),
            };

            setMessages((current) => [...current, next].slice(-150));
        };

        if (instance && connected) {
            instance.addListener(SocketEvent.CONSOLE_OUTPUT, handleConsole);
            instance.send(SocketRequest.SEND_LOGS);
        }

        return () => {
            if (instance) {
                instance.removeListener(SocketEvent.CONSOLE_OUTPUT, handleConsole);
            }
        };
    }, [instance, connected]);

    useEffect(() => {
        if (scrollRef.current) {
            scrollRef.current.scrollTop = scrollRef.current.scrollHeight;
        }
    }, [messages]);

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (!instance || !serverReady || !canUseConsole) {
            return;
        }

        const clean = draft.replace(/[\r\n]+/g, ' ').trim().slice(0, 240);
        if (!clean) {
            return;
        }

        instance.send('send command', 'say ' + clean);
        setDraft('');
    };

    return (
        <ServerContentBlock title={'MC Server Chat'}>
            <div className={'rounded-xl border border-gray-700 bg-gray-800 bg-opacity-40 shadow-lg overflow-hidden'}>
                <div className={'flex flex-col gap-3 border-b border-gray-700 px-5 py-5 sm:flex-row sm:items-center sm:justify-between'}>
                    <div>
                        <div className={'flex items-center gap-3'}>
                            <h1 className={'font-header text-xl font-semibold text-gray-50'}>MC Server Chat</h1>
                            <span className={\`inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold \${serverReady ? 'border-green-500 text-green-400' : 'border-gray-600 text-gray-400'}\`}>
                                <span className={\`mr-2 h-2 w-2 rounded-full \${serverReady ? 'bg-green-400' : 'bg-gray-500'}\`} />
                                {serverReady ? 'Live' : 'Offline'}
                            </span>
                        </div>
                        <p className={'mt-1 text-xs text-gray-400'}>
                            Se Minecraft-chatten fra {serverName} og skriv til spillerne direkte fra Nodexa.
                        </p>
                    </div>
                    <button
                        type={'button'}
                        onClick={() => setMessages([])}
                        className={'rounded-lg border border-gray-600 bg-gray-700 px-3 py-2 text-xs font-semibold text-gray-200 transition hover:bg-gray-600'}
                    >
                        Ryd visning
                    </button>
                </div>

                {!canUseConsole && (
                    <div className={'m-5 rounded-lg border border-red-700 bg-red-900 bg-opacity-20 px-4 py-3 text-sm text-red-300'}>
                        Du mangler permission <code>control.console</code> for at bruge MC Server Chat.
                    </div>
                )}

                <div
                    ref={scrollRef}
                    className={'h-96 overflow-y-auto bg-gray-900 bg-opacity-40 px-4 py-4 sm:px-5'}
                >
                    {messages.length === 0 ? (
                        <div className={'flex h-full items-center justify-center text-center'}>
                            <div>
                                <div className={'text-sm font-semibold text-gray-300'}>
                                    {serverReady ? 'Ingen chatbeskeder endnu.' : 'Start serveren for at bruge live chat.'}
                                </div>
                                <div className={'mt-1 text-xs text-gray-500'}>
                                    Chat og join/leave-events fra Minecraft vises automatisk her.
                                </div>
                            </div>
                        </div>
                    ) : (
                        <div className={'space-y-2'}>
                            {messages.map((entry) =>
                                entry.type === 'chat' ? (
                                    <div key={entry.id} className={'flex gap-3 rounded-lg px-3 py-2 hover:bg-gray-800 hover:bg-opacity-50'}>
                                        <div className={'flex h-8 w-8 flex-none items-center justify-center rounded-lg bg-blue-600 bg-opacity-20 text-xs font-bold text-blue-300'}>
                                            {entry.player.slice(0, 1).toUpperCase()}
                                        </div>
                                        <div className={'min-w-0 flex-1'}>
                                            <div className={'flex flex-wrap items-baseline gap-x-2'}>
                                                <span className={'text-sm font-semibold text-blue-300'}>{entry.player}</span>
                                                <span className={'text-xs text-gray-600'}>{entry.time.toLocaleTimeString()}</span>
                                            </div>
                                            <div className={'mt-0.5 break-words text-sm text-gray-200'}>{entry.message}</div>
                                        </div>
                                    </div>
                                ) : (
                                    <div key={entry.id} className={'px-3 py-1.5 text-xs text-gray-500'}>
                                        <span className={entry.type === 'join' ? 'text-green-400' : 'text-red-400'}>●</span>{' '}
                                        <strong className={'text-gray-400'}>{entry.player}</strong>{' '}
                                        {entry.type === 'join' ? 'joined the game' : 'left the game'} · {entry.time.toLocaleTimeString()}
                                    </div>
                                )
                            )}
                        </div>
                    )}
                </div>

                <form onSubmit={submit} className={'border-t border-gray-700 bg-gray-800 bg-opacity-50 p-3 sm:p-4'}>
                    <div className={'flex gap-2'}>
                        <input
                            value={draft}
                            onChange={(event) => setDraft(event.currentTarget.value)}
                            disabled={!serverReady || !canUseConsole}
                            maxLength={240}
                            autoComplete={'off'}
                            placeholder={serverReady ? 'Skriv en besked til Minecraft-serveren…' : 'Serveren er offline'}
                            className={'min-w-0 flex-1 rounded-lg border border-gray-600 bg-gray-900 px-4 py-2.5 text-sm text-gray-100 outline-none transition focus:border-blue-500 disabled:cursor-not-allowed disabled:opacity-50'}
                        />
                        <button
                            type={'submit'}
                            disabled={!serverReady || !canUseConsole || draft.trim().length === 0}
                            className={'rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-40'}
                        >
                            Send
                        </button>
                    </div>
                    <div className={'mt-2 text-xs text-gray-500'}>
                        Beskeder sendes med Minecrafts indbyggede <code>say</code>-kommando. Maks. 240 tegn.
                    </div>
                </form>
            </div>
        </ServerContentBlock>
    );
};
