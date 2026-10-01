<?php

namespace Pterodactyl\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Pterodactyl\Services\Nodexa\NodexaEventService;
use Symfony\Component\HttpFoundation\Response;

class NodexaAdminAudit
{
    public function __construct(private NodexaEventService $events)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)
            && !$request->route()?->named('admin.operations*')) {
            $route = (string) ($request->route()?->getName() ?? 'admin.unknown');
            $area = explode('.', $route)[1] ?? 'admin';

            $this->events->audit(
                $request->user()?->id,
                $area,
                $route,
                $request->method() . ' ' . $request->path(),
                'route',
                null,
                ['status' => $response->getStatusCode()],
                $request
            );
        }

        return $response;
    }
}
