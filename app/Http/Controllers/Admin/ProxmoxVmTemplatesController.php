<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Nodexa\NodexaEventService;
use Pterodactyl\Services\Nodexa\ProxmoxVmTemplateService;
use RuntimeException;

class ProxmoxVmTemplatesController extends Controller
{
    public function __construct(
        private ProxmoxVmTemplateService $templates,
        private NodexaEventService $events,
    ) {
    }

    private function ownerOnly(Request $request): void
    {
        abort_unless((bool) $request->user()?->root_admin, 403);
    }

    public function index(Request $request): View
    {
        $this->ownerOnly($request);

        $ready = Schema::hasTable('nodexa_proxmox_templates')
            && Schema::hasTable('nodexa_proxmox_deployments');
        $connections = Schema::hasTable('nodexa_proxmox_connections')
            ? DB::table('nodexa_proxmox_connections')
                ->where('enabled', true)
                ->get(['id', 'name', 'status', 'endpoint'])
            : collect();

        $mapped = $ready
            ? DB::table('nodexa_proxmox_templates as t')
                ->join('nodexa_proxmox_connections as c', 'c.id', '=', 't.connection_id')
                ->where('t.enabled', true)->where('c.enabled', true)
                ->orderBy('t.preset_key')
                ->get(['t.id', 't.preset_key', 't.connection_id', 't.node',
                    't.source_vmid', 'c.name as connection_name'])
            : collect();

        $deployments = $ready
            ? DB::table('nodexa_proxmox_deployments as d')
                ->leftJoin('nodexa_proxmox_connections as c', 'c.id', '=', 'd.connection_id')
                ->orderByDesc('d.id')->limit(30)
                ->get(['d.id', 'd.preset_key', 'd.name', 'd.node', 'd.vmid',
                    'd.status', 'd.last_error', 'd.created_at',
                    'd.finished_at', 'c.name as connection_name'])
            : collect();

        return view('admin.proxmox.templates', [
            'ready' => $ready,
            'connections' => $connections,
            'mapped' => $mapped,
            'deployments' => $deployments,
            'presets' => $this->templates->catalog(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ownerOnly($request);
        $data = $request->validate([
            'connection_id' => 'required|integer|min:1',
            'preset_key' => 'required|string|max:48',
            'node' => 'required|string|max:80',
            'source_vmid' => 'required|integer|min:100|max:999999999',
        ]);

        try {
            $this->templates->configureTemplate(
                (int) $data['connection_id'],
                $data['preset_key'],
                $data['node'],
                (int) $data['source_vmid']
            );
        } catch (RuntimeException $exception) {
            return back()->withInput()->withErrors(['templates' => $exception->getMessage()]);
        }

        $this->events->audit((int) $request->user()->id, 'proxmox', 'vm_template.configured',
            'Proxmox QEMU template linked to Nodexa OS preset',
            'proxmox', (int) $data['connection_id'],
            ['preset' => $data['preset_key'], 'source_vmid' => (int) $data['source_vmid']],
            $request);

        return redirect()->route('admin.proxmox.templates')
            ->with('success', 'OS-skabelonen er aktiv. Du kan nu oprette VM’er fra den.');
    }

    public function destroy(Request $request, int $template): RedirectResponse
    {
        $this->ownerOnly($request);
        $this->templates->unlinkTemplate($template);

        return redirect()->route('admin.proxmox.templates')
            ->with('success', 'Skabelonen er fjernet fra kataloget. Eksisterende VM’er er ikke ændret.');
    }

    public function deploy(Request $request, int $template): RedirectResponse
    {
        $this->ownerOnly($request);
        $data = $request->validate([
            'name' => 'required|string|min:2|max:63|regex:/^[a-z0-9][a-z0-9-]*[a-z0-9]$/',
            'auto_start' => 'nullable|boolean',
            'request_id' => 'required|uuid',
        ]);

        // One-time form nonce prevents double-clicks, browser resubmission,
        // and automatic retries from silently creating multiple VMs.
        $idempotencyKey = 'nodexa:pve:deploy-form:'
            . (int) $request->user()->id . ':' . $data['request_id'];
        if (!Cache::add($idempotencyKey, true, 86400)) {
            return redirect()->route('admin.proxmox.templates')
                ->withErrors(['deployment' => 'Denne VM-oprettelse er allerede modtaget. Kontrollér VM-oprettelser nederst på siden.']);
        }

        try {
            $deploymentId = $this->templates->deploy(
                $template,
                $data['name'],
                $request->boolean('auto_start')
            );
        } catch (RuntimeException $exception) {
            return back()->withErrors(['deployment' => $exception->getMessage()]);
        }

        $this->events->audit((int) $request->user()->id, 'proxmox', 'vm.deployment_requested',
            'Proxmox template deployment requested', 'proxmox', $deploymentId,
            ['template_id' => $template], $request);

        return redirect()->route('admin.proxmox.templates')
            ->with('success',
                'VM-oprettelsen er registreret. Se den faktiske kloningsstatus nederst på siden.');
    }

    public function refresh(Request $request): RedirectResponse
    {
        $this->ownerOnly($request);

        // The scheduler uses the same distributed per-deployment locks.
        $this->templates->processPending(15);

        return redirect()->route('admin.proxmox.templates')
            ->with('success', 'VM-opgavernes status er kontrolleret.');
    }
}
