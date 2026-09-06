<?php

namespace Pterodactyl\Http\Middleware;

use Illuminate\Http\Request;
use Pterodactyl\Support\NodexaPermissions;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AdminAuthenticate
{
    /**
     * Handle an incoming request.
     *
     * Root administrators always have full access. Non-root users can enter
     * the admin area when they have a Nodexa role containing the permission
     * required for the current admin section and request method.
     *
     * @throws AccessDeniedHttpException
     */
    public function handle(Request $request, \Closure $next): mixed
    {
        $user = $request->user();

        if (!$user) {
            throw new AccessDeniedHttpException();
        }

        if ($user->root_admin) {
            return $next($request);
        }

        if (!NodexaPermissions::hasAnyRole($user)) {
            throw new AccessDeniedHttpException();
        }

        $permission = NodexaPermissions::requiredForRequest($request);
        if (!NodexaPermissions::userHas($user, $permission)) {
            throw new AccessDeniedHttpException();
        }

        return $next($request);
    }
}
