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
import MinecraftPlayerList from '@/components/server/players/MinecraftPlayerList';
import { Alert } from '@/components/elements/alert';
import styles from './style.module.css';

export type PowerAction = 'start' | 'stop' | 'restart' | 'kill';

const ServerConsoleContainer = () => {
    const name = ServerContext.useStoreState((state) => state.server.data!.name);
    const description = ServerContext.useStoreState((state) => state.server.data!.description);
    const eggName = ServerContext.useStoreState((state) => state.server.data!.eggName);
    const eggIcon = ServerContext.useStoreState((state) => state.server.data!.eggIcon);
    const allocations = ServerContext.useStoreState((state) => state.server.data!.allocations);
    const minecraftPlayerList = ServerContext.useStoreState((state) => state.server.data!.addons.minecraftPlayerList || false);
    const status = ServerContext.useStoreState((state) => state.status.value);
    const isInstalling = ServerContext.useStoreState((state) => state.server.isInstalling);
    const isTransferring = ServerContext.useStoreState((state) => state.server.data!.isTransferring);
    const eggFeatures = ServerContext.useStoreState((state) => state.server.data!.eggFeatures, isEqual);
    const isNodeUnderMaintenance = ServerContext.useStoreState((state) => state.server.data!.isNodeUnderMaintenance);
    const [iconFailed, setIconFailed] = useState(false);
    const isMinecraft = /minecraft|paper|purpur|spigot|bukkit|folia|velocity|waterfall|bungee|forge|fabric/i.test(eggName);
    const showPlayers = isMinecraft && minecraftPlayerList;
    const allocation = allocations.find((item) => item.isDefault);
    const address = allocation ? `${allocation.alias || allocation.ip}:${allocation.port}` : 'Ingen allocation';

    useEffect(() => setIconFailed(false), [eggIcon]);

    const statusLabel = status === 'running' ? 'Online' : status === 'offline' ? 'Offline' : status || 'Connecting';
    const statusClass = status === 'running' ? styles.status_online : status === 'offline' ? styles.status_offline : styles.status_pending;

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
                <div className={styles.hero_content}>
                    <div className={styles.server_identity}>
                        <div className={styles.server_icon}>
                            {eggIcon && !iconFailed ? <img src={eggIcon} alt={`${eggName} logo`} onError={() => setIconFailed(true)} /> : <CubeIcon className={'w-8 h-8'} />}
                        </div>
                        <div className={'min-w-0'}>
                            <div className={'flex items-center flex-wrap gap-2'}>
                                <h1 className={'font-header font-bold text-2xl sm:text-3xl text-gray-50 leading-tight truncate'}>{name}</h1>
                                <span className={`${styles.status_pill} ${statusClass}`}><span className={styles.status_dot} />{statusLabel}</span>
                            </div>
                            <p className={'text-sm text-gray-300 mt-1'}>{description || `${eggName} server managed by Nodexa`}</p>
                            <div className={styles.hero_meta}><span>{eggName}</span><span className={styles.meta_separator}>•</span><span>{address}</span></div>
                        </div>
                    </div>
                    <Can action={['control.start', 'control.stop', 'control.restart']} matchAny>
                        <PowerButtons className={styles.hero_power} />
                    </Can>
                </div>
            </section>

            <div className={showPlayers ? styles.workspace_grid : styles.workspace_single}>
                <section className={styles.console_panel}>
                    <div className={styles.console_header}>
                        <div><div className={'flex items-center gap-2'}><span className={styles.console_mark} /><h2 className={'font-header font-semibold text-gray-100'}>Konsol</h2><span className={styles.live_indicator}><span className={styles.status_dot} />Live</span></div><p>Realtime server output og kommandoer</p></div>
                    </div>
                    <Spinner.Suspense><Console /></Spinner.Suspense>
                </section>
                {showPlayers && <MinecraftPlayerList embedded />}
            </div>

            <section className={styles.metrics_section}>
                <div className={styles.section_heading}><div><span>SERVER HEALTH</span><h2>Ressourceforbrug</h2></div><small>Live data fra Wings</small></div>
                <ServerDetailsBlock />
            </section>

            <section className={styles.graph_section}>
                <div className={styles.section_heading}><div><span>PERFORMANCE</span><h2>Historik</h2></div><small>Realtime grafer</small></div>
                <div className={'grid grid-cols-1 md:grid-cols-3 gap-3 sm:gap-4'}><Spinner.Suspense><StatGraphs /></Spinner.Suspense></div>
            </section>
            <Features enabled={eggFeatures} />
        </ServerContentBlock>
    );
};

export default memo(ServerConsoleContainer, isEqual);
