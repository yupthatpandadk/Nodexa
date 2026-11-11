<?php

namespace Pterodactyl\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\StoreProduct;
use Pterodactyl\Models\StoreOrder;
use Pterodactyl\Services\Nodexa\GlobalStoreSaleService;
use Pterodactyl\Services\Nodexa\OrderRemovalService;
use Pterodactyl\Services\Nodexa\MinecraftOrderOptionsService;

class StorefrontController extends Controller
{
    public function __construct(private GlobalStoreSaleService $sale, private OrderRemovalService $removal, private MinecraftOrderOptionsService $minecraft)
    {
    }

    public function index()
    {
        return view(app(\Pterodactyl\Services\Nodexa\FrontendThemeService::class)->template('home'), [
            'products'=>StoreProduct::where('enabled',true)->orderByDesc('featured')->orderBy('price_monthly')->get(),
            'globalSale'=>$this->sale->active(),
        ]);
    }

    public function hosting()
    {
        return view('store.hosting', [
            'products'=>StoreProduct::where('enabled',true)->orderByDesc('featured')->orderBy('price_monthly')->get(),
            'globalSale'=>$this->sale->active(),
        ]);
    }

    public function features() { return view('store.features'); }
    public function about() { return view('store.about'); }
    public function support() { return view('store.support'); }

    public function dashboard(Request $request)
    {
        $user = $request->user();

        $orders = StoreOrder::with(['product','server'])
            ->where('user_id', $user->id)
            ->where('hidden_from_client', false)
            ->latest()
            ->get();

        // The Client Area must reflect the actual servers the user can access in
        // Nodexa, not only servers that happened to be created through the
        // Storefront order flow. This also includes servers assigned as subuser.
        $activeServers = $user->accessibleServers()
            ->with(['node', 'allocation'])
            ->orderByDesc('servers.created_at')
            ->get();

        // CFX EUP is a first-class Nodexa service as well. Keep it separate from
        // game servers internally, but expose it in the same "Mine services"
        // overview so customers do not have to know which subsystem created it.
        $eupServices = DB::table('nodexa_cfx_eup_orders as eup')
            ->leftJoin('nodexa_subscriptions as sub', 'sub.id', '=', 'eup.subscription_id')
            ->where('eup.user_id', $user->id)
            ->whereIn('eup.status', ['awaiting_payment', 'active', 'suspended'])
            ->select([
                'eup.*',
                'sub.status as subscription_status',
                'sub.next_invoice_at',
            ])
            ->orderByDesc('eup.created_at')
            ->get();

        $flaxServices = DB::table('nodexa_flax_services as vps')
            ->leftJoin('nodexa_flax_products as product', 'product.id', '=', 'vps.product_id')
            ->leftJoin('nodexa_subscriptions as sub', 'sub.id', '=', 'vps.subscription_id')
            ->where('vps.user_id', $user->id)
            ->whereIn('vps.status', ['awaiting_payment', 'provisioning', 'provisioning_failed', 'active', 'suspended'])
            ->select([
                'vps.*',
                'product.name as product_name',
                'product.monthly_price',
                'product.currency',
                'sub.next_invoice_at',
            ])
            ->orderByDesc('vps.created_at')
            ->get();

        $activeServiceCount = $activeServers->count()
            + $eupServices->where('status', 'active')->count()
            + $flaxServices->where('status', 'active')->count();

        $pendingServiceCount = $orders->where('status', 'awaiting_payment')->count()
            + $eupServices->where('status', 'awaiting_payment')->count()
            + $flaxServices->whereIn('status', ['awaiting_payment', 'provisioning', 'provisioning_failed'])->count();

        $totalOrderCount = $orders->count() + $eupServices->count() + $flaxServices->count();
        return view('store.client.index', [
            'orders' => $orders,
            'activeServers' => $activeServers,
            'serverOrders' => $orders->whereNotNull('server_id')->keyBy('server_id'),
            'pendingOrders' => $orders->where('status', 'awaiting_payment'),
            'eupServices' => $eupServices,
            'flaxServices' => $flaxServices,
            'activeServiceCount' => $activeServiceCount,
            'pendingServiceCount' => $pendingServiceCount,
            'totalOrderCount' => $totalOrderCount,
            'user' => $user,
        ]);
    }

    public function clientServers(Request $request)
    {
        $user = $request->user();

        $servers = $user->accessibleServers()
            ->with(['node', 'allocation'])
            ->orderByDesc('servers.created_at')
            ->get();

        $serverOrders = StoreOrder::with('product')
            ->where('user_id', $user->id)
            ->whereNotNull('server_id')
            ->latest()
            ->get()
            ->keyBy('server_id');

        $serverSubscriptions = DB::table('nodexa_subscriptions')
            ->where('user_id', $user->id)
            ->whereNotNull('server_id')
            ->whereIn('status', ['active', 'cancel_pending'])
            ->where(function ($query) {
                $query->whereNull('service_type')->orWhere('service_type', 'game_server');
            })
            ->pluck('id', 'server_id');

        return view('store.client.servers', compact('servers', 'serverOrders', 'serverSubscriptions'));
    }

