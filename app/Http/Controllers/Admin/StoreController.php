<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Pterodactyl\Http\Controllers\Controller;
use Pterodactyl\Models\Egg;
use Pterodactyl\Models\StoreProduct;
use Pterodactyl\Models\StoreOrder;

class StoreController extends Controller
{
    public function index(){ return view('admin.store.index',['products'=>StoreProduct::orderBy('name')->get(),'orders'=>StoreOrder::with(['product','user'])->latest()->limit(50)->get(),'eggs'=>Egg::orderBy('name')->get()]); }
    public function store(Request $r){
        $d=$r->validate(['name'=>'required|max:100','game'=>'required|max:80','description'=>'nullable|max:2000','price_monthly'=>'required|numeric|min:0','egg_id'=>'required|exists:eggs,id','memory'=>'required|integer|min:128','disk'=>'required|integer|min:256','cpu'=>'required|integer|min:10','databases'=>'required|integer|min:0','backups'=>'required|integer|min:0','allocations'=>'required|integer|min:1']);
        $d['slug']=Str::slug($d['name']).'-'.Str::lower(Str::random(5)); $d['enabled']=$r->boolean('enabled'); $d['featured']=$r->boolean('featured'); StoreProduct::create($d);
        return back()->with('success','Produkt oprettet.');
    }
    public function update(Request $r, StoreProduct $product){ $product->update(['enabled'=>$r->boolean('enabled'),'featured'=>$r->boolean('featured')]); return back(); }
    public function destroy(StoreProduct $product){ $product->delete(); return back(); }
}
