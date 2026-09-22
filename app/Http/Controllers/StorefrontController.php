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

    public function show(StoreProduct $product)
    {
        abort_unless($product->enabled,404);
        return view('store.product', compact('product'));
    }

    public function order(Request $request, StoreProduct $product)
    {
        abort_unless($product->enabled,404);
        $order=StoreOrder::create([
            'user_id'=>$request->user()->id,'product_id'=>$product->id,'status'=>'pending',
            'amount'=>$product->price_monthly,'currency'=>'DKK',
        ]);
        return redirect()->route('store.product',$product)->with('success',"Ordre #{$order->id} er oprettet. Betaling/provisionering afventer.");
    }
}
