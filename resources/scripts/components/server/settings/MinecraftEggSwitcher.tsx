import React, { useEffect, useState } from 'react';
import http, { httpErrorToHuman } from '@/api/http';
import { ServerContext } from '@/state/server';
import TitledGreyBox from '@/components/elements/TitledGreyBox';
import { Button } from '@/components/elements/button/index';
import { Dialog } from '@/components/elements/dialog';
import { Actions, useStoreActions } from 'easy-peasy';
import { ApplicationStore } from '@/state';

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

/** Only mounted for the owner of a Minecraft Nest server. */
export default () => {
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const currentEgg = ServerContext.useStoreState((state) => state.server.data!.eggId);
    const powerState = ServerContext.useStoreState((state) => state.status.value);
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

    const refresh = () => {
        setLoading(true);
        http.get<EggOptionsResponse>('/api/client/servers/' + uuid + '/settings/minecraft-eggs')
            .then(({ data }) => {
                setEggs(data.eggs);
                setBackupReady(data.has_recent_backup);
                setSelected(data.current_egg_id);
                setError('');
            })
            .catch((err) => setError(httpErrorToHuman(err)))
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        setAcknowledged(false);
        refresh();
    }, [uuid]);

    const current = eggs.find((egg) => egg.id === currentEgg);
    const target = eggs.find((egg) => egg.id === selected);
    const offline = powerState === 'offline';
    const canChange = !loading && !pending && offline && backupReady && acknowledged
        && selected !== currentEgg && !!target;

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
                <strong>{target?.name || 'valgt Egg'}</strong>. Serveren geninstalleres. Installationsscriptet
                kan overskrive eller ændre filer. Opret altid en backup før skiftet. Mods og plugins
                kan kræve tilpasning bagefter.
            </Dialog.Confirm>

            <p className={'text-sm text-neutral-300 mb-4'}>
                Vælg Paper, Fabric, Forge eller et andet Egg fra denne servers Minecraft Nest.
                Andre spil som FiveM har ikke adgang til denne funktion.
            </p>
            {error ? <p className={'text-sm text-red-300 mb-3'}>{error}</p> : null}

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
                <p className={'text-xs font-semibold text-neutral-200 mb-2'}>Før du skifter</p>
                <p className={'text-xs text-neutral-400 mb-2'}>
                    {offline ? 'Serveren er stoppet.' : 'Stop serveren helt under Console, før du fortsætter.'}
                </p>
                <p className={backupReady ? 'text-xs text-green-300 mb-2' : 'text-xs text-yellow-300 mb-2'}>
                    {backupReady
                        ? 'Der findes en fuldført backup fra de seneste 24 timer.'
                        : 'Opret en backup under Backups, og afvent at den bliver fuldført (maks. 24 timer gammel).'}
                </p>
                <label className={'flex items-start text-xs text-neutral-300 cursor-pointer'}>
                    <input
                        type={'checkbox'}
                        checked={acknowledged}
                        onChange={(e) => setAcknowledged(e.target.checked)}
                        className={'mr-2 mt-0.5'}
                    />
                    Jeg forstår, at en geninstallation kan ændre eller overskrive serverfiler,
                    og at mods, plugins eller serverkonfiguration kan kræve tilpasning.
                </label>
            </div>

            <div className={'mt-4 flex items-center justify-between gap-3 flex-wrap'}>
                <button type={'button'} onClick={refresh} disabled={loading || pending}
                    className={'text-xs text-neutral-300 hover:text-white underline'}>
                    Opdatér Eggs og backupstatus
                </button>
                <Button.Danger variant={Button.Variants.Secondary} disabled={!canChange}
                    onClick={() => setDialog(true)}>
                    {pending ? 'Skifter Egg…' : 'Skift Egg og geninstaller'}
                </Button.Danger>
            </div>
        </TitledGreyBox>
    );
};
