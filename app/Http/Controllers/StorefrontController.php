<?php

namespace Pterodactyl\Http\Controllers;

use Illuminate\Http\Request;
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
        $orders = StoreOrder::with(['product','server'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return view('store.dashboard', [
            'orders' => $orders,
            'activeServers' => $orders->filter(fn ($order) => $order->server !== null),
            'pendingOrders' => $orders->where('status', 'awaiting_payment'),
        ]);
    }

    public function clientServers(Request $request)
    {
        $orders = StoreOrder::with(['product','server'])
            ->where('user_id', $request->user()->id)
            ->whereNotNull('server_id')
            ->latest()
            ->get();

        return view('store.client.servers', compact('orders'));
    }

    public function clientBilling(Request $request)
    {
        $orders = StoreOrder::with(['product','server'])
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return view('store.client.billing', compact('orders'));
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
