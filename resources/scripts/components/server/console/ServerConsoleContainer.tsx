import React, { memo } from 'react';
import { ServerContext } from '@/state/server';
import Can from '@/components/elements/Can';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import isEqual from 'react-fast-compare';
import Spinner from '@/components/elements/Spinner';
import Features from '@feature/Features';
import Console from '@/components/server/console/Console';
import StatGraphs from '@/components/server/console/StatGraphs';
import PowerButtons from '@/components/server/console/PowerButtons';
import ServerDetailsBlock from '@/components/server/console/ServerDetailsBlock';
import { Alert } from '@/components/elements/alert';

export type PowerAction = 'start' | 'stop' | 'restart' | 'kill';

const ServerConsoleContainer = () => {
    const name = ServerContext.useStoreState((state) => state.server.data!.name);
    const description = ServerContext.useStoreState((state) => state.server.data!.description);
    const status = ServerContext.useStoreState((state) => state.status.value);
    const isInstalling = ServerContext.useStoreState((state) => state.server.isInstalling);
    const isTransferring = ServerContext.useStoreState((state) => state.server.data!.isTransferring);
    const eggFeatures = ServerContext.useStoreState((state) => state.server.data!.eggFeatures, isEqual);
    const isNodeUnderMaintenance = ServerContext.useStoreState((state) => state.server.data!.isNodeUnderMaintenance);

    const statusLabel = status === 'running' ? 'Online' : status === 'offline' ? 'Offline' : status || 'Connecting';

    return (
        <ServerContentBlock title={'Console'}>
            {(isNodeUnderMaintenance || isInstalling || isTransferring) && (
                <Alert type={'warning'} className={'mb-5'}>
                    {isNodeUnderMaintenance
                        ? 'The node of this server is currently under maintenance and all actions are unavailable.'
                        : isInstalling
                        ? 'This server is currently running its installation process and most actions are unavailable.'
                        : 'This server is currently being transferred to another node and all actions are unavailable.'}
                </Alert>
            )}

            <div className={'rounded-xl border border-gray-700 bg-gray-800 bg-opacity-40 px-4 py-4 sm:px-5 sm:py-4 mb-3 shadow-lg'}>
                <div className={'flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between'}>
                    <div className={'min-w-0'}>
                        <div className={'flex flex-wrap items-center gap-3'}>
                            <h1 className={'font-header font-semibold text-xl sm:text-2xl text-gray-50 truncate'}>{name}</h1>
                            <span className={`inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold ${status === 'running' ? 'border-green-500 text-green-400' : status === 'offline' ? 'border-red-500 text-red-400' : 'border-yellow-500 text-yellow-400'}`}>
                                <span className={`mr-2 h-2 w-2 rounded-full ${status === 'running' ? 'bg-green-400' : status === 'offline' ? 'bg-red-500' : 'bg-yellow-400'}`} />
                                {statusLabel}
                            </span>
                        </div>
                        <p className={'mt-1 text-xs text-gray-400 line-clamp-1'}>
                            {description || 'Administrer server, ressourcer og konsol fra ét samlet kontrolpanel.'}
                        </p>
                    </div>
                    <Can action={['control.start', 'control.stop', 'control.restart']} matchAny>
                        <PowerButtons className={'flex w-full sm:w-auto sm:min-w-[300px] gap-2'} />
                    </Can>
                </div>
            </div>

            <div className={'rounded-xl border border-gray-700 bg-gray-800 bg-opacity-30 overflow-hidden mb-3 shadow-lg'}>
                <div className={'flex items-center justify-between border-b border-gray-700 px-4 py-3 sm:px-5'}>
                    <div>
                        <h2 className={'font-header font-semibold text-gray-50'}>Console <span className={'ml-1 text-xs font-normal text-green-400'}>● Live</span></h2>
                        
                    </div>
                    <div className={'text-xs text-gray-500'}>Realtime server output</div>
                </div>
                <div className={'p-2'}>
                    <Spinner.Suspense>
                        <Console />
                    </Spinner.Suspense>
                </div>
            </div>

            <div className={'mb-3'}>
                <ServerDetailsBlock />
            </div>
            <div className={'grid grid-cols-1 md:grid-cols-3 gap-3'}>
                <Spinner.Suspense>
                    <StatGraphs />
                </Spinner.Suspense>
            </div>
            <Features enabled={eggFeatures} />
        </ServerContentBlock>
    );
};

export default memo(ServerConsoleContainer, isEqual);
