<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\StoreOrder;
use Pterodactyl\Services\Nodexa\CfxEupService;
use Pterodactyl\Services\Nodexa\NodexaAutomationService;
use Pterodactyl\Services\Nodexa\NodexaEventService;
use Pterodactyl\Services\Servers\BuildModificationService;
use Pterodactyl\Services\Servers\SuspensionService;

class OperationsController extends Controller
{
    public function __construct(
        private NodexaEventService $events,
        private NodexaAutomationService $automation,
        private BuildModificationService $builds,
        private SuspensionService $suspension,
        private CfxEupService $cfxEup,
    ) {
    }

    public function index(): View
    {
        $components = DB::table('nodexa_status_components')->orderBy('sort_order')->orderBy('name')->get();
        $incidents = DB::table('nodexa_incidents')->orderByDesc('started_at')->orderByDesc('id')->limit(40)->get();
        $incidentUpdates = DB::table('nodexa_incident_updates')->orderByDesc('created_at')->limit(150)->get()->groupBy('incident_id');
        $addons = DB::table('nodexa_store_addons')->orderBy('name')->get();
        $invoices = DB::table('nodexa_invoices')
            ->leftJoin('users', 'users.id', '=', 'nodexa_invoices.user_id')
            ->select('nodexa_invoices.*', 'users.email as user_email')
            ->orderByDesc('nodexa_invoices.id')
            ->limit(100)
            ->get();
        $subscriptions = DB::table('nodexa_subscriptions')
            ->leftJoin('users', 'users.id', '=', 'nodexa_subscriptions.user_id')
            ->leftJoin('servers', 'servers.id', '=', 'nodexa_subscriptions.server_id')
            ->select('nodexa_subscriptions.*', 'users.email as user_email', 'servers.name as server_name')
            ->orderByDesc('nodexa_subscriptions.id')
            ->limit(100)
            ->get();
        $health = DB::table('nodexa_health_checks as h')
            ->joinSub(
                DB::table('nodexa_health_checks')
                    ->selectRaw('node_id, MAX(checked_at) as checked_at')
                    ->groupBy('node_id'),
                'latest',
                function ($join) {
                    $join->on('h.node_id', '=', 'latest.node_id')
                        ->on('h.checked_at', '=', 'latest.checked_at');
                }
            )
            ->join('nodes', 'nodes.id', '=', 'h.node_id')
            ->select(['h.*', 'nodes.name as node_name', 'nodes.fqdn'])
            ->orderBy('nodes.name')
            ->get();
        $audit = DB::table('nodexa_audit_logs')
            ->leftJoin('users', 'users.id', '=', 'nodexa_audit_logs.user_id')
            ->select('nodexa_audit_logs.*', 'users.email as user_email')
            ->orderByDesc('nodexa_audit_logs.id')
            ->limit(150)
            ->get();
        $affiliates = DB::table('nodexa_affiliates')
            ->leftJoin('users', 'users.id', '=', 'nodexa_affiliates.user_id')
            ->select('nodexa_affiliates.*', 'users.email as user_email')
            ->orderByDesc('nodexa_affiliates.balance')
            ->limit(100)
            ->get();
        $organizations = DB::table('nodexa_organizations')
            ->leftJoin('users', 'users.id', '=', 'nodexa_organizations.owner_id')
            ->select('nodexa_organizations.*', 'users.email as owner_email')
            ->orderByDesc('nodexa_organizations.id')
            ->limit(100)
            ->get();
        $webhooks = DB::table('nodexa_webhooks')->orderByDesc('id')->limit(100)->get();

        return view('admin.operations.index', compact(
            'components',
            'incidents',
            'incidentUpdates',
            'addons',
            'invoices',
            'subscriptions',
            'health',
            'audit',
            'affiliates',
            'organizations',
            'webhooks'
        ));
    }

