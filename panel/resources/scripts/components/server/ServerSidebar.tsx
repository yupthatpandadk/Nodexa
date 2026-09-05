import React from 'react';
import { Link, NavLink } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faArchive,
    faCogs,
    faDatabase,
    faFileAlt,
    faHistory,
    faHome,
    faNetworkWired,
    faPlayCircle,
    faPuzzlePiece,
    faServer,
    faSlidersH,
    faTerminal,
    faUsers,
} from '@fortawesome/free-solid-svg-icons';
import Can from '@/components/elements/Can';
import { ServerContext } from '@/state/server';
import routes from '@/routers/routes';

interface Props {
    baseUrl: string;
    rootAdmin: boolean;
    internalId?: string | number;
}

const iconForPath = (path: string) => {
    switch (path) {
        case '/': return faTerminal;
        case '/files': return faFileAlt;
        case '/plugins':
        case '/mods': return faPuzzlePiece;
        case '/databases': return faDatabase;
        case '/schedules': return faHistory;
        case '/users': return faUsers;
        case '/backups': return faArchive;
        case '/network': return faNetworkWired;
        case '/startup': return faPlayCircle;
        case '/settings': return faSlidersH;
        case '/activity': return faHistory;
        default: return faServer;
    }
};

const routeUrl = (baseUrl: string, path: string) =>
    path === '/' ? baseUrl : `${baseUrl.replace(/\/$/, '')}/${path.replace(/^\//, '')}`;

export default ({ baseUrl, rootAdmin, internalId }: Props) => {
    const name = ServerContext.useStoreState((state) => state.server.data!.name);
    const eggName = ServerContext.useStoreState((state) => state.server.data!.eggName || '');
    const minecraftPluginManager = ServerContext.useStoreState((state) => state.server.data!.addons.minecraftPluginManager);
    const minecraftModManager = ServerContext.useStoreState((state) => state.server.data!.addons.minecraftModManager);
    const status = ServerContext.useStoreState((state) => state.status.value);
    const isMinecraft = /minecraft|paper|purpur|spigot|bukkit|folia|velocity|waterfall|bungee|forge|fabric/i.test(eggName);
    const isModdedMinecraft = /forge|fabric/i.test(eggName);

    return (
        <aside className={'hidden lg:flex lg:w-[232px] xl:w-[248px] lg:flex-col lg:flex-shrink-0 lg:sticky lg:top-0 lg:h-screen border-r'} style={{ borderColor: 'var(--nodexa-border)', background: 'linear-gradient(180deg, var(--nodexa-surface), var(--nodexa-bg))', boxShadow: '8px 0 36px rgba(var(--nodexa-accent-rgb), 0.025)' }}>
            <div className={'px-5 pt-5 pb-4 border-b'} style={{ borderColor: 'var(--nodexa-border)' }}>
                <Link to={'/'} className={'flex items-center gap-3 no-underline'}>
                    <span className={'flex h-10 w-10 items-center justify-center rounded-xl text-[#06100d] font-black text-lg shadow-lg'} style={{ border: '1px solid var(--nodexa-border-strong)', background: 'linear-gradient(145deg, var(--nodexa-accent-2), var(--nodexa-accent))', boxShadow: '0 8px 30px rgba(var(--nodexa-accent-rgb), 0.2)' }}>N</span>
                    <span><span className={'block text-gray-50 font-bold text-lg leading-none'}>Nodexa</span><span className={'block text-[9px] tracking-[0.18em] font-semibold mt-1'} style={{ color: 'var(--nodexa-accent)' }}>GAME SERVER CLOUD</span></span>
                </Link>
            </div>

            <div className={'px-4 pt-5'}>
                <p className={'px-2 mb-2 text-[10px] uppercase tracking-[0.16em] text-gray-500 font-semibold'}>Overview</p>
                <Link to={'/'} className={'nodexa-sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg border border-transparent text-gray-300 no-underline transition-colors'}><FontAwesomeIcon icon={faHome} className={'w-4 text-gray-500'} /><span>Dashboard</span></Link>
            </div>

            <div className={'px-4 pt-5 min-h-0 overflow-y-auto'}>
                <p className={'px-2 mb-2 text-[10px] uppercase tracking-[0.16em] text-gray-500 font-semibold'}>Server</p>
                <div className={'mb-3 rounded-xl border px-3 py-3'} style={{ borderColor: 'var(--nodexa-border)', background: 'linear-gradient(145deg, var(--nodexa-surface-2), var(--nodexa-surface))' }}>
                    <div className={'flex items-center justify-between gap-2'}><span className={'truncate text-sm font-semibold text-gray-100'}>{name}</span><span className={`h-2 w-2 rounded-full ${status === 'running' ? 'bg-green-400' : status === 'offline' ? 'bg-red-400' : 'bg-yellow-400'}`} /></div>
                    <p className={'mt-1 text-[11px] text-gray-500 capitalize'}>{status || 'Connecting'}</p>
                </div>

                <nav className={'space-y-1'}>
                    {routes.server
                        .filter(
                            (route) =>
                                !!route.name &&
                                (!route.minecraftOnly || isMinecraft) &&
                                (!route.moddedOnly || isModdedMinecraft) &&
                                (!route.addon ||
                                    (route.addon === 'minecraftPluginManager' && minecraftPluginManager) ||
                                    (route.addon === 'minecraftModManager' && minecraftModManager))
                        )
                        .map((route) => {
                            const item = <NavLink to={routeUrl(baseUrl, route.path)} exact={route.exact} activeClassName={'nodexa-sidebar-active'} className={'nodexa-sidebar-link flex items-center gap-3 px-3 py-2.5 rounded-lg border border-transparent text-gray-400 no-underline transition-all'}><FontAwesomeIcon icon={iconForPath(route.path)} className={'w-4 text-gray-500'} /><span>{route.name}</span></NavLink>;
                            return route.permission ? <Can key={route.path} action={route.permission} matchAny>{item}</Can> : <React.Fragment key={route.path}>{item}</React.Fragment>;
                        })}
                </nav>
            </div>

            <div className={'mt-auto px-4 py-5 border-t space-y-1'} style={{ borderColor: 'var(--nodexa-border)' }}>
                {rootAdmin && internalId && <a href={`/admin/servers/view/${internalId}`} className={'nodexa-sidebar-action flex items-center gap-3 px-3 py-2.5 rounded-lg border border-transparent text-gray-400 no-underline transition-colors'}><FontAwesomeIcon icon={faCogs} className={'w-4'} /><span>Server Admin</span></a>}
                <Link to={'/account'} className={'flex items-center gap-3 px-3 py-2.5 rounded-lg text-gray-400 hover:text-gray-100 hover:bg-white/5 no-underline transition-colors'}><FontAwesomeIcon icon={faUsers} className={'w-4'} /><span>Account</span></Link>
            </div>
        </aside>
    );
};
