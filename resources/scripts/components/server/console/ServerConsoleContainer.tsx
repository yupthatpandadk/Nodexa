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

            <div className={'rounded-xl border border-gray-700 bg-gray-800 bg-opacity-40 px-4 py-4 sm:px-6 sm:py-5 mb-5 shadow-lg'}>
                <div className={'flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between'}>
                    <div className={'min-w-0'}>
                        <div className={'flex items-center gap-3'}>
                            <span className={`inline-block h-2.5 w-2.5 rounded-full ${status === 'running' ? 'bg-green-400' : status === 'offline' ? 'bg-red-500' : 'bg-yellow-400'}`} />
                            <h1 className={'font-header font-semibold text-xl sm:text-2xl text-gray-50 truncate'}>{name}</h1>
                        </div>
                        <p className={'mt-1 text-xs sm:text-sm text-gray-400 line-clamp-2'}>
                            {description || 'Administrer server, ressourcer og konsol fra ét sted.'}
                        </p>
                    </div>
                    <Can action={['control.start', 'control.stop', 'control.restart']} matchAny>
                        <PowerButtons className={'flex w-full sm:w-auto sm:min-w-[320px] gap-2'} />
                    </Can>
                </div>
            </div>

            <ServerDetailsBlock className={'mb-5'} />

            <div className={'rounded-xl border border-gray-700 bg-gray-800 bg-opacity-30 p-2 sm:p-3 mb-5 shadow-lg'}>
                <Spinner.Suspense>
                    <Console />
                </Spinner.Suspense>
            </div>

            <div className={'grid grid-cols-1 md:grid-cols-3 gap-3 sm:gap-4'}>
                <Spinner.Suspense>
                    <StatGraphs />
                </Spinner.Suspense>
            </div>
            <Features enabled={eggFeatures} />
        </ServerContentBlock>
    );
};

export default memo(ServerConsoleContainer, isEqual);
