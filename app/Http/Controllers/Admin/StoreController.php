<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\StoreCoupon;
use Pterodactyl\Models\StoreProduct;
use Pterodactyl\Models\StoreOrder;
use Pterodactyl\Services\Store\ProvisionStoreOrderService;

class StoreController extends Controller
{
    public function index(){
        return view('admin.store.index',[
            'products'=>StoreProduct::orderBy('name')->get(),
            'orders'=>StoreOrder::with(['product','user','server'])->latest()->limit(100)->get(),
            'coupons'=>StoreCoupon::latest()->get(),
            'eggs'=>Egg::orderBy('name')->get(),
        ]);
    }

    public function store(Request $r){
        $d=$r->validate(['name'=>'required|max:100','game'=>'required|max:80','description'=>'nullable|max:2000','price_monthly'=>'required|numeric|min:0','egg_id'=>'required|exists:eggs,id','memory'=>'required|integer|min:128','disk'=>'required|integer|min:256','cpu'=>'required|integer|min:10','databases'=>'required|integer|min:0','backups'=>'required|integer|min:0','allocations'=>'required|integer|min:1']);
        $d['slug']=Str::slug($d['name']).'-'.Str::lower(Str::random(5)); $d['enabled']=$r->boolean('enabled'); $d['featured']=$r->boolean('featured'); StoreProduct::create($d);
        return back()->with('success','Produkt oprettet.');
    }

    public function update(Request $r, StoreProduct $product){
        $product->update(['enabled'=>$r->boolean('enabled'),'featured'=>$r->boolean('featured')]);
        return back()->with('success','Produkt opdateret.');
    }

    public function destroy(StoreProduct $product){ $product->delete(); return back()->with('success','Produkt slettet.'); }

    public function coupon(Request $r){
        $d=$r->validate(['code'=>'required|string|max:40|unique:nodexa_store_coupons,code','type'=>'required|in:percent,fixed','value'=>'required|numeric|min:0','max_uses'=>'nullable|integer|min:1','expires_at'=>'nullable|date']);
        $d['code']=strtoupper(trim($d['code'])); $d['enabled']=true; StoreCoupon::create($d);
        return back()->with('success','Rabatkode oprettet.');
    }

    public function orderStatus(Request $r, StoreOrder $order, ProvisionStoreOrderService $provision){
        $status=$r->validate(['status'=>'required|in:awaiting_payment,paid,active,cancelled,refunded'])['status'];
        if ($status === 'paid' && !$order->paid_at) $order->paid_at=now();
        if ($status === 'cancelled') $order->cancelled_at=now();
        $order->status=$status; $order->save();

        if ($status === 'paid' && !$order->server_id) {
            try { $provision->handle($order); }
            catch (\Throwable $e) { report($e); return back()->withErrors(['store'=>'Betaling markeret, men serveren kunne ikke provisioneres: '.$e->getMessage()]); }
        }
        return back()->with('success','Ordrestatus opdateret.');
    }
}
