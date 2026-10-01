<?php

namespace Pterodactyl\Http\Controllers\Api\Nodexa;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Http\Controllers\Controller;

class AccountController extends Controller
{
    public function profile(Request $request): JsonResponse
    {
        $this->scope($request, 'profile.read');
        $user = $request->user();

        return response()->json([
            'data' => [
                'uuid' => $user->uuid,
                'username' => $user->username,
                'email' => $user->email,
                'name' => $user->name,
                'two_factor' => (bool) $user->use_totp,
            ],
        ]);
    }

    public function servers(Request $request): JsonResponse
    {
        $this->scope($request, 'servers.read');

        $servers = $request->user()->accessibleServers()
            ->with(['node', 'allocation'])
            ->get()
            ->map(fn ($server) => [
                'uuid' => $server->uuid,
                'identifier' => $server->uuidShort,
                'name' => $server->name,
                'status' => $server->status,
                'node' => $server->node?->name,
                'address' => $server->allocation ? $server->allocation->alias . ':' . $server->allocation->port : null,
                'limits' => [
                    'memory' => $server->memory,
                    'disk' => $server->disk,
                    'cpu' => $server->cpu,
                    'backups' => $server->backup_limit,
                ],
            ]);

        return response()->json(['data' => $servers]);
    }

    public function invoices(Request $request): JsonResponse
    {
        $this->scope($request, 'billing.read');

        $invoices = DB::table('nodexa_invoices')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json(['data' => $invoices]);
    }

    private function scope(Request $request, string $scope): void
    {
        $scopes = $request->attributes->get('nodexa_api_scopes', []);
        abort_unless(in_array($scope, $scopes, true), 403, 'API token is missing the required scope.');
    }
}
