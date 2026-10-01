<?php

namespace Pterodactyl\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Pterodactyl\Models\Server;
use Pterodactyl\Models\User;
use Pterodactyl\Services\Nodexa\NodexaEventService;

class ClientHubController extends Controller
{
    public function __construct(private NodexaEventService $events)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        $notifications = DB::table('nodexa_notifications')
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $invoices = DB::table('nodexa_invoices')
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $backupPolicies = DB::table('nodexa_backup_policies')
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('server_id');

        $servers = $user->accessibleServers()
            ->with(['node', 'backups'])
            ->orderBy('servers.name')
            ->get();

        $addons = DB::table('nodexa_store_addons')
            ->where('enabled', true)
            ->orderBy('price_monthly')
            ->get();

        $serviceAddons = DB::table('nodexa_service_addons')
            ->where('user_id', $user->id)
            ->get()
            ->groupBy('server_id');

        $affiliate = DB::table('nodexa_affiliates')->where('user_id', $user->id)->first();

        $organizations = DB::table('nodexa_organizations as o')
            ->leftJoin('nodexa_organization_members as m', 'm.organization_id', '=', 'o.id')
            ->where(function ($query) use ($user) {
                $query->where('o.owner_id', $user->id)->orWhere('m.user_id', $user->id);
            })
            ->select('o.*')
            ->distinct()
            ->orderBy('o.name')
            ->get();

        $organizationMembers = DB::table('nodexa_organization_members as m')
            ->join('users', 'users.id', '=', 'm.user_id')
            ->select('m.*', 'users.email', 'users.username')
            ->whereIn('m.organization_id', $organizations->pluck('id')->all())
            ->orderBy('m.organization_id')
            ->get()
            ->groupBy('organization_id');

        $webhooks = DB::table('nodexa_webhooks')
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->get();

        $tokens = DB::table('nodexa_api_tokens')
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->get();

        $securityActivity = DB::table('nodexa_audit_logs')
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $onboarding = [
            'profile' => filled($user->name_first) && filled($user->name_last),
            'two_factor' => (bool) $user->use_totp,
            'server' => $servers->isNotEmpty(),
            'backup' => $backupPolicies->where('enabled', true)->isNotEmpty(),
            'knowledgebase' => true,
        ];
        $completed = collect($onboarding)->filter()->count();
        $onboardingPercent = (int) round(($completed / count($onboarding)) * 100);

