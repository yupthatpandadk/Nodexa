import React, { memo, useEffect, useState } from 'react';
import { CubeIcon } from '@heroicons/react/solid';
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
import styles from './style.module.css';

export type PowerAction = 'start' | 'stop' | 'restart' | 'kill';

const ServerConsoleContainer = () => {
    const name = ServerContext.useStoreState((state) => state.server.data!.name);
    const description = ServerContext.useStoreState((state) => state.server.data!.description);
    const eggName = ServerContext.useStoreState((state) => state.server.data!.eggName);
    const eggIcon = ServerContext.useStoreState((state) => state.server.data!.eggIcon);
    const status = ServerContext.useStoreState((state) => state.status.value);
    const isInstalling = ServerContext.useStoreState((state) => state.server.isInstalling);
    const isTransferring = ServerContext.useStoreState((state) => state.server.data!.isTransferring);
    const eggFeatures = ServerContext.useStoreState((state) => state.server.data!.eggFeatures, isEqual);
    const isNodeUnderMaintenance = ServerContext.useStoreState((state) => state.server.data!.isNodeUnderMaintenance);
    const [iconFailed, setIconFailed] = useState(false);

    useEffect(() => setIconFailed(false), [eggIcon]);

    const statusLabel = status === 'running' ? 'Online' : status === 'offline' ? 'Offline' : status || 'Connecting';

    return (
        <ServerContentBlock title={'Console'}>
            {(isNodeUnderMaintenance || isInstalling || isTransferring) && (
                <Alert type={'warning'} className={'mb-4'}>
                    {isNodeUnderMaintenance
                        ? 'The node of this server is currently under maintenance and all actions are unavailable.'
                        : isInstalling
                        ? 'This server is currently running its installation process and most actions are unavailable.'
                        : 'This server is currently being transferred to another node and all actions are unavailable.'}
                </Alert>
            )}

            <section className={styles.server_hero}>
                <div className={styles.server_identity}>
                    <div className={styles.server_icon}>
                        {eggIcon && !iconFailed ? (
                            <img src={eggIcon} alt={`${eggName} logo`} onError={() => setIconFailed(true)} />
                        ) : (
                            <CubeIcon className={'w-7 h-7'} />
                        )}
                    </div>
                    <div className={'min-w-0'}>
                        <div className={'flex items-center flex-wrap gap-2'}>
                            <h1 className={'font-header font-semibold text-2xl text-gray-50 leading-tight truncate'}>{name}</h1>
                            <span className={styles.status_pill}>
                                <span className={styles.status_dot} />
                                {statusLabel}
                            </span>
                        </div>
                        <p className={'text-sm text-gray-400 mt-1 line-clamp-2'}>
                            {description || `${eggName} server managed by Nodexa`}
                        </p>
                    </div>
                </div>
                <Can action={['control.start', 'control.stop', 'control.restart']} matchAny>
                    <PowerButtons className={'flex w-full sm:w-auto sm:min-w-[330px] gap-2'} />
                </Can>
            </section>

            <div className={'grid grid-cols-1 xl:grid-cols-[minmax(0,1.9fr)_minmax(360px,1fr)] gap-3 sm:gap-4 mb-4'}>
                <section className={styles.console_panel}>
                    <div className={styles.console_header}>
                        <div className={'flex items-center gap-2'}>
                            <h2 className={'font-header font-semibold text-gray-100'}>Console</h2>
                            <span className={styles.live_indicator}>
                                <span className={styles.status_dot} />
                                Live
                            </span>
                        </div>
                        <span className={'text-xs text-gray-500 hidden sm:inline'}>Realtime server output</span>
                    </div>
                    <Spinner.Suspense>
                        <Console />
                    </Spinner.Suspense>
                </section>

                <ServerDetailsBlock />
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
