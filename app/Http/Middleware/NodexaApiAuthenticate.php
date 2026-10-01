<?php

namespace Pterodactyl\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class NodexaApiAuthenticate
{
    public function handle(Request $request, Closure $next): mixed
    {
        $header = (string) $request->bearerToken();
        if ($header === '') {
            throw new AccessDeniedHttpException('Missing API token.');
        }

        $token = DB::table('nodexa_api_tokens')
            ->where('token_hash', hash('sha256', $header))
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        if (!$token) {
            throw new AccessDeniedHttpException('Invalid API token.');
        }

        DB::table('nodexa_api_tokens')->where('id', $token->id)->update([
            'last_used_at' => now(),
            'updated_at' => now(),
        ]);

        $request->attributes->set('nodexa_api_token', $token);
        $request->attributes->set('nodexa_api_scopes', json_decode((string) $token->scopes, true) ?: []);
        $request->setUserResolver(fn () => \Pterodactyl\Models\User::query()->find($token->user_id));

        return $next($request);
    }
}
