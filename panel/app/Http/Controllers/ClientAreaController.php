<?php

namespace Pterodactyl\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ClientAreaController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user(); abort_unless($user, 401);
        $services = Schema::hasTable('servers') ? DB::table('servers')->where('owner_id',$user->id)->orderByDesc('id')->get() : collect();
        $invoices = Schema::hasTable('invoices') ? DB::table('invoices')->where('user_id',$user->id)->orderByDesc('id')->limit(8)->get() : collect();
        $tickets = Schema::hasTable('tickets') ? DB::table('tickets')->where('user_id',$user->id)->orderByDesc('id')->limit(8)->get() : collect();
        $products = Schema::hasTable('billing_products') ? DB::table('billing_products')->where('active',true)->orderBy('price')->get() : collect();
        return view('client.index', compact('user','services','invoices','tickets','products'));
    }

    public function order(Request $request)
    {
        $user=$request->user(); abort_unless($user,401);
        $data=$request->validate(['product_id'=>'required|integer']);
        $product=DB::table('billing_products')->where('id',$data['product_id'])->where('active',true)->first(); abort_unless($product,404);
        $now=now();
        $orderId=DB::table('billing_orders')->insertGetId(['user_id'=>$user->id,'product_id'=>$product->id,'status'=>'pending','amount'=>$product->price,'currency'=>$product->currency,'created_at'=>$now,'updated_at'=>$now]);
        $invoiceId=DB::table('invoices')->insertGetId(['user_id'=>$user->id,'order_id'=>$orderId,'number'=>'NX-'.now()->format('Ym').'-'.strtoupper(Str::random(6)),'status'=>'unpaid','subtotal'=>$product->price,'tax'=>0,'total'=>$product->price,'currency'=>$product->currency,'due_at'=>now()->addDays(7),'created_at'=>$now,'updated_at'=>$now]);
        DB::table('invoice_items')->insert(['invoice_id'=>$invoiceId,'description'=>$product->name.' ('.$product->billing_cycle.')','quantity'=>1,'unit_price'=>$product->price,'total'=>$product->price,'created_at'=>$now,'updated_at'=>$now]);
        return redirect('/client#invoices')->with('success','Ordren er oprettet, og fakturaen er klar til betaling.');
    }

    public function createTicket(Request $request)
    {
        $user=$request->user(); abort_unless($user,401);
        $data=$request->validate(['subject'=>'required|string|max:190','message'=>'required|string|max:10000','priority'=>'nullable|in:low,normal,high']); $now=now();
        $id=DB::table('tickets')->insertGetId(['user_id'=>$user->id,'subject'=>$data['subject'],'priority'=>$data['priority']??'normal','status'=>'open','created_at'=>$now,'updated_at'=>$now]);
        DB::table('ticket_messages')->insert(['ticket_id'=>$id,'user_id'=>$user->id,'message'=>$data['message'],'staff'=>false,'created_at'=>$now,'updated_at'=>$now]);
        return redirect('/client#tickets')->with('success','Din support ticket er oprettet.');
    }
}
