<?php
namespace App\Http\Controllers;

use App\Models\DatabaseHost;
use App\Models\Server;
use App\Models\ServerDatabase;
use App\Services\DatabaseProvisioner;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ServerDatabaseController extends Controller
{
    private function authorizeServer(Request $request, Server $server, string $permission): void
    {
        $user = $request->user();
        if ((bool)$user->is_admin || $server->owner_id === $user->id) return;
        $entry = $server->subusers()->where('user_id', $user->id)->first();
        $permissions = $entry?->permissions ?? [];
        abort_unless(in_array('database.*',$permissions,true)||in_array($permission,$permissions,true),403);
    }

    public function index(Request $request, Server $server)
    {
        $this->authorizeServer($request,$server,'database.read');
        return $server->databases()->with('databaseHost:id,name,host,port')->get(['id','server_id','database_host_id','name','username','host','port','created_at']);
    }

    public function store(Request $request, Server $server, DatabaseProvisioner $provisioner)
    {
        $this->authorizeServer($request,$server,'database.create');
        $data=$request->validate(['name'=>['required','string','max:40','regex:/^[A-Za-z0-9_-]+$/'],'database_host_id'=>'nullable|integer|exists:database_hosts,id']);
        abort_unless($server->server_number,409,'Server does not have a Nodexa server number yet.');
        $host=$this->selectHost($server,$data['database_host_id']??null);
        abort_unless($host,409,'No enabled database host is configured for this server/node.');
        $dbName='s'.$server->server_number.'_'.$data['name'];
        abort_if(ServerDatabase::where('name',$dbName)->exists(),422,'Database name already exists.');
        do{$username='u'.$server->server_number.'_'.Str::random(8);}while(ServerDatabase::where('username',$username)->exists());
        $password=Str::password(length:32,letters:true,numbers:true,symbols:true,spaces:false);
        $provisioner->create($host,$dbName,$username,$password);
        $database=ServerDatabase::create(['server_id'=>$server->id,'database_host_id'=>$host->id,'name'=>$dbName,'username'=>$username,'password'=>$password,'host'=>$host->host,'port'=>$host->port]);
        return response()->json(['database'=>$database->load('databaseHost:id,name,host,port'),'password'=>$password],201);
    }

    public function credentials(Request $request, Server $server, ServerDatabase $database)
    {
        $this->authorizeServer($request,$server,'database.credentials'); abort_unless($database->server_id===$server->id,404);
        return ['name'=>$database->name,'username'=>$database->username,'password'=>$database->plainPassword(),'host'=>$database->databaseHost?->host??$database->host,'port'=>$database->databaseHost?->port??$database->port];
    }

    public function rotateCredentials(Request $request, Server $server, ServerDatabase $database, DatabaseProvisioner $provisioner)
    {
        $this->authorizeServer($request,$server,'database.credentials');
        abort_unless($database->server_id===$server->id,404);
        $host=$database->databaseHost;
        abort_unless($host,409,'Database host is missing.');
        $password=Str::password(length:32,letters:true,numbers:true,symbols:true,spaces:false);
        $provisioner->rotatePassword($host,$database->username,$password);
        $database->password=$password;
        $database->save();
        return ['name'=>$database->name,'username'=>$database->username,'password'=>$password,'host'=>$host->host,'port'=>$host->port];
    }

    public function openPhpMyAdmin(Request $request, Server $server, ServerDatabase $database)
    {
        $this->authorizeServer($request,$server,'database.read'); abort_unless($database->server_id===$server->id,404);
        $token=Str::random(64); Cache::put('nodexa:pma:'.$token,['user_id'=>$request->user()->id,'server_id'=>$server->id,'database_id'=>$database->id],now()->addSeconds(60));
        return ['url'=>url('/database-gateway/'.$token),'expires_in'=>60];
    }

    public function destroy(Request $request, Server $server, ServerDatabase $database, DatabaseProvisioner $provisioner)
    {
        $this->authorizeServer($request,$server,'database.delete'); abort_unless($database->server_id===$server->id,404);
        $host=$database->databaseHost; abort_unless($host,409,'Database host is missing.');
        $provisioner->delete($host,$database->name,$database->username); $database->delete(); return response()->noContent();
    }

    private function selectHost(Server $server, ?int $requested): ?DatabaseHost
    {
        if($requested){$host=DatabaseHost::whereKey($requested)->where('enabled',true)->first(); if($host)return $host;}
        $query=DatabaseHost::where('enabled',true)->withCount('databases')->orderBy('databases_count');
        $nodeHost=(clone $query)->where('node_id',$server->node_id)->get()->first(fn($h)=>$h->max_databases===null||$h->databases_count<$h->max_databases);
        return $nodeHost??$query->whereNull('node_id')->get()->first(fn($h)=>$h->max_databases===null||$h->databases_count<$h->max_databases);
    }
}
