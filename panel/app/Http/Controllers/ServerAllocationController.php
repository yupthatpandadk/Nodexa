<?php
namespace App\Http\Controllers;

use App\Models\Allocation;
use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class ServerAllocationController extends Controller
{
    private function authorizeServer(Request $request, Server $server, string $permission): void
    {
        $user = $request->user();
        if ((bool) $user->is_admin || $server->owner_id === $user->id) return;
        $entry = $server->subusers()->where('user_id', $user->id)->first();
        abort_unless(in_array($permission, $entry?->permissions ?? [], true), 403);
    }

    public function index(Request $request, Server $server)
    {
        $this->authorizeServer($request, $server, 'allocation.read');
        return $server->allocations()->orderByDesc('is_primary')->orderBy('ip')->orderBy('port')->get();
    }

    public function store(Request $request, Server $server)
    {
        abort_unless((bool) $request->user()->is_admin, 403, 'Only administrators can assign allocations.');
        $data = $request->validate(['allocation_id'=>'required|integer|exists:allocations,id']);
        $allocation = Allocation::whereKey($data['allocation_id'])->where('node_id', $server->node_id)->firstOrFail();
        abort_if($allocation->server_id && $allocation->server_id !== $server->id, 409, 'Allocation is already assigned.');
        $allocation->server_id = $server->id;
        if (!$server->allocations()->where('is_primary', true)->exists()) $allocation->is_primary = true;
        $allocation->save();
        return $allocation;
    }

    public function primary(Request $request, Server $server, Allocation $allocation)
    {
        $this->authorizeServer($request, $server, 'allocation.update');
        abort_unless($allocation->server_id === $server->id, 404);
        DB::transaction(function () use ($server, $allocation) {
            $server->allocations()->update(['is_primary'=>false]);
            $allocation->update(['is_primary'=>true]);
        });
        return ['ok'=>true];
    }

    public function update(Request $request, Server $server, Allocation $allocation)
    {
        $this->authorizeServer($request, $server, 'allocation.update');
        abort_unless($allocation->server_id === $server->id, 404);
        $allocation->update($request->validate(['notes'=>'nullable|string|max:255']));
        return $allocation;
    }

    public function destroy(Request $request, Server $server, Allocation $allocation)
    {
        abort_unless((bool) $request->user()->is_admin, 403, 'Only administrators can unassign allocations.');
        abort_unless($allocation->server_id === $server->id, 404);
        abort_if($allocation->is_primary, 409, 'The primary allocation cannot be removed. Select another primary allocation first.');
        $allocation->update(['server_id'=>null,'notes'=>null,'is_primary'=>false]);
        return response()->noContent();
    }
}
