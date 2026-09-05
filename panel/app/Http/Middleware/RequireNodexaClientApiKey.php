<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireNodexaClientApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->user()?->currentAccessToken();
        $name = (string) ($token?->name ?? '');

        abort_unless(
            $token !== null && str_starts_with($name, 'client-api:'),
            403,
            'Denne endpoint kræver en Nodexa Client API key (nxa_...).'
        );

        return $next($request);
    }
}
