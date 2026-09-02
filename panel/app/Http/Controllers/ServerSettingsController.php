<?php

namespace App\Http\Controllers;

use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ServerSettingsController extends Controller
{
    private function authorizeServer(Request $request, Server $server, string $permission): void
    {
        $user = $request->user();
        if ((bool) $user->is_admin || (int) $server->owner_id === (int) $user->id) return;

        $entry = $server->subusers()->where('user_id', $user->id)->first();
        $permissions = $entry?->permissions ?? [];
        abort_unless(in_array($permission, $permissions, true), 403, 'You do not have permission to perform this action.');
    }

    public function update(Request $request, Server $server)
    {
        $this->authorizeServer($request, $server, 'settings.update');
        $data = $request->validate([
            'name' => 'required|string|max:120',
        ]);

        $name = trim($data['name']);
        abort_if($name === '', 422, 'Servernavnet må ikke være tomt.');

        $server->update(['name' => $name]);

        return response()->json([
            'message' => 'Serverindstillingerne er gemt.',
            'server' => $server->fresh(),
        ]);
    }
}
