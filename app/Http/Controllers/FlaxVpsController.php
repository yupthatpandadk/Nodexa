<?php

namespace Pterodactyl\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        return back()->with('success', 'VPS-handlingen er sendt til Flax.');
    }
}
