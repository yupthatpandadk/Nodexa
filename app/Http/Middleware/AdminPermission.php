<?php

namespace Pterodactyl\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AdminPermission
{
    private const AREAS = [
        'settings' => 'settings',
        'api' => 'api',
        'mail' => 'mail',
        'updates' => 'updates',
        'databases' => 'databases',
        'locations' => 'locations',
        'nodes' => 'nodes',
        'servers' => 'servers',
        'users' => 'users',
        'mounts' => 'mounts',
        'nests' => 'nests',
        'roles' => 'roles',
    ];

    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();
        if (!$user) {
            throw new AccessDeniedHttpException();
        }

        if ($user->root_admin || $request->route()?->getName() === 'admin.index') {
            return $next($request);
        }

        $name = (string) $request->route()?->getName();
        if (!str_starts_with($name, 'admin.')) {
            throw new AccessDeniedHttpException();
        }

        $area = explode('.', substr($name, 6))[0] ?? '';
        $permissionArea = self::AREAS[$area] ?? null;
        if (!$permissionArea) {
            throw new AccessDeniedHttpException();
        }

        $action = in_array($request->method(), ['GET', 'HEAD'], true) ? 'view' : 'manage';
        if (!$user->hasPermission($permissionArea . '.' . $action)) {
            throw new AccessDeniedHttpException('You do not have permission to access this Nodexa administration area.');
        }

        return $next($request);
    }
}
