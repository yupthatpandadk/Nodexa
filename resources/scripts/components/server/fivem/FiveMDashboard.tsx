import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { ServerContext } from '@/state/server';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import Can from '@/components/elements/Can';
import Spinner from '@/components/elements/Spinner';
import Console from '@/components/server/console/Console';
import ServerDetailsBlock from '@/components/server/console/ServerDetailsBlock';
import StatGraphs from '@/components/server/console/StatGraphs';
import PowerButtons from '@/components/server/console/PowerButtons';
import Features from '@feature/Features';
import { Alert } from '@/components/elements/alert';
import loadDirectory from '@/api/server/files/loadDirectory';
import styles from './style.module.css';

const FiveMDashboard = () => {
    const server = ServerContext.useStoreState((state) => state.server.data!);
    const status = ServerContext.useStoreState((state) => state.status.value);
    const isInstalling = ServerContext.useStoreState((state) => state.server.isInstalling);
    const [resourceCount, setResourceCount] = useState<number | null>(null);
    const [resourcesError, setResourcesError] = useState(false);
    const [activeMetric, setActiveMetric] = useState<'cpu' | 'memory' | 'network'>('cpu');
    const base = '/server/' + server.id;
    const label = status === 'running' ? 'Online' : status === 'offline' ? 'Offline' : 'Forbinder…';

    useEffect(() => {
        let active = true;
        const fetchResources = async () => {
            for (const path of ['/resources', '/server-data/resources']) {
                try {
                    const data = await loadDirectory(server.uuid, path);
                    if (active) setResourceCount(data.filter((item) => !item.isFile && !item.isSymlink).length);
                    return;
                } catch {
                    // FiveM installations can place resources in different roots.
                }
            }
            if (active) setResourcesError(true);
        };
        fetchResources();
        return () => { active = false; };
    }, [server.uuid]);

    return (
        <ServerContentBlock title={'FiveM Control'}>
            {(isInstalling || server.isTransferring || server.isNodeUnderMaintenance) && (
                <Alert type={'warning'} className={'mb-4'}>
                    {isInstalling
                        ? 'FiveM-serveren installeres. De fleste handlinger er midlertidigt utilgængelige.'
                        : server.isTransferring
                            ? 'Serveren bliver flyttet til en anden node.'
                            : 'Noden er under vedligeholdelse.'}
                </Alert>
            )}

            <section className={styles.hero} aria-label={'FiveM serveroverblik'}>
                <div className={styles.heroHeading}>
                    <span className={styles.eyebrow}>NODEXA / FIVEM CONTROL</span>
                    <div className={styles.headingRow}>
                        <div className={styles.monogram}>V</div>
                        <div className={styles.title}>
                            <h1>{server.name}</h1>
                            <p>{server.description || 'FiveM FXServer • Serveradministration'}</p>
                        </div>
                        <span className={status === 'running' ? styles.online : styles.offline}>
                            <i aria-hidden={'true'} /> {label}
                        </span>
                    </div>
                    <div className={styles.serverMeta}>
                        <span>FXServer / FiveM</span>
                        <span>Node: {server.node}</span>
                        <span>Server ID: {server.identifier}</span>
                    </div>
                </div>
                <Can action={['control.start', 'control.stop', 'control.restart']} matchAny>
                    <div className={styles.power}><PowerButtons className={'flex flex-wrap gap-2'} /></div>
                </Can>
            </section>

            <div className={styles.metrics} aria-label={'Live serverstatistik'}>
                <ServerDetailsBlock />
            </div>

            <nav className={styles.shortcuts} aria-label={'FiveM hurtige handlinger'}>
                <Can action={'file.read'}>
                    <Link to={base + '/resources'}><strong>Resources</strong><small>Se installerede scripts</small><span>↗</span></Link>
                </Can>
                <Can action={'file.read'}>
                    <Link to={base + '/files'}><strong>server.cfg og filer</strong><small>Filhåndtering</small><span>↗</span></Link>
                </Can>
                <Can action={'startup.read'}>
                    <Link to={base + '/startup'}><strong>FXServer Startup</strong><small>Miljø og opstart</small><span>↗</span></Link>
                </Can>
                <Can action={'backup.read'}>
                    <Link to={base + '/backups'}><strong>Backups</strong><small>Sikkerhedskopier</small><span>↗</span></Link>
                </Can>
            </nav>

            <div className={styles.grid}>
                <section className={styles.main}>
                    <div className={styles.panel}>
                        <div className={styles.panelHead}><div><span>01 / LIVE CONSOLE</span><h2>FXServer Console</h2></div><small>Realtime output</small></div>
                        <Spinner.Suspense><Console /></Spinner.Suspense>
                    </div>
                </section>
                <aside className={styles.sidebar}>
                    <div className={styles.panel}>
                        <div className={styles.panelHead}><div><span>SERVER / CAPACITY</span><h2>Ressourcer</h2></div></div>
                        <div className={styles.capacity}>
                            <div><span>CPU-limit</span><strong>{server.limits.cpu ? server.limits.cpu + '%' : 'Ubegrænset'}</strong></div>
                            <div><span>RAM-limit</span><strong>{server.limits.memory ? (server.limits.memory / 1024).toFixed(1) + ' GB' : 'Ubegrænset'}</strong></div>
                            <div><span>Disk-limit</span><strong>{server.limits.disk ? (server.limits.disk / 1024).toFixed(1) + ' GB' : 'Ubegrænset'}</strong></div>
                        </div>
                    </div>
                    <div className={styles.panel}>
                        <div className={styles.panelHead}><div><span>RESOURCE INVENTORY</span><h2>Server resources</h2></div></div>
                        <div className={styles.inventory}>
                            <strong>{resourceCount === null ? '—' : resourceCount}</strong>
                            <span>{resourcesError ? 'Ingen adgang til /resources endnu' : 'Mapper i /resources'}</span>
                            <p>Listen viser mapper på disken – ikke hvilke scripts der er startet. Administrér dem i Files eller txAdmin.</p>
                            <Can action={'file.read'}><Link to={base + '/resources'}>Åbn resource-oversigt ↗</Link></Can>
                        </div>
                    </div>
                    <div className={styles.panel}>
                        <div className={styles.panelHead}><div><span>FIVEM / OPERATIONS</span><h2>Administration</h2></div></div>
                        <p className={styles.help}>Administrér server.cfg, resources, databaser og opstartsvariabler direkte fra Nodexa. txAdmin kan fortsat bruges separat, hvis det er konfigureret på din server.</p>
                        <Can action={'database.read'}><Link className={styles.smallLink} to={base + '/databases'}>Databaser ↗</Link></Can>
                        <Can action={'schedule.read'}><Link className={styles.smallLink} to={base + '/schedules'}>Schedules ↗</Link></Can>
                    </div>
                </aside>
            </div>

            <section className={styles.panel + ' ' + styles.graphPanel}>
                <div className={styles.panelHead}>
                    <div><span>02 / LIVE TELEMETRY</span><h2>Ressourceforbrug</h2></div>
                    <div className={styles.metricTabs} role={'group'} aria-label={'Vælg ressourcegraf'}>
                        {(['cpu', 'memory', 'network'] as const).map((metric) => (
                            <button
                                key={metric}
                                type={'button'}
                                aria-pressed={activeMetric === metric}
                                className={activeMetric === metric ? styles.metricActive : ''}
                                onClick={() => setActiveMetric(metric)}
                            >
                                {metric === 'cpu' ? 'CPU' : metric === 'memory' ? 'RAM' : 'Netværk'}
                            </button>
                        ))}
                    </div>
                </div>
                <div className={styles.graphs}>
                    <Spinner.Suspense><StatGraphs activeMetric={activeMetric} compact /></Spinner.Suspense>
                </div>
            </section>
            <Features enabled={server.eggFeatures} />
        </ServerContentBlock>
    );
};

export default FiveMDashboard;
