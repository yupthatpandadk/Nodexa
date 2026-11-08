<?php

namespace Pterodactyl\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Pterodactyl\Services\Nodexa\FlaxResellerService;
use Pterodactyl\Services\Nodexa\GlobalStoreSaleService;

class FlaxVpsController extends Controller
{
    public function __construct(
        private FlaxResellerService $flax,
        private GlobalStoreSaleService $sale,
    ) {
    }

    public function index(): View
    {
        $products = DB::table('nodexa_flax_products')
            ->where('enabled', true)
            ->orderByDesc('featured')
            ->orderBy('sort_order')
            ->orderBy('monthly_price')
            ->get();

        return view('store.vps.index', [
            'products' => $products,
            'configured' => $this->flax->isConfigured(),
            'globalSale' => $this->sale->active(),
        ]);
    }

    public function show(string $product): View
    {
        $plan = DB::table('nodexa_flax_products')
            ->where('slug', $product)
            ->where('enabled', true)
            ->first();

        abort_unless($plan, 404);

        $templates = DB::table('nodexa_flax_os_templates')
            ->orderBy('name')
            ->orderBy('version')
            ->orderBy('variant')
            ->get();

        return view('store.vps.product', [
            'product' => $plan,
            'templates' => $templates,
            'configured' => $this->flax->isConfigured(),
            'globalSale' => $this->sale->active(),
        ]);
    }

    public function order(Request $request, string $product): RedirectResponse
    {
        $plan = DB::table('nodexa_flax_products')
            ->where('slug', $product)
            ->where('enabled', true)
            ->first();

        abort_unless($plan, 404);

        $data = $request->validate([
            'hostname' => ['required', 'string', 'min:3', 'max:190', 'regex:/^[A-Za-z0-9][A-Za-z0-9 ._-]*[A-Za-z0-9]$/'],
            'os_template_id' => 'required|integer|exists:nodexa_flax_os_templates,upstream_id',
        ]);

        try {
            $result = $this->flax->createOrder(
                $request->user()->id,
                $plan->id,
                trim($data['hostname']),
                (int) $data['os_template_id']
            );
        } catch (\Throwable $exception) {
            return back()->withInput()->withErrors(['vps' => $exception->getMessage()]);
        }

        return redirect()->route('store.vps.checkout', $result['serviceId']);
    }

    public function checkout(Request $request, int $service): View
    {
        $serviceRow = DB::table('nodexa_flax_services as s')
            ->join('nodexa_flax_products as p', 'p.id', '=', 's.product_id')
            ->leftJoin('nodexa_flax_os_templates as os', 'os.upstream_id', '=', 's.os_template_id')
            ->leftJoin('nodexa_invoices as i', 'i.id', '=', 's.first_invoice_id')
            ->where('s.id', $service)
            ->where('s.user_id', $request->user()->id)
            ->select([
                's.*',
                'p.name as product_name',
                'p.description as product_description',
                'p.memory_mb',
                'p.vcpu',
                'p.disk_gb',
                'p.ipv4_count',
                'p.bandwidth',
                'p.monthly_price',
                'p.currency',
                'os.label as os_label',
                'i.number as invoice_number',
                'i.status as invoice_status',
                'i.due_at as invoice_due_at',
            ])
            ->first();

        abort_unless($serviceRow, 404);

        return view('store.vps.checkout', ['service' => $serviceRow]);
    }

    public function client(Request $request): View
    {
        $services = DB::table('nodexa_flax_services as s')
            ->join('nodexa_flax_products as p', 'p.id', '=', 's.product_id')
            ->leftJoin('nodexa_flax_os_templates as os', 'os.upstream_id', '=', 's.os_template_id')
            ->leftJoin('nodexa_subscriptions as sub', 'sub.id', '=', 's.subscription_id')
            ->where('s.user_id', $request->user()->id)
            ->select([
                's.*',
                'p.name as product_name',
                'p.memory_mb',
                'p.vcpu',
                'p.disk_gb',
                'p.ipv4_count',
                'p.bandwidth',
                'p.monthly_price',
                'p.currency',
                'os.label as os_label',
                'sub.next_invoice_at',
                'sub.status as subscription_status',
            ])
            ->orderByDesc('s.id')
            ->get();

        foreach ($services as $service) {
            $service->primary_ip = $this->flax->primaryIp($service->server_info);
            $service->remote_running = $this->flax->remoteRunning($service->server_info);
        }

        return view('store.client.vps', compact('services'));
    }

