<?php
namespace App\Http\Controllers;

use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AdminServerStartupController extends Controller
{
    private function admin(Request $request): void
    {
        abort_unless((bool)$request->user()->is_admin, 403, 'Administrator access required.');
    }

    public function show(Request $request, Server $server)
    {
        $this->admin($request);
        return response()->json([
            'id' => $server->id,
            'identifier' => $server->identifier,
            'name' => $server->name,
            'startup' => $server->startup,
            'docker_image' => $server->docker_image,
            'environment' => $server->environment ?? [],
        ]);
    }

    public function update(Request $request, Server $server)
    {
        $this->admin($request);
        $data = $request->validate([
            'startup' => 'required|string|max:12000',
            'docker_image' => 'required|string|max:500',
            'environment' => 'nullable|array',
        ]);
        $server->update([
            'startup' => $data['startup'],
            'docker_image' => $data['docker_image'],
            'environment' => $data['environment'] ?? [],
        ]);
        return $this->show($request, $server->fresh());
    }
}
