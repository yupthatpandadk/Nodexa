import React from 'react';
import { Link } from 'react-router-dom';
import { ServerContext } from '@/state/server';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import styles from './style.module.css';

const FiveMTxAdminContainer = () => {
    const server = ServerContext.useStoreState((state) => state.server.data!);
    const txAdmin = server.txAdmin;
    const base = '/server/' + server.id;
    const isReady = txAdmin?.state === 'ready_to_open' && !!txAdmin.url;
    const enabled = txAdmin?.enabled;
    const allocated = !!txAdmin?.allocated;

    const status = txAdmin?.state === 'disabled'
        ? 'Deaktiveret i Egg-opstart'
        : txAdmin?.state === 'missing_allocation'
            ? 'TxAdmin-port mangler en allocation'
            : txAdmin?.state === 'invalid_port'
                ? 'Ugyldig txAdmin-port'
                : txAdmin?.state === 'invalid_host'
                    ? 'Ingen gyldig offentlig adresse'
                    : isReady
                        ? 'Adresse konfigureret – drift ikke verificeret'
                        : 'Kræver konfiguration';

    return (
        <ServerContentBlock title={'FiveM txAdmin'}>
            <section className={styles.txHero}>
                <div>
                    <span className={styles.eyebrow}>NODEXA / FIVEM / TXADMIN</span>
                    <h1>txAdmin</h1>
                    <p>Det officielle administrationspanel til FXServer, med spillerstyring, resource-kontrol, scheduled restarts og avancerede værktøjer.</p>
                    <div className={styles.txStatus}><span className={isReady ? styles.txConfigured : styles.txAttention} />{status}</div>
                </div>
                {isReady ? (
                    <a className={styles.txLaunch} href={txAdmin!.url!} rel={'noopener noreferrer'} target={'_blank'}>
                        Åbn txAdmin ↗
                    </a>
                ) : (
                    <Link to={base + '/startup'} className={styles.txSecondary}>Gå til Startup →</Link>
                )}
            </section>

            <div className={styles.txGrid}>
                <section className={styles.txPanel}>
                    <header><span>01 / FORBINDELSE</span><h2>Konfiguration</h2></header>
                    <div className={styles.txFields}>
                        <div><span>FiveM Egg</span><strong>{server.isFiveMServer ? 'Registreret' : 'Ikke FiveM'}</strong></div>
                        <div><span>txAdmin aktiveret</span><strong>{enabled === null || enabled === undefined ? 'Ikke angivet i Egg' : enabled ? 'Ja' : 'Nej'}</strong></div>
                        <div><span>Webport</span><strong>{txAdmin?.port ?? 'Ukendt'}</strong></div>
                        <div><span>TCP-allocation</span><strong>{allocated ? 'Tildelt serveren' : 'Mangler'}</strong></div>
                    </div>
                    {isReady && (
                        <div className={styles.txAddress}>
                            <span>Adresse – åbnes i ny fane</span>
                            <a href={txAdmin!.url!} rel={'noopener noreferrer'} target={'_blank'}>
                                {txAdmin!.url}
                            </a>
                        </div>
                    )}
                    <p className={styles.txHelp}>
                        Nodexa viser kun et link, når txAdmin-porten er tildelt netop denne Pterodactyl-server.
                        At linket findes betyder ikke, at txAdmin kører eller kan nås gennem en firewall.
                    </p>
                </section>

                <section className={styles.txPanel}>
                    <header><span>02 / SIKKER OPSÆTNING</span><h2>Få txAdmin til at virke</h2></header>
                    <ol className={styles.txSteps}>
                        <li><b>1</b><div><strong>Aktivér txAdmin</strong><p>Åbn Startup. Hvis dit FiveM Egg understøtter <code>TXADMIN_ENABLED</code>, skal den stå til <code>1</code>. Ændringer kan kræve genstart.</p></div></li>
                        <li><b>2</b><div><strong>Tildel en separat TCP-port</strong><p>Din txAdmin-port (<code>{txAdmin?.port ?? 40120}</code> som udgangspunkt) skal være tilføjet under Pterodactyl Network og matche <code>TXHOST_TXA_PORT</code>.</p></div></li>
                        <li><b>3</b><div><strong>Start FXServer i txAdmin-tilstand</strong><p>FiveM Egg skal starte FXServer uden <code>+exec server.cfg</code>, når txAdmin er aktiv. Brug ikke en anden installationsopskrift på en eksisterende server uden backup.</p></div></li>
                        <li><b>4</b><div><strong>Åbn txAdmin og log ind</strong><p>Brug førstegangs-PIN fra Console og din Cfx.re-konto. txAdmin har sit eget login og selvstændige adminrettigheder.</p></div></li>
                    </ol>
                    <div className={styles.txLinks}>
                        <Link to={base + '/startup'}>Startup ↗</Link>
                        <Link to={base + '/network'}>Network ↗</Link>
                        <Link to={base}>Console ↗</Link>
                        <a href={'https://txadmin.org/docs/deployment/pterodactyl'} target={'_blank'} rel={'noopener noreferrer'}>txAdmin vejledning ↗</a>
                    </div>
                </section>
            </div>

            <section className={styles.txInfo}>
                <strong>Adgang og sikkerhed</strong>
                <p>
                    txAdmin åbnes med separat Cfx.re-/txAdmin-login. Nodexa kopierer ikke login, gemmer ikke
                    txAdmin-adgangskoder og omgår ikke txAdmins rettigheder. Hvis du anvender HTTP på en
                    offentlig port, anbefales en sikker HTTPS-reverse proxy og adgangsbegrænsning.
                    txAdmin-muligheder som bans, player management og resource restart udføres i det officielle txAdmin-panel.
                </p>
            </section>
        </ServerContentBlock>
    );
};

export default FiveMTxAdminContainer;
