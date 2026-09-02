<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Services\DaemonClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ServerSftpController extends Controller
{
    public function show(Request $request, Server $server)
    {
        $this->authorizeServer($request, $server);
        $user = $request->user();
        return response()->json([
            'host' => $server->node->fqdn,
            'port' => 2022,
            'username' => $this->username($server, $user),
            'password' => 'Use your Nodexa account password',
        ]);
    }

    public function sync(Request $request, Server $server, DaemonClient $daemon)
    {
        $this->authorizeServer($request, $server);
        $data = $request->validate(['password' => ['required','string','max:255']]);
        $user = $request->user();
        if (!Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => 'The account password is incorrect.']);
        }
        $daemon->syncSftp($server, $this->username($server, $user), $data['password']);
        return response()->json(['ok' => true, 'username' => $this->username($server, $user)]);
    }

    private function username(Server $server, $user): string
    {
        // Pterodactyl-style login: account-name.server-short-uuid
        // Example: panda.e68e4160
        $account = trim((string)($user->username ?: $user->id));
        $shortUuid = substr((string)$server->uuid, 0, 8);
        return sprintf('%s.%s', $account, $shortUuid);
    }

    private function authorizeServer(Request $request, Server $server): void
    {
        $user = $request->user();
        if ($user->is_admin || (int)$server->user_id === (int)$user->id) return;
        $allowed = $server->subusers()->where('user_id', $user->id)->exists();
        abort_unless($allowed, 403);
    }
}
