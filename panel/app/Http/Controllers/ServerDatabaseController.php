<?php
namespace App\Http\Controllers;

use App\Models\Server;
use App\Models\ServerDatabase;
use App\Services\DatabaseProvisioner;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;

class ServerDatabaseController extends Controller
{
    private function authorizeServer(Request $r, Server $server): void { abort_unless($server->owner_id === $r->user()->id, 403); }

    public function index(Request $r, Server $server)
    {
        $this->authorizeServer($r, $server);
        return $server->databases()->select(['id','server_id','name','username','host','port','created_at'])->get();
    }

    public function store(Request $r, Server $server, DatabaseProvisioner $provisioner)
    {
        $this->authorizeServer($r, $server);
        $data = $r->validate(['name'=>['required','string','max:40','regex:/^[A-Za-z0-9_-]+$/']]);
        abort_unless($server->server_number, 409, 'Server does not have a Nodexa server number yet.');
        $dbName = 's'.$server->server_number.'_'.$data['name'];
        abort_if(ServerDatabase::where('name',$dbName)->exists(), 422, 'Database name already exists.');
        do { $username = 'u'.$server->server_number.'_'.Str::random(8); } while (ServerDatabase::where('username',$username)->exists());
        $password = Str::password(32, true, true, false, false);
        $provisioner->create($dbName, $username, $password);
        $database = ServerDatabase::create([
            'server_id'=>$server->id,'name'=>$dbName,'username'=>$username,'password'=>$password,
            'host'=>config('nodexa.database_host','127.0.0.1'),'port'=>(int)config('nodexa.database_port',3306)
        ]);
        return response()->json([
            'database'=>$database->only(['id','name','username','host','port','created_at']),
            'password'=>$password,
            'notice'=>'Password is returned once. Store it securely.'
        ], 201);
    }

    public function credentials(Request $r, Server $server, ServerDatabase $database)
    {
        $this->authorizeServer($r, $server);
        abort_unless($database->server_id === $server->id, 404);
        return ['name'=>$database->name,'username'=>$database->username,'password'=>$database->plainPassword(),'host'=>$database->host,'port'=>$database->port];
    }

    public function destroy(Request $r, Server $server, ServerDatabase $database, DatabaseProvisioner $provisioner)
    {
        $this->authorizeServer($r, $server);
        abort_unless($database->server_id === $server->id, 404);
        $provisioner->delete($database->name, $database->username);
        $database->delete();
        return response()->noContent();
    }
}
