<?php
namespace App\Http\Controllers;

use App\Models\Server;
use App\Services\DaemonClient;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class ServerController extends Controller
{
    public function index(Request $r) { return Server::where('owner_id', $r->user()->id)->with('node')->paginate(25); }
    public function show(Request $r, Server $server) { abort_unless($server->owner_id === $r->user()->id, 403); return $server->load('node'); }

    public function store(Request $r, DaemonClient $daemon)
    {
        $data = $r->validate([
            'name'=>'required|string|max:120','node_id'=>'required|exists:nodes,id','docker_image'=>'required|string',
            'startup'=>'required|string','memory_mb'=>'required|integer|min:128','disk_mb'=>'required|integer|min:512','cpu_limit'=>'required|integer|min:0|max:1000','environment'=>'array'
        ]);
        $server = Server::create($data + ['uuid'=>(string) Str::uuid(),'owner_id'=>$r->user()->id,'status'=>'installing']);
        $daemon->createServer($server->load('node'));
        $server->update(['status'=>'offline']);
        return response()->json($server, 201);
    }

    public function power(Request $r, Server $server, DaemonClient $daemon)
    {
        abort_unless($server->owner_id === $r->user()->id, 403);
        $data = $r->validate(['signal'=>'required|in:start,stop,restart,kill']);
        return $daemon->power($server->load('node'), $data['signal']);
    }

    public function command(Request $r, Server $server, DaemonClient $daemon)
    {
        abort_unless($server->owner_id === $r->user()->id, 403);
        $data = $r->validate(['command'=>'required|string|max:4096']);
        return $daemon->command($server->load('node'), $data['command']);
    }
}