    public function storeComponent(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
            'status' => 'required|in:operational,degraded,partial_outage,major_outage,maintenance',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'is_public' => 'nullable|boolean',
        ]);

        $id = DB::table('nodexa_status_components')->insertGetId([
            'name' => trim($data['name']),
            'slug' => $this->uniqueComponentSlug($data['name']),
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'status' => $data['status'],
            'is_public' => $request->boolean('is_public'),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->events->audit($request->user()->id, 'status', 'component.created', $data['name'], 'component', $id, [], $request);

        return back()->with('success', 'Status-komponenten er oprettet.');
    }

    public function updateComponent(Request $request, int $component): RedirectResponse
    {
        $data = $request->validate([
            'status' => 'required|in:operational,degraded,partial_outage,major_outage,maintenance',
            'description' => 'nullable|string|max:500',
            'is_public' => 'nullable|boolean',
        ]);

        DB::table('nodexa_status_components')->where('id', $component)->update([
            'status' => $data['status'],
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'is_public' => $request->boolean('is_public'),
            'updated_at' => now(),
        ]);

        $this->events->audit($request->user()->id, 'status', 'component.updated', null, 'component', $component, $data, $request);

        return back()->with('success', 'Status-komponenten er opdateret.');
    }

    public function deleteComponent(Request $request, int $component): RedirectResponse
    {
        DB::table('nodexa_status_components')->where('id', $component)->delete();
        $this->events->audit($request->user()->id, 'status', 'component.deleted', null, 'component', $component, [], $request);

        return back()->with('success', 'Status-komponenten er slettet.');
    }

    public function storeIncident(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:180',
            'severity' => 'required|in:info,minor,major,critical,maintenance',
            'status' => 'required|in:investigating,identified,monitoring,resolved',
            'message' => 'required|string|max:10000',
            'published' => 'nullable|boolean',
        ]);

        $resolved = $data['status'] === 'resolved' ? now() : null;
        $id = DB::table('nodexa_incidents')->insertGetId([
            'title' => trim($data['title']),
            'severity' => $data['severity'],
            'status' => $data['status'],
            'message' => trim($data['message']),
            'published' => $request->boolean('published'),
            'started_at' => now(),
            'resolved_at' => $resolved,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('nodexa_incident_updates')->insert([
            'incident_id' => $id,
            'user_id' => $request->user()->id,
            'status' => $data['status'],
            'message' => trim($data['message']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->events->emit('status.incident.created', [
            'incident_id' => $id,
            'title' => $data['title'],
            'severity' => $data['severity'],
            'status' => $data['status'],
        ]);
        $this->events->audit($request->user()->id, 'status', 'incident.created', $data['title'], 'incident', $id, [], $request);

        return back()->with('success', 'Incident er oprettet.');
    }

    public function updateIncident(Request $request, int $incident): RedirectResponse
    {
        $data = $request->validate([
            'status' => 'required|in:investigating,identified,monitoring,resolved',
            'message' => 'required|string|max:10000',
        ]);

        $row = DB::table('nodexa_incidents')->where('id', $incident)->first();
        abort_unless($row, 404);

        DB::table('nodexa_incidents')->where('id', $incident)->update([
            'status' => $data['status'],
            'resolved_at' => $data['status'] === 'resolved' ? now() : null,
            'updated_at' => now(),
        ]);

        DB::table('nodexa_incident_updates')->insert([
            'incident_id' => $incident,
            'user_id' => $request->user()->id,
            'status' => $data['status'],
            'message' => trim($data['message']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->events->emit('status.incident.updated', [
            'incident_id' => $incident,
            'title' => $row->title,
            'status' => $data['status'],
            'message' => trim($data['message']),
        ]);
        $this->events->audit($request->user()->id, 'status', 'incident.updated', $row->title, 'incident', $incident, $data, $request);

        return back()->with('success', 'Incident er opdateret.');
    }

    public function storeAddon(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
            'price_monthly' => 'required|numeric|min:0',
            'memory_delta' => 'nullable|integer|min:0',
            'disk_delta' => 'nullable|integer|min:0',
            'cpu_delta' => 'nullable|integer|min:0',
            'backup_delta' => 'nullable|integer|min:0',
            'enabled' => 'nullable|boolean',
        ]);

        DB::table('nodexa_store_addons')->insert([
            'name' => trim($data['name']),
            'slug' => Str::slug($data['name']) . '-' . Str::lower(Str::random(5)),
            'description' => trim((string) ($data['description'] ?? '')) ?: null,
            'price_monthly' => $data['price_monthly'],
            'memory_delta' => (int) ($data['memory_delta'] ?? 0),
            'disk_delta' => (int) ($data['disk_delta'] ?? 0),
            'cpu_delta' => (int) ($data['cpu_delta'] ?? 0),
            'backup_delta' => (int) ($data['backup_delta'] ?? 0),
            'enabled' => $request->boolean('enabled'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Service add-on er oprettet.');
    }

    public function markInvoicePaid(Request $request, int $invoice): RedirectResponse
    {
        $row = DB::table('nodexa_invoices')->where('id', $invoice)->first();
        abort_unless($row, 404);

        if ($row->status === 'paid') {
            return back()->with('success', 'Fakturaen er allerede betalt.');
        }

        DB::table('nodexa_invoices')->where('id', $invoice)->update([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_method' => $request->input('payment_method', 'manual'),
            'payment_reference' => trim((string) $request->input('payment_reference')) ?: null,
            'updated_at' => now(),
        ]);

        $pendingAddons = DB::table('nodexa_service_addons')->where('invoice_id', $invoice)->where('status', 'pending')->get();
        foreach ($pendingAddons as $serviceAddon) {
            $addon = DB::table('nodexa_store_addons')->where('id', $serviceAddon->addon_id)->first();
            $server = Server::query()->find($serviceAddon->server_id);

            if ($addon && $server) {
                $this->builds->handle($server, [
                    'memory' => $server->memory + (int) $addon->memory_delta,
                    'swap' => $server->swap,
                    'io' => $server->io,
                    'cpu' => $server->cpu + (int) $addon->cpu_delta,
                    'threads' => $server->threads,
                    'disk' => $server->disk + (int) $addon->disk_delta,
                    'allocation_id' => $server->allocation_id,
                    'database_limit' => $server->database_limit,
                    'allocation_limit' => $server->allocation_limit,
                    'backup_limit' => $server->backup_limit + (int) $addon->backup_delta,
                    'oom_disabled' => $server->oom_disabled,
                ]);

                DB::table('nodexa_service_addons')->where('id', $serviceAddon->id)->update([
                    'status' => 'active',
                    'started_at' => now(),
                    'updated_at' => now(),
                ]);

                $subscription = DB::table('nodexa_subscriptions')
                    ->where('server_id', $server->id)
                    ->where('status', 'active')
                    ->orderByDesc('id')
                    ->first();

                if ($subscription) {
                    DB::table('nodexa_subscriptions')->where('id', $subscription->id)->update([
                        'amount' => (float) $subscription->amount + (float) $addon->price_monthly,
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        if ($row->subscription_id) {
            $subscription = DB::table('nodexa_subscriptions')->where('id', $row->subscription_id)->first();
            $hasOverdue = $subscription
                ? DB::table('nodexa_invoices')
                    ->where('subscription_id', $subscription->id)
                    ->where('status', 'overdue')
                    ->where('id', '!=', $invoice)
                    ->exists()
                : false;

            if ($subscription?->server_id) {
                $server = Server::query()->find($subscription->server_id);

                if ($server && $server->isSuspended() && !$hasOverdue) {
                    try {
                        $this->suspension->toggle($server, SuspensionService::ACTION_UNSUSPEND);
                    } catch (\Throwable $exception) {
                        report($exception);
                    }
                }
            }

            if (($subscription->service_type ?? null) === 'cfx_eup' && !$hasOverdue) {
                try {
                    $this->cfxEup->activateInvoice($invoice);
                } catch (\Throwable $exception) {
                    report($exception);
                }
            }
        }

        $this->events->notify($row->user_id, 'Faktura betalt', 'Faktura ' . $row->number . ' er registreret som betalt.', 'billing', '/client/billing');
        $this->events->emit('invoice.paid', ['invoice_id' => $invoice, 'number' => $row->number], $row->user_id);
        $this->events->audit($request->user()->id, 'billing', 'invoice.paid', $row->number, 'invoice', $invoice, [], $request);

        return back()->with('success', 'Fakturaen er markeret som betalt.');
    }

    public function syncSubscriptions(Request $request): RedirectResponse
    {
        $orders = StoreOrder::query()
            ->whereIn('status', ['paid', 'active'])
            ->whereNotNull('server_id')
            ->with('product')
            ->get();

        $created = 0;
        foreach ($orders as $order) {
            $exists = DB::table('nodexa_subscriptions')->where('order_id', $order->id)->exists();
            if ($exists) {
                continue;
            }

            DB::table('nodexa_subscriptions')->insert([
                'user_id' => $order->user_id,
                'server_id' => $order->server_id,
                'order_id' => $order->id,
                'status' => 'active',
                'amount' => $order->amount,
                'currency' => $order->currency ?: 'DKK',
                'interval' => 'monthly',
                'next_invoice_at' => now()->addMonth(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $created++;
        }

        $this->events->audit($request->user()->id, 'billing', 'subscriptions.synced', $created . ' subscriptions created', null, null, ['created' => $created], $request);

        return back()->with('success', $created . ' abonnement(er) blev oprettet/synkroniseret.');
    }

    public function payAffiliate(Request $request, int $affiliate): RedirectResponse
    {
        $row = DB::table('nodexa_affiliates')->where('id', $affiliate)->first();
        abort_unless($row, 404);

        $amount = (float) $row->balance;
        if ($amount <= 0) {
            return back()->withErrors(['affiliate' => 'Affiliate-kontoen har ingen saldo at udbetale.']);
        }

        DB::table('nodexa_affiliates')->where('id', $affiliate)->update([
            'balance' => 0,
            'paid_total' => DB::raw('paid_total + ' . $amount),
            'updated_at' => now(),
        ]);

        $this->events->notify($row->user_id, 'Affiliate-udbetaling registreret', number_format($amount, 2, ',', '.') . ' DKK er markeret som udbetalt.', 'billing', '/client/hub');
        $this->events->audit($request->user()->id, 'affiliate', 'payout.created', null, 'affiliate', $affiliate, ['amount' => $amount], $request);

        return back()->with('success', 'Affiliate-saldoen er markeret som udbetalt.');
    }

    public function runAutomation(Request $request): RedirectResponse
    {
        $result = $this->automation->run();
        $this->events->audit($request->user()->id, 'operations', 'automation.run', null, null, null, $result, $request);

        return back()->with('success', sprintf(
            'Automation færdig: %d health checks, %d backups og %d fakturaer.',
            $result['health'],
            $result['backups'],
            $result['billing']
        ));
    }

    private function uniqueComponentSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'component';
        $slug = $base;
        $number = 2;

        while (DB::table('nodexa_status_components')->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $number++;
        }

        return $slug;
    }
}
