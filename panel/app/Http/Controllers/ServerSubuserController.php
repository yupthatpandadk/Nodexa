<?php
namespace App\Http\Controllers;

use App\Models\Server;
use App\Models\Subuser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ServerSubuserController extends Controller
{
    private const ALLOWED = [
        'console.read','console.command','power.start','power.stop','power.restart',
        'files.read','files.write','backups.read','backups.create',
        'database.read','database.create','database.credentials','database.delete','database.*',
        'schedules.read','schedules.write','settings.read',
    ];

    private function manage(Request $request, Server $server): void
    {
        abort_unless((bool)$request->user()->is_admin || $server->owner_id === $request->user()->id, 403);
    }

    public function index(Request $request, Server $server)
    {
        $this->manage($request, $server);
        return $server->subusers()->with('user:id,name,email')->get();
    }

    public function store(Request $request, Server $server)
    {
        $this->manage($request, $server);
        $data = $request->validate(['email'=>'required|email','permissions'=>'required|array','permissions.*'=>'string']);
        $user = User::where('email', strtolower($data['email']))->firstOrFail();
        abort_if($user->id === $server->owner_id, 422, 'The server owner already has full access.');
        $permissions = array_values(array_unique(array_intersect($data['permissions'], self::ALLOWED)));
        abort_if(count($permissions) !== count(array_unique($data['permissions'])), 422, 'One or more permissions are invalid.');
        $entry = Subuser::updateOrCreate(
            ['server_id'=>$server->id,'user_id'=>$user->id],
            ['permissions'=>$permissions]
        );
        return response()->json($entry->load('user:id,name,email'), 201);
    }

    public function update(Request $request, Server $server, Subuser $subuser)
    {
        $this->manage($request, $server);
        abort_unless($subuser->server_id === $server->id, 404);
        $data = $request->validate(['permissions'=>'required|array','permissions.*'=>'string']);
        $permissions = array_values(array_unique(array_intersect($data['permissions'], self::ALLOWED)));
        abort_if(count($permissions) !== count(array_unique($data['permissions'])), 422, 'One or more permissions are invalid.');
        $subuser->update(['permissions'=>$permissions]);
        return $subuser->load('user:id,name,email');
    }

    public function destroy(Request $request, Server $server, Subuser $subuser)
    {
        $this->manage($request, $server);
        abort_unless($subuser->server_id === $server->id, 404);
        $subuser->delete();
        return response()->noContent();
    }
}
