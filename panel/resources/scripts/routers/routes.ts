import React, { lazy } from 'react';
import ServerConsole from '@/components/server/console/ServerConsoleContainer';
import DatabasesContainer from '@/components/server/databases/DatabasesContainer';
import ScheduleContainer from '@/components/server/schedules/ScheduleContainer';
import UsersContainer from '@/components/server/users/UsersContainer';
import BackupContainer from '@/components/server/backups/BackupContainer';
import NetworkContainer from '@/components/server/network/NetworkContainer';
import StartupContainer from '@/components/server/startup/StartupContainer';
import FileManagerContainer from '@/components/server/files/FileManagerContainer';
import MinecraftPluginManager from '@/components/server/plugins/MinecraftPluginManager';
import MinecraftModManager from '@/components/server/mods/MinecraftModManager';
import SettingsContainer from '@/components/server/settings/SettingsContainer';
import AccountOverviewContainer from '@/components/dashboard/AccountOverviewContainer';
import AccountApiContainer from '@/components/dashboard/AccountApiContainer';
import AccountSSHContainer from '@/components/dashboard/ssh/AccountSSHContainer';
import ActivityLogContainer from '@/components/dashboard/activity/ActivityLogContainer';
import ServerActivityLogContainer from '@/components/server/ServerActivityLogContainer';

const FileEditContainer = lazy(() => import('@/components/server/files/FileEditContainer'));
const ScheduleEditContainer = lazy(() => import('@/components/server/schedules/ScheduleEditContainer'));

interface RouteDefinition {
    path: string;
    name: string | undefined;
    component: React.ComponentType;
    exact?: boolean;
}

interface ServerRouteDefinition extends RouteDefinition {
    permission: string | string[] | null;
    minecraftOnly?: boolean;
    moddedOnly?: boolean;
    addon?: 'minecraftPluginManager' | 'minecraftModManager';
}

interface Routes {
    account: RouteDefinition[];
    server: ServerRouteDefinition[];
}

export default {
    account: [
        { path: '/', name: 'Account', component: AccountOverviewContainer, exact: true },
        { path: '/api', name: 'API Credentials', component: AccountApiContainer },
        { path: '/ssh', name: 'SSH Keys', component: AccountSSHContainer },
        { path: '/activity', name: 'Activity', component: ActivityLogContainer },
    ],
    server: [
        { path: '/', permission: null, name: 'Console', component: ServerConsole, exact: true },
        { path: '/files', permission: 'file.*', name: 'Files', component: FileManagerContainer },
        {
            path: '/plugins',
            permission: 'file.create',
            name: 'Plugins',
            component: MinecraftPluginManager,
            minecraftOnly: true,
            addon: 'minecraftPluginManager',
        },
        {
            path: '/mods',
            permission: 'file.create',
            name: 'Mods',
            component: MinecraftModManager,
            moddedOnly: true,
            addon: 'minecraftModManager',
        },
        { path: '/files/:action(edit|new)', permission: 'file.*', name: undefined, component: FileEditContainer },
        { path: '/databases', permission: 'database.*', name: 'Databases', component: DatabasesContainer },
        { path: '/schedules', permission: 'schedule.*', name: 'Schedules', component: ScheduleContainer },
        { path: '/schedules/:id', permission: 'schedule.*', name: undefined, component: ScheduleEditContainer },
        { path: '/users', permission: 'user.*', name: 'Users', component: UsersContainer },
        { path: '/backups', permission: 'backup.*', name: 'Backups', component: BackupContainer },
        { path: '/network', permission: 'allocation.*', name: 'Network', component: NetworkContainer },
        { path: '/startup', permission: 'startup.*', name: 'Startup', component: StartupContainer },
        { path: '/settings', permission: ['settings.*', 'file.sftp'], name: 'Settings', component: SettingsContainer },
        { path: '/activity', permission: 'activity.*', name: 'Activity', component: ServerActivityLogContainer },
    ],
} as Routes;
