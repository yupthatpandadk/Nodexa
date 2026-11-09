<?php

namespace Pterodactyl\Services\Nodexa;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Preset VM catalog with explicitly mapped Proxmox QEMU templates.
 *
 * Administrative use only; this is not a client-side provisioning API.
 * Never repeat a state-changing Proxmox request if its result is ambiguous.
 */
class ProxmoxVmTemplateService
{
    public const PRESETS = [
        'ubuntu-2404' => [
            'name' => 'Ubuntu Server 24.04 LTS',
            'group' => 'Linux',
            'description' => 'Ubuntu LTS med cloud-init og QEMU Guest Agent.',
            'icon' => 'ubuntu',
        ],
        'ubuntu-2204' => [
            'name' => 'Ubuntu Server 22.04 LTS',
            'group' => 'Linux',
            'description' => 'Velafprøvet Ubuntu LTS til servere og apps.',
            'icon' => 'ubuntu',
        ],
        'debian-13' => [
            'name' => 'Debian 13',
            'group' => 'Linux',
            'description' => 'Minimal Debian til web, containere og databaser.',
            'icon' => 'linux',
        ],
        'debian-12' => [
            'name' => 'Debian 12',
            'group' => 'Linux',
            'description' => 'Stabil Debian-installation til servere.',
            'icon' => 'linux',
        ],
        'rocky-9' => [
            'name' => 'Rocky Linux 9',
            'group' => 'Linux',
            'description' => 'Enterprise-orienteret Linux til serverdrift.',
            'icon' => 'linux',
        ],
        'almalinux-9' => [
            'name' => 'AlmaLinux 9',
            'group' => 'Linux',
            'description' => 'Enterprise-orienteret Linux med lang support.',
            'icon' => 'linux',
        ],
        'windows-2022' => [
            'name' => 'Windows Server 2022',
            'group' => 'Windows',
            'description' => 'Windows Server fra en klargjort, licenseret VM-template.',
            'icon' => 'windows',
        ],
        'windows-2025' => [
            'name' => 'Windows Server 2025',
            'group' => 'Windows',
            'description' => 'Windows Server fra en klargjort, licenseret VM-template.',
            'icon' => 'windows',
        ],
    ];

    public function catalog(): array
    {
        return self::PRESETS;
    }

