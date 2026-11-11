import React, { useEffect, useState } from 'react';
import http, { httpErrorToHuman } from '@/api/http';
import getServerResourceUsage, { ServerPowerState } from '@/api/server/getServerResourceUsage';
import { ServerContext } from '@/state/server';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import { Button } from '@/components/elements/button/index';
import { Dialog } from '@/components/elements/dialog';
import { Actions, useStoreActions } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import { Link } from 'react-router-dom';

interface EggOption {
    id: number;
    name: string;
    current: boolean;
}

interface EggOptionsResponse {
    current_egg_id: number;
    eggs: EggOption[];
    has_recent_backup: boolean;
    backup_required_hours: number;
}

/**
 * This section is shown only to Minecraft server owners. The backend enforces
 * the actual Minecraft Nest, ownership, stopped-server and backup checks.
 */
export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const identifier = ServerContext.useStoreState((state) => state.server.data!.id);
    const currentEgg = ServerContext.useStoreState((state) => state.server.data!.eggId);
    const socketPowerState = ServerContext.useStoreState((state) => state.status.value);
    const getServer = ServerContext.useStoreActions((actions) => actions.server.getServer);
    const { addFlash, clearFlashes } = useStoreActions((actions: Actions<ApplicationStore>) => actions.flashes);

    const [eggs, setEggs] = useState<EggOption[]>([]);
    const [selected, setSelected] = useState<number>(currentEgg);
    const [backupReady, setBackupReady] = useState(false);
    const [acknowledged, setAcknowledged] = useState(false);
    const [dialog, setDialog] = useState(false);
    const [pending, setPending] = useState(false);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [resourcePowerState, setResourcePowerState] = useState<ServerPowerState | null>(null);
    const [resourceChecked, setResourceChecked] = useState(false);

    const refresh = (resetChoice = false) => {
        setLoading(true);
        setError('');
        Promise.all([
            http.get<EggOptionsResponse>('/api/client/servers/' + uuid + '/settings/minecraft-eggs'),
            getServerResourceUsage(uuid).then((stats) => stats.status).catch(() => null),
        ]).then(([{ data }, power]) => {
            setEggs(data.eggs);
            setBackupReady(data.has_recent_backup);
            setResourcePowerState(power);
            setResourceChecked(true);
            setSelected((previous) => {
                if (resetChoice || !data.eggs.some((egg) => egg.id === previous)) {
                    return data.current_egg_id;
                }
                return previous;
            });
        }).catch((err) => {
            setError(httpErrorToHuman(err));
        }).finally(() => setLoading(false));
    };

    useEffect(() => {
        setAcknowledged(false);
        setSelected(currentEgg);
        setResourceChecked(false);
        refresh(true);
    }, [uuid]);

    const current = eggs.find((egg) => egg.id === currentEgg);
    const target = eggs.find((egg) => egg.id === selected);
    const chosenNewEgg = !!target && target.id !== currentEgg;

    // Prefer the socket's live status, otherwise use the latest resource API.
    // A null/unknown state must not silently count as "offline".
    const effectivePowerState = socketPowerState || (resourceChecked ? resourcePowerState : null);
    const offline = effectivePowerState === 'offline';
    const requirements = [
        {
            ok: chosenNewEgg,
            text: chosenNewEgg ? 'Nyt Egg valgt: ' + target?.name : 'Vælg et andet Egg end det nuværende (' + (current?.name || 'Minecraft') + ').',
        },
        {
            ok: offline,
            text: offline ? 'Serveren er stoppet.' : 'Serveren skal være stoppet helt.',
        },
        {
            ok: backupReady,
            text: backupReady
                ? 'Fuldført backup fra de seneste 24 timer er fundet.'
                : 'Opret en backup under Backups og vent, til den er fuldført.',
        },
        {
            ok: acknowledged,
            text: acknowledged ? 'Du har accepteret risikoen ved geninstallationen.' : 'Markér bekræftelsen om risiko for filændringer.',
        },
    ];
    const missing = requirements.filter((requirement) => !requirement.ok);
    const canChange = !loading && !pending && missing.length === 0;

    const applyChange = () => {
        if (!canChange) return;
        setPending(true);
        setDialog(false);
        clearFlashes('settings');

        http.post('/api/client/servers/' + uuid + '/settings/minecraft-egg', {
            egg_id: selected,
            confirm_reinstall: true,
        }).then(() => {
            addFlash({
                key: 'settings',
                type: 'success',
                message: 'Minecraft er skiftet til ' + (target?.name || 'det valgte Egg') + '. Geninstallationen er startet.',
            });
            setAcknowledged(false);
            getServer(uuid).catch(() => undefined);
        }).catch((err) => {
            addFlash({ key: 'settings', type: 'error', message: httpErrorToHuman(err) });
            refresh();
        }).finally(() => setPending(false));
    };

    return (
        <TitledGreyBox title={'Minecraft – skift servertype'} className={'mb-6 md:mb-10'}>
            <Dialog.Confirm
                open={dialog}
                title={'Bekræft Egg-skift og geninstallation'}
                confirm={'Ja, skift og geninstaller'}
                onClose={() => setDialog(false)}
                onConfirmed={applyChange}
            >
                Du skifter fra <strong>{current?.name || 'nuværende Egg'}</strong> til{' '}
                <strong>{target?.name || 'valgt Egg'}</strong>. Installationsscriptet kan ændre eller
                overskrive filer. Backuppen bevares, men plugins, mods og verdener kan kræve tilpasning.
            </Dialog.Confirm>

            <p className={'text-sm text-neutral-300 mb-4'}>
                Vælg Paper, Fabric, Forge eller et andet Egg fra serverens Minecraft Nest.
                Kun serverejere med Minecraft-servere kan skifte Egg.
            </p>
            {error ? <p role={'alert'} className={'text-sm text-red-300 mb-3'}>{error}</p> : null}

            <label htmlFor={'minecraft-egg-choice'} className={'block text-xs font-semibold text-neutral-300 mb-2'}>
                Minecraft Egg
            </label>
            <select
                id={'minecraft-egg-choice'}
                value={selected}
                disabled={loading || pending}
                onChange={(e) => { setSelected(Number(e.target.value)); setAcknowledged(false); }}
                className={'w-full p-3 rounded-md border border-neutral-600 bg-neutral-900 text-sm text-neutral-100'}
            >
                {eggs.map((egg) => (
                    <option key={egg.id} value={egg.id}>
                        {egg.name}{egg.current ? ' (nuværende)' : ''}
                    </option>
                ))}
            </select>

            <div className={'mt-4 rounded-md p-3 border border-neutral-700 bg-neutral-900'}>
                <p className={'text-xs font-semibold text-neutral-200 mb-3'}>Krav før Egg-skift</p>

                <ul className={'space-y-2'} aria-live={'polite'}>
                    {requirements.map((requirement, index) => (
                        <li key={index} className={'flex items-start gap-2 text-xs'}>
                            <span aria-hidden={'true'} className={requirement.ok ? 'text-green-300' : 'text-yellow-300'}>
                                {requirement.ok ? '✓' : '○'}
                            </span>
                            <span className={requirement.ok ? 'text-green-200' : 'text-neutral-300'}>
                                {requirement.text}
                            </span>
                        </li>
                    ))}
                </ul>

                <div className={'flex flex-wrap gap-3 mt-3 mb-4'}>
                    {!offline && <Link to={'/server/' + identifier}
                        className={'text-xs text-cyan-300 hover:underline'}>Gå til Console og stop serveren</Link>}
                    {!backupReady && <Link to={'/server/' + identifier + '/backups'}
                        className={'text-xs text-cyan-300 hover:underline'}>Gå til Backups</Link>}
                </div>

                <label className={'flex items-start text-xs text-neutral-300 cursor-pointer'}>
                    <input
                        type={'checkbox'}
                        checked={acknowledged}
                        onChange={(e) => setAcknowledged(e.target.checked)}
                        className={'mr-2 mt-0.5'}
                    />
                    Jeg forstår, at geninstallation kan ændre eller overskrive serverfiler,
                    og at mods, plugins eller serverkonfiguration kan kræve tilpasning.
                </label>
            </div>

            {!loading && !canChange && !pending && (
                <p className={'text-xs text-yellow-300 mt-3'} role={'status'}>
                    Knappen bliver aktiv, når alle fire krav ovenfor er opfyldt.
                    {offline && !backupReady ? ' Opdatér backupstatus, efter du har lavet en backup.' : ''}
                </p>
            )}

            <div className={'mt-4 flex items-center justify-between gap-3 flex-wrap'}>
                <button type={'button'} onClick={() => refresh()} disabled={loading || pending}
                    className={'text-xs text-neutral-300 hover:text-white underline'}>
                    {loading ? 'Kontrollerer status…' : 'Opdatér server-, Egg- og backupstatus'}
                </button>
                <Button.Danger variant={Button.Variants.Secondary} disabled={!canChange}
                    onClick={() => setDialog(true)}>
                    {pending ? 'Skifter Egg…' : 'Skift Egg og geninstaller'}
                </Button.Danger>
            </div>
        </TitledGreyBox>
    );
};