    public function clientBilling(Request $request)
    {
        $orders = StoreOrder::with(['product','server'])
            ->where('user_id', $request->user()->id)
            ->where('hidden_from_client', false)
            ->latest()
            ->get();

        $invoices = DB::table('nodexa_invoices')
            ->where('user_id', $request->user()->id)
            ->where(function ($query) {
                $query->whereNull('hidden_from_client')->orWhere('hidden_from_client', false);
            })
            ->orderByDesc('id')
            ->get();

        $cartCheckouts = DB::table('nodexa_cart_checkouts')
            ->where('user_id', $request->user()->id)
            ->where('hidden_from_client', false)
            ->orderByDesc('id')
            ->get();

        $subscriptions = DB::table('nodexa_subscriptions')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->get();

        // Display the actual purchased product name instead of only the
        // subscription number. All lookups remain limited to this customer.
        $vpsNames = DB::table('nodexa_flax_services')
            ->where('user_id', $request->user()->id)->pluck('hostname', 'id');
        $eupNames = DB::table('nodexa_cfx_eup_orders')
            ->where('user_id', $request->user()->id)->pluck('description', 'id');
        $ownedOrders = $orders->keyBy('id');
        $subscriptionLabels = [];

        foreach ($subscriptions as $subscription) {
            $kind = (string) ($subscription->service_type ?? '');
            $label = trim((string) ($subscription->description ?? ''));

            if ($kind === 'flax_vps') {
                $label = $vpsNames->get((int) $subscription->service_reference) ?: $label ?: 'VPS Hosting';
            } elseif ($kind === 'cfx_eup') {
                $label = $eupNames->get((int) $subscription->service_reference) ?: $label ?: 'CFX EUP';
            } elseif ($kind === '' || $kind === 'game_server') {
                $order = $ownedOrders->get((int) $subscription->order_id);
                $label = $order?->server_name ?: $order?->product?->name ?: $label ?: 'Gameserver';
            }

            $subscriptionLabels[$subscription->id] = $label ?: 'Abonnement';
        }

        return view('store.client.billing', compact('orders', 'invoices', 'subscriptions', 'cartCheckouts', 'subscriptionLabels'));
    }

    public function clientProfile(Request $request)
    {
        $recentActivity = collect();
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('nodexa_audit_logs')) {
                $recentActivity = DB::table('nodexa_audit_logs')
                    ->where('user_id', $request->user()->id)
                    ->orderByDesc('id')
                    ->limit(8)
                    ->get(['area', 'action', 'created_at']);
            }
        } catch (\Throwable $exception) {
            report($exception);
        }

        return view('store.client.profile', [
            'user' => $request->user(),
            'recentActivity' => $recentActivity,
        ]);
    }

    public function show(StoreProduct $product)
    {
        abort_unless($product->enabled,404);
        $isMinecraft = $this->minecraft->isMinecraftProduct($product);
        $minecraftEggs = $isMinecraft ? $this->minecraft->eggs() : collect();

        return view('store.product', [
            'product' => $product,
            'globalSale' => $this->sale->active(),
            'isMinecraft' => $isMinecraft,
            'minecraftEggs' => $minecraftEggs,
            'minecraftVersions' => $isMinecraft ? $this->minecraft->versions() : [],
            'minecraftVersionSupport' => $minecraftEggs->mapWithKeys(
                fn ($egg) => [$egg->id => $this->minecraft->versionVariable($egg) !== null]
            )->all(),
        ]);
    }

    public function order(Request $request, StoreProduct $product)
    {
        abort_unless($product->enabled,404);
        $rules = ['server_name' => 'required|string|min:3|max:100'];
        if ($this->minecraft->isMinecraftProduct($product)) {
            $rules['minecraft_egg_id'] = 'required|integer';
            $rules['minecraft_version'] = 'nullable|string|max:32';
        }
        $data = $request->validate($rules);
        $configuration = [];

        if ($this->minecraft->isMinecraftProduct($product)) {
            try {
                $configuration = $this->minecraft->selection(
                    $product,
                    (int) $data['minecraft_egg_id'],
                    $data['minecraft_version'] ?? null
                );
            } catch (\RuntimeException $exception) {
                return back()->withInput()->withErrors(['minecraft_egg_id' => $exception->getMessage()]);
            }
        }

        $order=StoreOrder::create([
            'user_id'=>$request->user()->id,
            'product_id'=>$product->id,
            'minecraft_egg_id'=>$configuration['minecraft_egg_id'] ?? null,
            'minecraft_version'=>$configuration['minecraft_version'] ?? null,
            'status'=>'awaiting_payment',
            'amount'=>$product->price_monthly,
            'currency'=>'DKK',
            'server_name'=>$data['server_name'],
        ]);

        $referralCode = strtoupper(trim((string) $request->cookie('nodexa_ref', '')));
        if ($referralCode !== '') {
            $affiliate = DB::table('nodexa_affiliates')
                ->where('code', $referralCode)
                ->where('enabled', true)
                ->first();

            if ($affiliate && (int) $affiliate->user_id !== (int) $request->user()->id) {
                DB::table('nodexa_affiliate_events')->insert([
                    'affiliate_id' => $affiliate->id,
                    'referred_user_id' => $request->user()->id,
                    'order_id' => $order->id,
                    'type' => 'order_pending',
                    'amount' => $order->amount,
                    'commission' => 0,
                    'metadata' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return redirect()->route('store.checkout',$order);
    }

    public function checkout(Request $request, StoreOrder $order)
    {
        abort_unless($order->user_id === $request->user()->id,403);
        $order->load('product');
        abort_if($order->hidden_from_client, 404);
        return view('store.checkout',compact('order'));
    }

    public function destroyOrder(Request $request, StoreOrder $order)
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 403);

        try {
            $this->removal->removeLegacyOrder((int) $order->id, (int) $request->user()->id);
        } catch (\Throwable $exception) {
            return back()->withErrors(['order' => $exception->getMessage()]);
        }

        return redirect()->route('store.orders')
            ->with('success', 'Den ubetalte ordre er annulleret og fjernet fra oversigten.');
    }

    public function orders(Request $request)
    {
        return view('store.orders',['orders'=>StoreOrder::with(['product','server'])->where('user_id',$request->user()->id)->where('hidden_from_client', false)->latest()->get()]);
    }
}
