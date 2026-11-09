<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Nodexa\NodexaEventService;
use Pterodactyl\Services\Nodexa\ProxmoxIntegrationService;
use RuntimeException;

class ProxmoxController extends Controller
{
    public function __construct(
        private ProxmoxIntegrationService $proxmox,
        private NodexaEventService $events,
    ) {
    }

    private function ownerOnly(Request $request): void
    {
        // PVE API credentials can access an entire hypervisor cluster.
        // Do not grant access through ordinary support/staff/admin roles.
        abort_unless((bool) $request->user()?->root_admin, 403);
    }

    public function index(Request $request): View
    {
        $this->ownerOnly($request);
        $ready = Schema::hasTable('nodexa_proxmox_connections');
        $connections = $ready
            ? DB::table('nodexa_proxmox_connections')
                ->select('id', 'name', 'endpoint', 'token_id', 'verify_tls',
                    'enabled', 'status', 'last_error', 'last_checked_at',
                    'snapshot_at', 'nodes_snapshot', 'guests_snapshot')
                ->orderBy('name')->get()
            : collect();

        foreach ($connections as $connection) {
            $nodes = json_decode((string) $connection->nodes_snapshot, true);
            $guests = json_decode((string) $connection->guests_snapshot, true);
            $connection->nodes = is_array($nodes) ? $nodes : [];
            $connection->guests = is_array($guests) ? $guests : [];

            // Snapshots are safe display-only metadata: no raw secrets.
            unset($connection->nodes_snapshot, $connection->guests_snapshot);
        }

        return view('admin.proxmox.index', [
            'connections' => $connections,
            'ready' => $ready,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ownerOnly($request);
        $data = $request->validate([
            'name' => 'required|string|min:2|max:120',
            'endpoint' => 'required|string|max:255',
            'token_id' => 'required|string|max:255',
            'token_secret' => 'required|string|max:5000',
        ]);

        try {
            $id = $this->proxmox->store(array_merge($data, [
                'verify_tls' => $request->boolean('verify_tls'),
                'enabled' => true,
            ]));
        } catch (RuntimeException $exception) {
            return back()->withInput($request->except('token_secret'))
                ->withErrors(['proxmox' => $exception->getMessage()]);
        }

        $this->events->audit((int) $request->user()->id, 'proxmox', 'connection.created',
            'Proxmox integration created', 'proxmox', $id, [], $request);

        return redirect()->route('admin.proxmox')
            ->with('success', 'Proxmox-forbindelsen er gemt. Tryk Test & synkronisér for at bekræfte API-adgangen.');
    }

    public function update(Request $request, int $connection): RedirectResponse
    {
        $this->ownerOnly($request);
        $data = $request->validate([
            'name' => 'required|string|min:2|max:120',
            'endpoint' => 'required|string|max:255',
            'token_id' => 'required|string|max:255',
            'token_secret' => 'nullable|string|max:5000',
        ]);

        try {
            $this->proxmox->store(array_merge($data, [
                'verify_tls' => $request->boolean('verify_tls'),
                'enabled' => $request->boolean('enabled'),
            ]), $connection);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['proxmox' => $exception->getMessage()]);
        }

        $this->events->audit((int) $request->user()->id, 'proxmox', 'connection.updated',
            'Proxmox integration updated', 'proxmox', $connection, [], $request);

        return redirect()->route('admin.proxmox')
            ->with('success', 'Proxmox-forbindelsen er opdateret. Test forbindelsen igen.');
    }

    public function sync(Request $request, int $connection): RedirectResponse
    {
        $this->ownerOnly($request);

        try {
            $result = $this->proxmox->sync($connection);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['proxmox' => $exception->getMessage()]);
        }

        $this->events->audit((int) $request->user()->id, 'proxmox', 'connection.tested',
            'Proxmox API verified and inventory refreshed', 'proxmox', $connection,
            ['nodes' => $result['nodes'], 'guests' => $result['guests']], $request);

        return redirect()->route('admin.proxmox')
            ->with('success', 'Proxmox er forbundet: ' . $result['nodes']
                . ' noder og ' . $result['guests'] . ' VM’er/containere fundet.');
    }

    public function destroy(Request $request, int $connection): RedirectResponse
    {
        $this->ownerOnly($request);
        if (!Schema::hasTable('nodexa_proxmox_connections')) {
            return back()->withErrors(['proxmox' => 'Databaseopdateringen mangler.']);
        }

        $deleted = DB::table('nodexa_proxmox_connections')->where('id', $connection)->delete();
        if (!$deleted) {
            return back()->withErrors(['proxmox' => 'Proxmox-forbindelsen blev ikke fundet.']);
        }

        $this->events->audit((int) $request->user()->id, 'proxmox', 'connection.deleted',
            'Proxmox integration disconnected', 'proxmox', $connection, [], $request);

        return redirect()->route('admin.proxmox')
            ->with('success', 'Forbindelsen er fjernet fra Nodexa. Ingen VM’er eller containere er blevet slettet.');
    }
}
