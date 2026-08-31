<?php
namespace App\Http\Controllers;

use App\Models\Server;
use App\Services\DaemonClient;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServerController extends Controller
{
    private function authorizeServer(Request $request, Server $server): void
    {
        abort_unless((bool)$request->user()->is_admin || $server->owner_id === $request->user()->id, 403);
    }

    public function index(Request $request)
    {
        $query = Server::with('node')->orderByDesc('created_at');
        if (!$request->user()->is_admin) $query->where('owner_id', $request->user()->id);
        return $query->paginate(25);
    }

    public function show(Request $request, Server $server)
    {
        $this->authorizeServer($request, $server);
        return $server->load('node');
    }

    public function store(Request $request, DaemonClient $daemon)
    {
        $data = $request->validate([
            'name'=>'required|string|max:120','node_id'=>'required|exists:nodes,id','docker_image'=>'required|string',
            'startup'=>'required|string','memory_mb'=>'required|integer|min:128','disk_mb'=>'required|integer|min:512','cpu_limit'=>'required|integer|min:0|max:1000','environment'=>'array',
            'owner_id'=>'nullable|integer|exists:users,id'
        ]);
        $ownerId = $request->user()->is_admin && isset($data['owner_id']) ? (int)$data['owner_id'] : $request->user()->id;
        unset($data['owner_id']);
        $server = DB::transaction(function () use ($data, $ownerId) {
            $last = Server::query()->lockForUpdate()->max('server_number') ?? 0;
            $number = $last + 1;
            return Server::create($data + [
                'uuid'=>(string) Str::uuid(), 'server_number'=>$number, 'identifier'=>'s'.$number,
                'owner_id'=>$ownerId, 'status'=>'installing'
            ]);
        });
        $daemon->createServer($server->load('node'));
        $server->update(['status'=>'offline']);
        return response()->json($server, 201);
    }

    public function power(Request $request, Server $server, DaemonClient $daemon)
    {
        $this->authorizeServer($request, $server);
        $data = $request->validate(['signal'=>'required|in:start,stop,restart,kill']);
        return $daemon->power($server->load('node'), $data['signal']);
    }

    public function command(Request $request, Server $server, DaemonClient $daemon)
    {
        $this->authorizeServer($request, $server);
        $data = $request->validate(['command'=>'required|string|max:4096']);
        return $daemon->command($server->load('node'), $data['command']);
    }
}