        return view('store.client.hub', compact(
            'user',
            'notifications',
            'invoices',
            'backupPolicies',
            'servers',
            'addons',
            'serviceAddons',
            'affiliate',
            'organizations',
            'organizationMembers',
            'webhooks',
            'tokens',
            'securityActivity',
            'onboarding',
            'onboardingPercent'
        ));
    }

    public function markNotification(Request $request, int $notification): RedirectResponse
    {
        DB::table('nodexa_notifications')
            ->where('id', $notification)
            ->where('user_id', $request->user()->id)
            ->update(['read_at' => now(), 'updated_at' => now()]);

        return back();
    }

    public function markAllNotifications(Request $request): RedirectResponse
    {
        DB::table('nodexa_notifications')
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now(), 'updated_at' => now()]);

        return back();
    }

    public function saveBackupPolicy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'server_id' => 'required|integer',
            'enabled' => 'nullable|boolean',
            'frequency_hours' => 'required|integer|min:1|max:720',
            'retention_count' => 'required|integer|min:1|max:50',
            'name_prefix' => 'nullable|string|max:120',
        ]);

        $server = $request->user()->servers()->whereKey($data['server_id'])->firstOrFail();

        DB::table('nodexa_backup_policies')->updateOrInsert(
            ['server_id' => $server->id],
            [
                'user_id' => $request->user()->id,
                'enabled' => $request->boolean('enabled'),
                'frequency_hours' => (int) $data['frequency_hours'],
                'retention_count' => min((int) $data['retention_count'], max(1, $server->backup_limit)),
                'name_prefix' => trim((string) ($data['name_prefix'] ?? '')) ?: 'Automatic backup',
                'next_run_at' => $request->boolean('enabled') ? now()->addHours((int) $data['frequency_hours']) : null,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $this->events->audit($request->user()->id, 'backup', 'policy.updated', $server->name, 'server', $server->id, $data, $request);

        return back()->with('success', 'Backup-planen er gemt.');
    }

    public function requestAddon(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'server_id' => 'required|integer',
            'addon_id' => 'required|integer',
        ]);

        $server = $request->user()->servers()->whereKey($data['server_id'])->firstOrFail();
        $addon = DB::table('nodexa_store_addons')->where('id', $data['addon_id'])->where('enabled', true)->first();
        abort_unless($addon, 404);

        $exists = DB::table('nodexa_service_addons')
            ->where('server_id', $server->id)
            ->where('addon_id', $addon->id)
            ->whereIn('status', ['pending', 'active'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['addon' => 'Dette add-on er allerede aktivt eller afventer betaling.']);
        }

        $number = 'NX-' . now()->format('Ym') . '-' . strtoupper(Str::random(8));
        $invoiceId = DB::table('nodexa_invoices')->insertGetId([
            'user_id' => $request->user()->id,
            'subscription_id' => null,
            'number' => $number,
            'status' => 'unpaid',
            'subtotal' => $addon->price_monthly,
            'tax' => 0,
            'total' => $addon->price_monthly,
            'currency' => 'DKK',
            'due_at' => now()->addDays(7),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('nodexa_invoice_items')->insert([
            'invoice_id' => $invoiceId,
            'description' => $addon->name . ' til ' . $server->name,
            'quantity' => 1,
            'unit_price' => $addon->price_monthly,
            'total' => $addon->price_monthly,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('nodexa_service_addons')->insert([
            'user_id' => $request->user()->id,
            'server_id' => $server->id,
            'addon_id' => $addon->id,
            'invoice_id' => $invoiceId,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->events->notify(
            $request->user()->id,
            'Add-on afventer betaling',
            $addon->name . ' er klar på faktura ' . $number . '.',
            'billing',
            '/client/billing'
        );
        $this->events->emit('addon.ordered', [
            'addon_id' => $addon->id,
            'server_id' => $server->id,
            'invoice_id' => $invoiceId,
        ], $request->user()->id);

        return back()->with('success', 'Add-on er bestilt og fakturaen er oprettet.');
    }

    public function enableAffiliate(Request $request): RedirectResponse
    {
        $existing = DB::table('nodexa_affiliates')->where('user_id', $request->user()->id)->first();
        if ($existing) {
            return back()->with('success', 'Affiliate-programmet er allerede aktivt.');
        }

        do {
            $code = strtoupper(Str::random(8));
        } while (DB::table('nodexa_affiliates')->where('code', $code)->exists());

        DB::table('nodexa_affiliates')->insert([
            'user_id' => $request->user()->id,
            'code' => $code,
            'commission_percent' => 10,
            'balance' => 0,
            'paid_total' => 0,
            'clicks' => 0,
            'conversions' => 0,
            'enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Affiliate-programmet er aktiveret.');
    }

    public function createOrganization(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => 'required|string|max:120']);

        $base = Str::slug($data['name']) ?: 'team';
        $slug = $base . '-' . Str::lower(Str::random(5));

        $id = DB::table('nodexa_organizations')->insertGetId([
            'owner_id' => $request->user()->id,
            'name' => trim($data['name']),
            'slug' => $slug,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('nodexa_organization_members')->insert([
            'organization_id' => $id,
            'user_id' => $request->user()->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Team/organisation er oprettet.');
    }

    public function addOrganizationMember(Request $request, int $organization): RedirectResponse
    {
        $org = DB::table('nodexa_organizations')
            ->where('id', $organization)
            ->where('owner_id', $request->user()->id)
            ->first();
        abort_unless($org, 403);

        $data = $request->validate([
            'email' => 'required|email',
            'role' => 'required|in:admin,billing,member',
        ]);

        $user = User::query()->where('email', $data['email'])->first();
        if (!$user) {
            return back()->withErrors(['email' => 'Der findes ingen Nodexa-konto med denne email.']);
        }

        DB::table('nodexa_organization_members')->updateOrInsert(
            ['organization_id' => $organization, 'user_id' => $user->id],
            [
                'role' => $data['role'],
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $this->events->notify($user->id, 'Tilføjet til ' . $org->name, 'Du er blevet tilføjet til teamet med rollen ' . $data['role'] . '.', 'team', '/client/hub');

        return back()->with('success', 'Medlemmet er tilføjet.');
    }

    public function createWebhook(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'url' => 'required|url|max:1000',
            'events' => 'required|array|min:1',
            'events.*' => 'in:invoice.created,invoice.paid,addon.ordered,status.incident.created,status.incident.updated,server.provisioned,ticket.reply,*',
        ]);

        $secret = Str::random(48);

        DB::table('nodexa_webhooks')->insert([
            'user_id' => $request->user()->id,
            'name' => trim($data['name']),
            'url' => $data['url'],
            'secret' => $secret,
            'events' => json_encode(array_values($data['events'])),
            'enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Webhook er oprettet. Secret: ' . $secret);
    }

    public function deleteWebhook(Request $request, int $webhook): RedirectResponse
    {
        DB::table('nodexa_webhooks')
            ->where('id', $webhook)
            ->where('user_id', $request->user()->id)
            ->delete();

        return back()->with('success', 'Webhook er slettet.');
    }

    public function testWebhook(Request $request, int $webhook): RedirectResponse
    {
        $row = DB::table('nodexa_webhooks')
            ->where('id', $webhook)
            ->where('user_id', $request->user()->id)
            ->first();
        abort_unless($row, 404);

        $payload = [
            'event' => 'test',
            'occurred_at' => now()->toIso8601String(),
            'data' => ['message' => 'Nodexa webhook test'],
        ];
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', $json ?: '', (string) $row->secret);

        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Nodexa-Event' => 'test',
                    'X-Nodexa-Signature' => 'sha256=' . $signature,
                ])
                ->withBody($json ?: '{}', 'application/json')
                ->post($row->url);

            DB::table('nodexa_webhooks')->where('id', $row->id)->update([
                'last_delivery_at' => now(),
                'last_status' => $response->successful() ? 'success' : 'failed',
                'updated_at' => now(),
            ]);

            return back()->with($response->successful() ? 'success' : 'error', 'Webhook test HTTP ' . $response->status());
        } catch (\Throwable $exception) {
            return back()->withErrors(['webhook' => 'Webhook fejlede: ' . $exception->getMessage()]);
        }
    }

    public function createApiToken(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'scopes' => 'required|array|min:1',
            'scopes.*' => 'in:profile.read,servers.read,billing.read',
        ]);

        $plain = 'ndx_' . Str::random(48);

        DB::table('nodexa_api_tokens')->insert([
            'user_id' => $request->user()->id,
            'name' => trim($data['name']),
            'token_prefix' => substr($plain, 0, 12),
            'token_hash' => hash('sha256', $plain),
            'scopes' => json_encode(array_values($data['scopes'])),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('new_api_token', $plain)->with('success', 'API-token er oprettet. Kopiér den nu; den vises kun én gang.');
    }

    public function revokeApiToken(Request $request, int $token): RedirectResponse
    {
        DB::table('nodexa_api_tokens')
            ->where('id', $token)
            ->where('user_id', $request->user()->id)
            ->delete();

        return back()->with('success', 'API-token er tilbagekaldt.');
    }
}
