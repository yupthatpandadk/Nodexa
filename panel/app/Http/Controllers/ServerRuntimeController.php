<?php
namespace App\Http\Controllers;

use App\Models\Server;
use App\Services\DaemonClient;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ServerRuntimeController extends Controller
{
    private function authorizeServer(Request $request, Server $server, string $permission): void
    {
        $user = $request->user();
        if ((bool)$user->is_admin || $server->owner_id === $user->id) return;
        $permissions = $server->subusers()->where('user_id', $user->id)->value('permissions') ?? [];
        abort_unless(in_array($permission, $permissions, true), 403, 'You do not have permission to perform this action.');
    }

    public function stats(Request $request, Server $server, DaemonClient $daemon)
    {
        $this->authorizeServer($request, $server, 'console.read');
        return $daemon->stats($server->load('node'));
    }

    public function logs(Request $request, Server $server, DaemonClient $daemon)
    {
        $this->authorizeServer($request, $server, 'console.read');
        return response($daemon->logs($server->load('node'), (int) $request->integer('tail', 200)), 200)
            ->header('Content-Type', 'text/plain; charset=utf-8');
    }

    public function files(Request $request, Server $server, DaemonClient $daemon)
    {
        $this->authorizeServer($request, $server, 'files.read');
        return $daemon->files($server->load('node'), $request->string('path', '/')->toString());
    }

    public function readFile(Request $request, Server $server, DaemonClient $daemon)
    {
        $this->authorizeServer($request, $server, 'files.read');
        return response($daemon->readFile($server->load('node'), $request->string('path')->toString()), 200)
            ->header('Content-Type', 'text/plain; charset=utf-8');
    }

    public function writeFile(Request $request, Server $server, DaemonClient $daemon)
    {
        $this->authorizeServer($request, $server, 'files.write');
        $request->validate(['path' => 'required|string', 'content' => 'present|string|max:8388608']);
        return $daemon->writeFile($server->load('node'), $request->string('path')->toString(), $request->input('content'));
    }

    public function mkdir(Request $request, Server $server, DaemonClient $daemon)
    {
        $this->authorizeServer($request, $server, 'files.write');
        $data = $request->validate(['path' => 'required|string']);
        return $daemon->mkdir($server->load('node'), $data['path']);
    }

    public function deleteFile(Request $request, Server $server, DaemonClient $daemon)
    {
        $this->authorizeServer($request, $server, 'files.write');
        $data = $request->validate(['path' => 'required|string']);
        return $daemon->deleteFile($server->load('node'), $data['path']);
    }

    public function backup(Request $request, Server $server, DaemonClient $daemon)
    {
        $this->authorizeServer($request, $server, 'backups.create');
        $data = $request->validate(['name' => 'nullable|string|max:100']);
        return $daemon->backup($server->load('node'), $data['name'] ?? 'backup');
    }
}
