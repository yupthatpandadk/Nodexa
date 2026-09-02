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
        $this->authorizeSftp($request, $server);
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
        $this->authorizeSftp($request, $server);
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
        $account = trim((string)($user->username ?: $user->id));
        return sprintf('%s.%s', $account, substr((string)$server->uuid, 0, 8));
    }

    private function authorizeSftp(Request $request, Server $server): void
    {
        $user = $request->user();
        if ((bool)$user->is_admin || (int)$server->owner_id === (int)$user->id || (int)$server->user_id === (int)$user->id) return;
        $subuser = $server->subusers()->where('user_id', $user->id)->first();
        abort_unless($subuser, 403);
        $permissions = is_array($subuser->permissions) ? $subuser->permissions : [];
        abort_unless(in_array('files.read', $permissions, true) || in_array('files.write', $permissions, true), 403, 'SFTP requires file access permission.');
    }
}