    /**
     * Individual customer VPS control panel. The owner filter is mandatory:
     * an authenticated customer must never view another customer's VPS.
     */
    public function clientService(Request $request, int $service): View
    {
        $serviceRow = DB::table('nodexa_flax_services as s')
            ->leftJoin('nodexa_flax_products as p', 'p.id', '=', 's.product_id')
            ->leftJoin('nodexa_flax_os_templates as os', 'os.upstream_id', '=', 's.os_template_id')
            ->leftJoin('nodexa_subscriptions as sub', 'sub.id', '=', 's.subscription_id')
            ->where('s.id', $service)
            ->where('s.user_id', $request->user()->id)
            ->select([
                's.*',
                'p.name as product_name',
                'p.vcpu',
                'p.memory_mb',
                'p.disk_gb',
                'p.ipv4_count',
                'p.bandwidth',
                'p.monthly_price',
                'p.currency',
                'os.label as os_label',
                'sub.next_invoice_at',
                'sub.status as subscription_status',
            ])
            ->first();

        abort_unless($serviceRow, 404);

        return view('store.client.vps-show', [
            'service' => $serviceRow,
            'primaryIp' => $this->flax->primaryIp($serviceRow->server_info),
            'running' => $this->flax->remoteRunning($serviceRow->server_info),
            'hasPassword' => Schema::hasColumn('nodexa_flax_services', 'credential_password_encrypted')
                && !empty($serviceRow->credential_password_encrypted),
        ]);
    }

    /**
     * Store a customer-managed copy of their VPS login password.
     * This is NOT a provider password reset. Never store plaintext.
     */
    public function savePassword(Request $request, int $service): RedirectResponse
    {
        $data = $request->validate([
            'vps_password' => ['required', 'string', 'min:8', 'max:255'],
        ]);

        $owned = $this->ownedPasswordService($request, $service);

        DB::table('nodexa_flax_services')->where('id', $owned->id)->update([
            'credential_password_encrypted' => Crypt::encryptString($data['vps_password']),
            'updated_at' => now(),
        ]);

        return redirect()->route('store.client.vps.show', $service)
            ->with('success', 'Adgangskoden blev gemt krypteret i Nodexa. Den er IKKE ændret hos Flax eller på VPS’en.');
    }

    public function revealPassword(Request $request, int $service): JsonResponse
    {
        $owned = $this->ownedPasswordService($request, $service);

        if (empty($owned->credential_password_encrypted)) {
            return response()->json(['message' => 'Der er endnu ikke gemt en adgangskode i Nodexa.'], 404)
                ->header('Cache-Control', 'private, no-store, max-age=0');
        }

        try {
            $password = Crypt::decryptString($owned->credential_password_encrypted);
        } catch (\Throwable $exception) {
            return response()->json(['message' => 'Den gemte adgangskode kunne ikke dekrypteres.'], 500)
                ->header('Cache-Control', 'private, no-store, max-age=0');
        }

        return response()->json(['password' => $password])
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    public function deletePassword(Request $request, int $service): RedirectResponse
    {
        $owned = $this->ownedPasswordService($request, $service);

        DB::table('nodexa_flax_services')->where('id', $owned->id)->update([
            'credential_password_encrypted' => null,
            'updated_at' => now(),
        ]);

        return redirect()->route('store.client.vps.show', $service)
            ->with('success', 'Den gemte adgangskode blev fjernet fra Nodexa. VPS’ens faktiske login er uændret.');
    }

    private function ownedPasswordService(Request $request, int $service): object
    {
        abort_unless(Schema::hasColumn('nodexa_flax_services', 'credential_password_encrypted'), 503);

        $owned = DB::table('nodexa_flax_services')
            ->where('id', $service)
            ->where('user_id', $request->user()->id)
            ->first();

        abort_unless($owned, 404);
        abort_unless($owned->upstream_server_id
            && in_array($owned->status, ['active', 'suspended'], true), 403);

        return $owned;
    }

    public function action(Request $request, int $service): RedirectResponse
    {
        $data = $request->validate([
            'action' => 'required|in:boot,shutdown,restart,poweroff,refresh',
        ]);

        $owned = DB::table('nodexa_flax_services')
            ->where('id', $service)
            ->where('user_id', $request->user()->id)
            ->first();

        abort_unless($owned, 404);

        try {
            if ($data['action'] === 'refresh') {
                $this->flax->syncService($service);
            } else {
                $this->flax->power($service, $request->user()->id, $data['action']);
            }
        } catch (\Throwable $exception) {
            return back()->withErrors(['vps' => $exception->getMessage()]);
        }

        return redirect()->route('store.client.vps.show', $service)
            ->with('success', $data['action'] === 'refresh'
                ? 'Status for denne VPS er opdateret.'
                : 'VPS-handlingen er sendt til Flax.');
    }
}
