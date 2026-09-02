<?php
namespace App\Http\Controllers;

use App\Models\Allocation;
use App\Models\Server;
use App\Services\DaemonClient;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Throwable;

class ServerAllocationController extends Controller
{
    public function __construct(private DaemonClient $daemon) {}

    private function authorizeServer(Request $request, Server $server, string $permission): void
    {
        $user=$request->user(); if((bool)$user->is_admin||$server->owner_id===$user->id)return;
        $entry=$server->subusers()->where('user_id',$user->id)->first();
        abort_unless(in_array($permission,$entry?->permissions??[],true),403);
    }

    private function syncRuntime(Server $server): void
    {
        $server->load(['node','allocations']);
        $this->daemon->reconfigure($server);
    }

    public function index(Request $request,Server $server){$this->authorizeServer($request,$server,'allocation.read');return $server->allocations()->orderByDesc('is_primary')->orderBy('ip')->orderBy('port')->get();}

    public function store(Request $request,Server $server)
    {
        abort_unless((bool)$request->user()->is_admin,403,'Only administrators can assign allocations.');
        $data=$request->validate(['allocation_id'=>'required|integer|exists:allocations,id']);
        $allocation=Allocation::whereKey($data['allocation_id'])->where('node_id',$server->node_id)->firstOrFail();
        abort_if($allocation->server_id&&$allocation->server_id!==$server->id,409,'Allocation is already assigned.');
        $original=$allocation->only(['server_id','is_primary']);
        $allocation->server_id=$server->id;if(!$server->allocations()->where('is_primary',true)->exists())$allocation->is_primary=true;$allocation->save();
        try{$this->syncRuntime($server);}catch(Throwable $e){$allocation->update($original);throw $e;}
        return $allocation->fresh();
    }

    public function primary(Request $request,Server $server,Allocation $allocation)
    {
        $this->authorizeServer($request,$server,'allocation.update');abort_unless($allocation->server_id===$server->id,404);
        $oldPrimary=$server->allocations()->where('is_primary',true)->value('id');
        DB::transaction(function()use($server,$allocation){$server->allocations()->update(['is_primary'=>false]);$allocation->update(['is_primary'=>true]);});
        try{$this->syncRuntime($server);}catch(Throwable $e){DB::transaction(function()use($server,$oldPrimary){$server->allocations()->update(['is_primary'=>false]);if($oldPrimary)$server->allocations()->whereKey($oldPrimary)->update(['is_primary'=>true]);});throw $e;}
        return ['ok'=>true];
    }

    public function update(Request $request,Server $server,Allocation $allocation)
    {
        $this->authorizeServer($request,$server,'allocation.update');abort_unless($allocation->server_id===$server->id,404);
        // Notes are panel metadata only and do not require a container restart.
        $allocation->update($request->validate(['notes'=>'nullable|string|max:255']));return $allocation;
    }

    public function destroy(Request $request,Server $server,Allocation $allocation)
    {
        abort_unless((bool)$request->user()->is_admin,403,'Only administrators can unassign allocations.');abort_unless($allocation->server_id===$server->id,404);abort_if($allocation->is_primary,409,'The primary allocation cannot be removed. Select another primary allocation first.');
        $original=$allocation->only(['server_id','notes','is_primary']);$allocation->update(['server_id'=>null,'notes'=>null,'is_primary'=>false]);
        try{$this->syncRuntime($server);}catch(Throwable $e){$allocation->update($original);throw $e;}
        return response()->noContent();
    }
}
