<?php
namespace App\Http\Controllers;

use App\Models\Allocation;
use App\Models\Node;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AdminAllocationController extends Controller
{
    private function admin(Request $request): void { abort_unless((bool)$request->user()->is_admin, 403); }

    public function index(Request $request, Node $node)
    {
        $this->admin($request);
        return $node->allocations()->with('server:id,name,identifier')->orderBy('ip')->orderBy('port')->get();
    }

    public function store(Request $request, Node $node)
    {
        $this->admin($request);
        $data=$request->validate(['ip'=>'required|string|max:255','port'=>'required|integer|min:1|max:65535','alias'=>'nullable|string|max:255']);
        return $node->allocations()->create($data);
    }

    public function range(Request $request, Node $node)
    {
        $this->admin($request);
        $data=$request->validate(['ip'=>'required|string|max:255','start_port'=>'required|integer|min:1|max:65535','end_port'=>'required|integer|min:1|max:65535','alias'=>'nullable|string|max:255']);
        abort_if($data['end_port'] < $data['start_port'] || ($data['end_port']-$data['start_port']) > 1000, 422, 'Invalid port range (maximum 1001 ports).');
        $created=0;
        for($port=$data['start_port'];$port<=$data['end_port'];$port++) {
            Allocation::firstOrCreate(['node_id'=>$node->id,'ip'=>$data['ip'],'port'=>$port],['alias'=>$data['alias']??null]);
            $created++;
        }
        return ['created'=>$created];
    }

    public function destroy(Request $request, Node $node, Allocation $allocation)
    {
        $this->admin($request);
        abort_unless($allocation->node_id === $node->id, 404);
        abort_if($allocation->server_id, 409, 'Allocation is assigned to a server.');
        $allocation->delete();
        return response()->noContent();
    }
}
