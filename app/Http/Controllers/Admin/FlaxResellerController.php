<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Services\Nodexa\FlaxResellerService;
use Pterodactyl\Services\Nodexa\NodexaEventService;

class FlaxResellerController extends Controller
{
    public function __construct(
        private FlaxResellerService $flax,
        private NodexaEventService $events,
    ) {
    }

    public function index(): View
    {
        $settings = $this->flax->getSettings();
        $products = DB::table('nodexa_flax_products')->orderBy('sort_order')->orderBy('monthly_price')->get();
        $templates = DB::table('nodexa_flax_os_templates')->orderBy('name')->orderBy('version')->get();
        $services = DB::table('nodexa_flax_services as s')
            ->leftJoin('users as u', 'u.id', '=', 's.user_id')
            ->leftJoin('nodexa_flax_products as p', 'p.id', '=', 's.product_id')
            ->leftJoin('nodexa_subscriptions as sub', 'sub.id', '=', 's.subscription_id')
            ->select([
                's.*',
                'u.email as user_email',
                'u.username as username',
                'p.name as product_name',
                'p.monthly_price as retail_price',
                'p.currency as product_currency',
                'sub.next_invoice_at',
                'sub.status as subscription_status',
            ])
            ->orderByDesc('s.id')
            ->limit(200)
            ->get();

        foreach ($services as $service) {
            $service->primary_ip = $this->flax->primaryIp($service->server_info);
            $service->remote_running = $this->flax->remoteRunning($service->server_info);
        }

        return view('admin.flax-reseller.index', [
            'settings' => $settings,
            'maskedKey' => $this->flax->maskedKey(),
            'products' => $products,
            'templates' => $templates,
            'services' => $services,
        ]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'api_url' => 'required|url|max:1000',
            'api_key' => 'nullable|string|max:5000',
            'enabled' => 'nullable|boolean',
        ]);

        try {
            $this->flax->saveSettings(
                $data['api_url'],
                isset($data['api_key']) ? trim($data['api_key']) : null,
                $request->boolean('enabled')
            );
        } catch (\Throwable $exception) {
            return back()->withErrors(['flax' => $exception->getMessage()]);
        }

        $this->events->audit(
            $request->user()->id,
            'flax_reseller',
            'settings.updated',
            'Flax reseller settings updated',
            null,
            null,
            ['enabled' => $request->boolean('enabled')],
            $request
        );

        return back()->with('success', 'Flax Reseller-indstillinger er gemt.');
    }

    public function testConnection(Request $request): RedirectResponse
    {
        $result = $this->flax->testConnection();

        $this->events->audit(
            $request->user()->id,
            'flax_reseller',
            'connection.tested',
            $result['message'],
            null,
            null,
            ['success' => $result['success'], 'server_count' => $result['server_count'] ?? 0],
            $request
        );

        return $result['success']
            ? back()->with('success', $result['message'] . ' Upstream services: ' . ($result['server_count'] ?? 0) . '.')
            : back()->withErrors(['flax' => $result['message']]);
    }

    public function syncTemplates(Request $request): RedirectResponse
    {
        try {
            $count = $this->flax->syncTemplates();
        } catch (\Throwable $exception) {
            return back()->withErrors(['flax' => $exception->getMessage()]);
        }

        $this->events->audit(
            $request->user()->id,
            'flax_reseller',
            'templates.synced',
            $count . ' templates synced',
            null,
            null,
            ['count' => $count],
            $request
        );

        return back()->with('success', $count . ' VirtFusion OS-template(s) blev synkroniseret fra Flax.');
    }

    public function storeProduct(Request $request): RedirectResponse
    {
        $data = $this->validateProduct($request);
        $data['slug'] = $this->uniqueSlug($data['name']);
        $data['enabled'] = $request->boolean('enabled');
        $data['featured'] = $request->boolean('featured');
        $data['created_at'] = now();
        $data['updated_at'] = now();

        $id = DB::table('nodexa_flax_products')->insertGetId($data);

        $this->events->audit(
            $request->user()->id,
            'flax_reseller',
            'product.created',
            $data['name'],
            'flax_product',
            $id,
            ['upstream_product_id' => $data['upstream_product_id']],
            $request
        );

        return back()->with('success', 'VPS-produktet er oprettet.');
    }

    public function updateProduct(Request $request, int $product): RedirectResponse
    {
        $existing = DB::table('nodexa_flax_products')->where('id', $product)->first();
        abort_unless($existing, 404);

        $data = $this->validateProduct($request);
        $data['enabled'] = $request->boolean('enabled');
        $data['featured'] = $request->boolean('featured');
        $data['updated_at'] = now();

        DB::table('nodexa_flax_products')->where('id', $product)->update($data);

        $this->events->audit(
            $request->user()->id,
            'flax_reseller',
            'product.updated',
            $data['name'],
            'flax_product',
            $product,
            ['upstream_product_id' => $data['upstream_product_id']],
            $request
        );

        return back()->with('success', 'VPS-produktet er opdateret.');
    }

    public function deleteProduct(Request $request, int $product): RedirectResponse
    {
        $existing = DB::table('nodexa_flax_products')->where('id', $product)->first();
        abort_unless($existing, 404);

        if (DB::table('nodexa_flax_services')->where('product_id', $product)->exists()) {
            return back()->withErrors(['flax' => 'Produktet har eksisterende VPS-services og kan derfor ikke slettes. Deaktivér det i stedet.']);
        }

        DB::table('nodexa_flax_products')->where('id', $product)->delete();

        $this->events->audit(
            $request->user()->id,
            'flax_reseller',
            'product.deleted',
            $existing->name,
            'flax_product',
            $product,
            [],
            $request
        );

        return back()->with('success', 'VPS-produktet er slettet.');
    }

    public function serviceAction(Request $request, int $service): RedirectResponse
    {
        $data = $request->validate([
            'action' => 'required|in:sync,retry,boot,shutdown,restart,poweroff,suspend,unsuspend,terminate',
        ]);

        try {
            $this->flax->adminAction($service, $data['action']);
        } catch (\Throwable $exception) {
            return back()->withErrors(['flax' => $exception->getMessage()]);
        }

        $this->events->audit(
            $request->user()->id,
            'flax_reseller',
            'service.' . $data['action'],
            'Flax VPS service action',
            'flax_service',
            $service,
            [],
            $request
        );

        return back()->with('success', 'VPS-handlingen "' . $data['action'] . '" er udført.');
    }

    private function validateProduct(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:140',
            'description' => 'nullable|string|max:3000',
            'upstream_product_id' => 'required|integer|min:1',
            'monthly_price' => 'required|numeric|min:0.01|max:999999.99',
            'currency' => 'required|string|size:3',
            'memory_mb' => 'nullable|integer|min:128|max:1048576',
            'vcpu' => 'nullable|integer|min:1|max:255',
            'disk_gb' => 'nullable|integer|min:1|max:1048576',
            'ipv4_count' => 'required|integer|min:0|max:32',
            'bandwidth' => 'nullable|string|max:120',
            'sort_order' => 'nullable|integer|min:0|max:99999',
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'vps';
        $slug = $base;
        $counter = 2;

        while (DB::table('nodexa_flax_products')->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $counter++;
        }

        return $slug;
    }
}
