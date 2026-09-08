<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Pterodactyl\Http\Controllers\Controller;

class CommerceController extends Controller
{
    public function billing()
    {
        $invoices = Schema::hasTable('invoices') ? DB::table('invoices')->orderByDesc('id')->limit(100)->get() : collect();
        $orders = Schema::hasTable('billing_orders') ? DB::table('billing_orders')->orderByDesc('id')->limit(100)->get() : collect();
        $products = Schema::hasTable('billing_products') ? DB::table('billing_products')->orderBy('sort_order')->orderBy('name')->get() : collect();
        $categories = Schema::hasTable('billing_categories') ? DB::table('billing_categories')->orderBy('sort_order')->orderBy('name')->get() : collect();
        return view('admin.commerce.billing', compact('invoices', 'orders', 'products', 'categories'));
    }

    public function storeCategory(Request $request)
    {
        $data=$request->validate(['name'=>'required|string|max:100','description'=>'nullable|string|max:1000','sort_order'=>'nullable|integer|min:0|max:9999']);
        $slug=$this->uniqueSlug('billing_categories',$data['name']);
        DB::table('billing_categories')->insert(['name'=>$data['name'],'slug'=>$slug,'description'=>$data['description']??null,'sort_order'=>$data['sort_order']??0,'active'=>$request->boolean('active',true),'created_at'=>now(),'updated_at'=>now()]);
        return back()->with('success','Kategorien blev oprettet.');
    }

    public function updateCategory(Request $request, int $id)
    {
        $data=$request->validate(['name'=>'required|string|max:100','description'=>'nullable|string|max:1000','sort_order'=>'nullable|integer|min:0|max:9999']);
        DB::table('billing_categories')->where('id',$id)->update(['name'=>$data['name'],'description'=>$data['description']??null,'sort_order'=>$data['sort_order']??0,'active'=>$request->boolean('active'),'updated_at'=>now()]);
        return back()->with('success','Kategorien blev gemt.');
    }

    public function deleteCategory(int $id)
    {
        DB::table('billing_products')->where('category_id',$id)->update(['category_id'=>null]);
        DB::table('billing_categories')->where('id',$id)->delete();
        return back()->with('success','Kategorien blev slettet.');
    }

    public function storeProduct(Request $request)
    {
        $data=$this->validateProduct($request); $slug=$this->uniqueSlug('billing_products',$data['name']);
        DB::table('billing_products')->insert(array_merge($this->productData($request,$data),['slug'=>$slug,'created_at'=>now(),'updated_at'=>now()]));
        return back()->with('success','Pakken blev oprettet.');
    }

    public function updateProduct(Request $request, int $id)
    {
        $data=$this->validateProduct($request);
        DB::table('billing_products')->where('id',$id)->update(array_merge($this->productData($request,$data),['updated_at'=>now()]));
        return back()->with('success','Pakken blev gemt.');
    }

    public function deleteProduct(int $id)
    {
        if (DB::table('billing_orders')->where('product_id',$id)->exists()) {
            DB::table('billing_products')->where('id',$id)->update(['active'=>false,'updated_at'=>now()]);
            return back()->with('success','Pakken har eksisterende ordrer og blev derfor deaktiveret.');
        }
        DB::table('billing_products')->where('id',$id)->delete();
        return back()->with('success','Pakken blev slettet.');
    }

    public function tickets()
    {
        $tickets = Schema::hasTable('tickets') ? DB::table('tickets')->orderByDesc('updated_at')->limit(100)->get() : collect();
        return view('admin.commerce.tickets', compact('tickets'));
    }

    private function validateProduct(Request $request): array
    {
        return $request->validate(['name'=>'required|string|max:120','description'=>'nullable|string|max:2000','category_id'=>'nullable|integer|exists:billing_categories,id','price'=>'required|numeric|min:0|max:999999.99','currency'=>'required|string|size:3','billing_cycle'=>'required|in:monthly,quarterly,semiannually,annually,one_time','memory_mb'=>'nullable|integer|min:0|max:1048576','disk_mb'=>'nullable|integer|min:0|max:10485760','cpu_percent'=>'nullable|integer|min:0|max:100000','databases_limit'=>'nullable|integer|min:0|max:1000','backups_limit'=>'nullable|integer|min:0|max:1000','allocations_limit'=>'nullable|integer|min:0|max:1000','sort_order'=>'nullable|integer|min:0|max:9999']);
    }

    private function productData(Request $request,array $d): array
    {
        return ['name'=>$d['name'],'description'=>$d['description']??null,'category_id'=>$d['category_id']??null,'price'=>$d['price'],'currency'=>strtoupper($d['currency']),'billing_cycle'=>$d['billing_cycle'],'active'=>$request->boolean('active'),'memory_mb'=>$d['memory_mb']??null,'disk_mb'=>$d['disk_mb']??null,'cpu_percent'=>$d['cpu_percent']??null,'databases_limit'=>$d['databases_limit']??0,'backups_limit'=>$d['backups_limit']??0,'allocations_limit'=>$d['allocations_limit']??1,'sort_order'=>$d['sort_order']??0,'provisioning'=>json_encode(['memory_mb'=>$d['memory_mb']??null,'disk_mb'=>$d['disk_mb']??null,'cpu_percent'=>$d['cpu_percent']??null,'databases'=>$d['databases_limit']??0,'backups'=>$d['backups_limit']??0,'allocations'=>$d['allocations_limit']??1])];
    }

    private function uniqueSlug(string $table,string $name): string
    {
        $base=Str::slug($name) ?: 'item'; $slug=$base; $i=2;
        while(DB::table($table)->where('slug',$slug)->exists()) $slug=$base.'-'.$i++;
        return $slug;
    }
}
