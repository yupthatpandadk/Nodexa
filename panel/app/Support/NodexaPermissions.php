<?php

namespace Pterodactyl\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\User;

final class NodexaPermissions
{
    public const PERMISSIONS = [
        'admin.dashboard.view' => 'Se admin dashboard',
        'admin.api.view' => 'Se API-indstillinger',
        'admin.api.manage' => 'Administrer API-nøgler',
        'admin.locations.view' => 'Se lokationer',
        'admin.locations.manage' => 'Administrer lokationer',
        'admin.databases.view' => 'Se database hosts',
        'admin.databases.manage' => 'Administrer database hosts',
        'admin.settings.view' => 'Se systemindstillinger',
        'admin.settings.manage' => 'Administrer systemindstillinger',
        'admin.users.view' => 'Se brugere',
        'admin.users.manage' => 'Administrer brugere',
        'admin.servers.view' => 'Se alle servere',
        'admin.servers.manage' => 'Administrer alle servere',
        'admin.nodes.view' => 'Se nodes',
        'admin.nodes.manage' => 'Administrer nodes',
        'admin.mounts.view' => 'Se mounts',
        'admin.mounts.manage' => 'Administrer mounts',
        'admin.nests.view' => 'Se nests og eggs',
        'admin.nests.manage' => 'Administrer nests og eggs',
        'admin.addons.view' => 'Se addons',
        'admin.addons.manage' => 'Administrer addons',
        'admin.updates.view' => 'Se opdateringer',
        'admin.updates.manage' => 'Kør opdateringer',
    ];

    public static function userHas(User $user, string $permission): bool
    {
        if ($user->root_admin) {
            return true;
        }

        $roles = DB::table('nodexa_role_user')
            ->join('nodexa_roles', 'nodexa_roles.id', '=', 'nodexa_role_user.role_id')
            ->where('nodexa_role_user.user_id', $user->id)
            ->pluck('nodexa_roles.permissions');

        foreach ($roles as $rawPermissions) {
            $permissions = json_decode((string) $rawPermissions, true);
            if (!is_array($permissions)) {
                continue;
            }

            if (in_array('*', $permissions, true) || in_array($permission, $permissions, true)) {
                return true;
            }

            foreach ($permissions as $allowed) {
                if (is_string($allowed) && str_ends_with($allowed, '.*')) {
                    $prefix = substr($allowed, 0, -1);
                    if (str_starts_with($permission, $prefix)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    public static function hasAnyRole(User $user): bool
    {
        if ($user->root_admin) {
            return true;
        }

        return DB::table('nodexa_role_user')->where('user_id', $user->id)->exists();
    }

    public static function requiredForRequest(Request $request): string
    {
        $path = trim($request->path(), '/');
        $segments = explode('/', $path);
        $section = $segments[1] ?? '';
        $isRead = in_array(strtoupper($request->method()), ['GET', 'HEAD'], true);

        if ($section === '') {
            return 'admin.dashboard.view';
        }

        $map = [
            'api' => 'admin.api',
            'locations' => 'admin.locations',
            'databases' => 'admin.databases',
            'settings' => 'admin.settings',
            'users' => 'admin.users',
            'servers' => 'admin.servers',
            'nodes' => 'admin.nodes',
            'mounts' => 'admin.mounts',
            'nests' => 'admin.nests',
            'addons' => 'admin.addons',
            'updates' => 'admin.updates',
        ];

        $base = $map[$section] ?? 'admin.__unknown';
        return $base . ($isRead ? '.view' : '.manage');
    }
}
