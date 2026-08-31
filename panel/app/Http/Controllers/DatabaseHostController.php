<?php
namespace App\Http\Controllers;

use App\Models\DatabaseHost;
use App\Services\DatabaseProvisioner;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DatabaseHostController extends Controller
{
    private function admin(Request $request): void
    {
        abort_unless((bool)$request->user()?->is_admin, 403, 'Administrator permission required.');
    }

    public function index(Request $request)
    {
        $this->admin($request);
        return DatabaseHost::with('node:id,name')->withCount('databases')->orderBy('name')->get();
    }

    public function store(Request $request, DatabaseProvisioner $provisioner)
    {
        $this->admin($request);
        $data = $this->validated($request, true);
        $host = DatabaseHost::create($data);
        $this->checkHost($host, $provisioner);
        return response()->json($host->fresh()->load('node:id,name')->loadCount('databases'), 201);
    }

    public function update(Request $request, DatabaseHost $databaseHost, DatabaseProvisioner $provisioner)
    {
        $this->admin($request);
        $data = $this->validated($request, false);
        if (empty($data['password'] ?? null)) unset($data['password']);
        $databaseHost->update($data);
        $this->checkHost($databaseHost, $provisioner);
        return $databaseHost->fresh()->load('node:id,name')->loadCount('databases');
    }

    public function test(Request $request, DatabaseHost $databaseHost, DatabaseProvisioner $provisioner)
    {
        $this->admin($request);
        return $this->checkHost($databaseHost, $provisioner);
    }

    public function credentials(Request $request, DatabaseHost $databaseHost)
    {
        $this->admin($request);
        return ['username'=>$databaseHost->username,'password'=>$databaseHost->plainPassword()];
    }

    public function destroy(Request $request, DatabaseHost $databaseHost)
    {
        $this->admin($request);
        abort_if($databaseHost->databases()->exists(), 409, 'Move or delete databases on this host before deleting it.');
        $databaseHost->delete();
        return response()->noContent();
    }

    private function validated(Request $request, bool $passwordRequired): array
    {
        return $request->validate([
            'name'=>'required|string|max:120',
            'host'=>'required|string|max:255',
            'port'=>'required|integer|min:1|max:65535',
            'username'=>'required|string|max:64',
            'password'=>($passwordRequired?'required':'nullable').'|string|max:512',
            'remote_host'=>'nullable|string|max:255',
            'node_id'=>'nullable|integer|exists:nodes,id',
            'max_databases'=>'nullable|integer|min:1',
            'ssl'=>'boolean',
            'enabled'=>'boolean',
        ]);
    }

    private function checkHost(DatabaseHost $host, DatabaseProvisioner $provisioner): array
    {
        try {
            $version = $provisioner->test($host);
            $host->update(['last_checked_at'=>now(),'last_status'=>'online','last_error'=>null]);
            return ['status'=>'online','version'=>$version];
        } catch (\Throwable $e) {
            $host->update(['last_checked_at'=>now(),'last_status'=>'offline','last_error'=>$e->getMessage()]);
            return ['status'=>'offline','error'=>$e->getMessage()];
        }
    }
}
