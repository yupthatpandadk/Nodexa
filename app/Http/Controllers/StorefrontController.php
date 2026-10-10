<?php

namespace Pterodactyl\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Models\StoreProduct;
use Pterodactyl\Models\StoreOrder;

class StorefrontController extends Controller
{
    public function index()
    {
        return view('store.index', ['products'=>StoreProduct::where('enabled',true)->orderByDesc('featured')->orderBy('price_monthly')->get()]);
    }

    public function hosting()
    {
        return view('store.hosting', ['products'=>StoreProduct::where('enabled',true)->orderByDesc('featured')->orderBy('price_monthly')->get()]);
    }

    public function features() { return view('store.features'); }
    public function about() { return view('store.about'); }
    public function support() { return view('store.support'); }

    public function dashboard(Request $request)
    {
        $user = $request->user();

        $orders = StoreOrder::with(['product','server'])
            ->where('user_id', $user->id)
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

        $activeServiceCount = $activeServers->count() + $eupServices->where('status', 'active')->count();
        $pendingServiceCount = $orders->where('status', 'awaiting_payment')->count() + $eupServices->where('status', 'awaiting_payment')->count();
        $totalOrderCount = $orders->count() + $eupServices->count();

        return view('store.client.index', [
            'orders' => $orders,
            'activeServers' => $activeServers,
            'serverOrders' => $orders->whereNotNull('server_id')->keyBy('server_id'),
            'pendingOrders' => $orders->where('status', 'awaiting_payment'),
            'eupServices' => $eupServices,
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

        return view('store.client.servers', compact('servers', 'serverOrders'));
    }

    public function clientBilling(Request $request)
    {
        $orders = StoreOrder::with(['product','server'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        $invoices = DB::table('nodexa_invoices')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->get();

        $subscriptions = DB::table('nodexa_subscriptions')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->get();

        return view('store.client.billing', compact('orders', 'invoices', 'subscriptions'));
    }

    public function clientProfile(Request $request)
    {
        return view('store.client.profile', ['user' => $request->user()]);
    }

    public function show(StoreProduct $product)
    {
        abort_unless($product->enabled,404);
        return view('store.product', compact('product'));
    }

    public function order(Request $request, StoreProduct $product)
    {
        abort_unless($product->enabled,404);
        $data=$request->validate(['server_name'=>'required|string|min:3|max:100']);
        $order=StoreOrder::create([
            'user_id'=>$request->user()->id,
            'product_id'=>$product->id,
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
        return view('store.checkout',compact('order'));
    }

    public function orders(Request $request)
    {
        return view('store.orders',['orders'=>StoreOrder::with(['product','server'])->where('user_id',$request->user()->id)->latest()->get()]);
    }
}