    public function configureTemplate(
        int $connectionId,
        string $presetKey,
        string $node,
        int $sourceVmid
    ): void {
        if (!isset(self::PRESETS[$presetKey])) {
            throw new RuntimeException('Vælg en gyldig OS-skabelon.');
        }

        $node = $this->validateNode($node);
        $this->validateVmid($sourceVmid);

        $connection = $this->connection($connectionId);
        if (!$connection->enabled) {
            throw new RuntimeException('Proxmox-forbindelsen er deaktiveret.');
        }

        $config = $this->api($connection, 'GET',
            '/nodes/' . rawurlencode($node) . '/qemu/' . $sourceVmid . '/config'
        );

        if (!in_array($config['template'] ?? null, [1, '1', true], true)) {
            throw new RuntimeException(
                'Det valgte VM-ID er ikke markeret som en Proxmox QEMU-template. '
                . 'Opret en template på Proxmox, før du aktiverer skabelonen.'
            );
        }

        DB::table('nodexa_proxmox_templates')->updateOrInsert(
            ['connection_id' => $connectionId, 'preset_key' => $presetKey],
            [
                'node' => $node,
                'source_vmid' => $sourceVmid,
                'enabled' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function unlinkTemplate(int $templateId): void
    {
        DB::table('nodexa_proxmox_templates')->where('id', $templateId)->delete();
        // Deployment records remain for audit/status. No Proxmox VM is deleted.
    }

    /**
     * Allocate the destination ID under a per-connection DB row lock, then
     * send ONE clone request. An ambiguous POST is never retried.
     */
    public function deploy(int $templateId, string $name, bool $autoStart = true): int
    {
        $name = strtolower(trim($name));
        if (!preg_match('/^[a-z0-9][a-z0-9-]{1,62}$/', $name)
            || str_ends_with($name, '-')) {
            throw new RuntimeException(
                'VM-navnet skal bestå af 2-63 små bogstaver, tal eller bindestreger.'
            );
        }

        $deployment = DB::transaction(function () use ($templateId, $name, $autoStart) {
            $template = DB::table('nodexa_proxmox_templates')
                ->where('id', $templateId)->where('enabled', true)->first();
            if (!$template || !isset(self::PRESETS[$template->preset_key])) {
                throw new RuntimeException('VM-skabelonen er ikke aktiv.');
            }

            $connection = DB::table('nodexa_proxmox_connections')
                ->where('id', $template->connection_id)->lockForUpdate()->first();

            if (!$connection || !$connection->enabled) {
                throw new RuntimeException('Proxmox-forbindelsen er ikke aktiv.');
            }

            // Check again immediately before reserving a new VM. A
            // previously configured source may have been deleted or changed.
            $sourceConfig = $this->api($connection, 'GET',
                '/nodes/' . rawurlencode($template->node)
                . '/qemu/' . $template->source_vmid . '/config'
            );
            if (!in_array($sourceConfig['template'] ?? null, [1, '1', true], true)) {
                throw new RuntimeException(
                    'Kilde-VM’en er ikke længere en gyldig Proxmox-template.'
                );
            }

            // Proxmox's nextid is a suggestion, not a reservation. Holding
            // this connection row lock prevents Nodexa from reserving the
            // same ID for concurrent deployments on this integration.
            $nextId = $this->api($connection, 'GET', '/cluster/nextid');
            $candidate = (int) $nextId;
            $this->validateVmid($candidate);

            $lastReserved = (int) DB::table('nodexa_proxmox_deployments')
                ->where('connection_id', $connection->id)->max('vmid');
            $candidate = max($candidate, $lastReserved + 1);
            $this->validateVmid($candidate);

            $id = DB::table('nodexa_proxmox_deployments')->insertGetId([
                'connection_id' => $connection->id,
                'template_id' => $template->id,
                'preset_key' => $template->preset_key,
                'node' => $template->node,
                'source_vmid' => $template->source_vmid,
                'vmid' => $candidate,
                'name' => $name,
                'status' => 'reserved',
                'auto_start' => $autoStart,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return (int) $id;
        }, 3);

        $row = DB::table('nodexa_proxmox_deployments')->where('id', $deployment)->first();
        $connection = $this->connection((int) $row->connection_id);

        // Do not send a second clone automatically on timeout, HTTP 5xx or
        // unparseable responses; the VM may already have been created.
        DB::table('nodexa_proxmox_deployments')->where('id', $deployment)
            ->update(['status' => 'clone_requesting', 'updated_at' => now()]);

        try {
            $result = $this->api($connection, 'POST',
                '/nodes/' . rawurlencode($row->node)
                . '/qemu/' . $row->source_vmid . '/clone',
                [
                    'newid' => $row->vmid,
                    'name' => $row->name,
                    'full' => 1,
                ]
            );

            $upid = $this->validateUpid($result);
            DB::table('nodexa_proxmox_deployments')->where('id', $deployment)->update([
                'status' => 'cloning',
                'clone_upid' => $upid,
                'updated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            DB::table('nodexa_proxmox_deployments')->where('id', $deployment)->update([
                'status' => 'unknown',
                'last_error' => 'Proxmox kunne ikke bekræfte kloningen. '
                    . 'Kontrollér VM-ID ' . $row->vmid . ' i Proxmox, før du forsøger igen.',
                'updated_at' => now(),
            ]);
        }

        return $deployment;
    }

    public function processPending(int $limit = 20): int
    {
        // A worker/process can die after sending a mutation but before
        // saving its UPID. The outcome is unknown and must not be replayed.
        DB::table('nodexa_proxmox_deployments')
            ->whereIn('status', ['reserved', 'clone_requesting', 'start_requesting'])
            ->where('updated_at', '<', now()->subMinutes(10))
            ->update([
                'status' => 'unknown',
                'last_error' => 'Uafklaret VM-opgave. Kontrollér VM-ID i Proxmox før nyt forsøg.',
                'updated_at' => now(),
            ]);

        $ids = DB::table('nodexa_proxmox_deployments')
            ->whereIn('status', ['cloning', 'ready', 'starting'])
            ->orderBy('id')
            ->limit(max(1, min(50, $limit)))
            ->pluck('id');

        $processed = 0;
        foreach ($ids as $id) {
            $lock = Cache::lock('nodexa:pve:deploy:' . $id, 45);
            if (!$lock->get()) {
                continue;
            }

            try {
                $this->advance((int) $id);
                $processed++;
            } catch (Throwable $exception) {
                // Do not log raw Proxmox exceptions. Retain the existing
                // tracked state; the next scheduled check can retry GETs.
                report(new RuntimeException('Proxmox VM task check failed for deployment #' . $id));
            } finally {
                $lock->release();
            }
        }

        return $processed;
    }

    public function advance(int $deploymentId): void
    {
        $row = DB::table('nodexa_proxmox_deployments')->where('id', $deploymentId)->first();
        if (!$row || !in_array($row->status, ['cloning', 'ready', 'starting'], true)) {
            return;
        }

        $connection = $this->connection((int) $row->connection_id);
        if (!$connection->enabled) {
            return;
        }

        if ($row->status === 'cloning') {
            $task = $this->api($connection, 'GET', $this->taskPath($row, $row->clone_upid));
            if (($task['status'] ?? '') !== 'stopped') {
                return;
            }
            if (($task['exitstatus'] ?? '') !== 'OK') {
                $this->setStatus($row->id, 'failed', 'Kloningen fejlede i Proxmox. Kontrollér opgaveloggen på hypervisoren.');
                return;
            }

            $this->setStatus($row->id, 'ready');
            $row->status = 'ready';
        }

        if ($row->status === 'ready') {
            if (!$row->auto_start) {
                return;
            }

            // Mark the action as in flight BEFORE sending the POST.
            // A crash or ambiguous response must not trigger a second start.
            $this->setStatus($row->id, 'start_requesting');

            try {
                $upid = $this->validateUpid($this->api($connection, 'POST',
                    '/nodes/' . rawurlencode($row->node)
                    . '/qemu/' . $row->vmid . '/status/start'
                ));

                DB::table('nodexa_proxmox_deployments')
                    ->where('id', $row->id)->where('status', 'start_requesting')
                    ->update([
                        'status' => 'starting',
                        'start_upid' => $upid,
                        'updated_at' => now(),
                    ]);
            } catch (Throwable $exception) {
                $this->setStatus($row->id, 'unknown',
                    'Start blev ikke bekræftet. Kontrollér VM ' . $row->vmid . ' direkte i Proxmox.');
            }

            return;
        }

        if ($row->status === 'starting') {
            $task = $this->api($connection, 'GET', $this->taskPath($row, $row->start_upid));
            if (($task['status'] ?? '') !== 'stopped') {
                return;
            }

            if (($task['exitstatus'] ?? '') === 'OK') {
                DB::table('nodexa_proxmox_deployments')->where('id', $row->id)->update([
                    'status' => 'running',
                    'last_error' => null,
                    'finished_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                $this->setStatus($row->id, 'failed', 'VM-opstart fejlede i Proxmox.');
            }
        }
    }

    private function setStatus(int $id, string $status, ?string $error = null): void
    {
        DB::table('nodexa_proxmox_deployments')->where('id', $id)->update([
            'status' => $status,
            'last_error' => $error,
            'updated_at' => now(),
        ]);
    }

    private function taskPath(object $row, ?string $upid): string
    {
        $upid = $this->validateUpid($upid);

        return '/nodes/' . rawurlencode($row->node) . '/tasks/'
            . rawurlencode($upid) . '/status';
    }

    private function validateUpid($upid): string
    {
        if (!is_string($upid) || strlen($upid) > 512
            || !preg_match('/^UPID:[A-Za-z0-9._:@-]+$/', $upid)) {
            throw new RuntimeException('Proxmox returnerede ikke et gyldigt opgave-ID.');
        }

        return $upid;
    }

    private function validateNode(string $node): string
    {
        $node = trim($node);
        if (!preg_match('/^[a-z0-9][a-z0-9-]{0,79}$/i', $node)) {
            throw new RuntimeException('Node-navnet er ugyldigt.');
        }

        return $node;
    }

    private function validateVmid(int $vmid): void
    {
        if ($vmid < 100 || $vmid > 999999999) {
            throw new RuntimeException('VM-ID skal være mellem 100 og 999999999.');
        }
    }

    private function connection(int $id): object
    {
        $row = DB::table('nodexa_proxmox_connections')->where('id', $id)->first();
        if (!$row) {
            throw new RuntimeException('Proxmox-forbindelsen blev ikke fundet.');
        }

        return $row;
    }

    /**
     * Internal, fixed-prefix Proxmox API calls only. Never accepts an
     * arbitrary URL/host submitted by a browser.
     *
     * @return mixed Proxmox's JSON "data" payload.
     */
    private function api(object $connection, string $method, string $path, array $body = [])
    {
        if (!preg_match('~^/(?:nodes/[A-Za-z0-9._%-]+/(?:qemu/[0-9]+/(?:config|clone|status/start)|tasks/[A-Za-z0-9%._:@-]+/status)|cluster/nextid)~', $path)) {
            throw new RuntimeException('Ikke-tilladt Proxmox API-handling.');
        }

        $endpoint = rtrim((string) $connection->endpoint, '/') . '/api2/json' . $path;

        try {
            $secret = Crypt::decryptString($connection->token_secret_encrypted);
            $request = Http::withHeaders([
                'Authorization' => 'PVEAPIToken=' . $connection->token_id . '=' . $secret,
            ])->acceptJson()->connectTimeout(5)->timeout(15)->withOptions([
                'verify' => (bool) $connection->verify_tls,
                'allow_redirects' => false,
            ]);

            $response = $method === 'GET'
                ? $request->get($endpoint)
                : $request->asForm()->post($endpoint, $body);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'Proxmox kunne ikke kontaktes. Kontrollér HTTPS, adgang og netværk.'
            );
        }

        if ($response->status() === 401) {
            throw new RuntimeException('Proxmox API afviste tokenet (401).');
        }
        if ($response->status() === 403) {
            throw new RuntimeException('Tokenet mangler Proxmox-rettigheder til denne handling (403).');
        }
        if (!$response->successful()) {
            throw new RuntimeException('Proxmox API-handlingen fejlede (HTTP ' . $response->status() . ').');
        }

        $payload = $response->json();
        if (!is_array($payload) || !array_key_exists('data', $payload)) {
            throw new RuntimeException('Proxmox returnerede et ugyldigt svar.');
        }

        return $payload['data'];
    }
}
