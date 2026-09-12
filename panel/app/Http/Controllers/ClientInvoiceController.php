<?php
namespace Pterodactyl\Http\Controllers;
use Illuminate\Http\Request; use Illuminate\Support\Facades\DB; use Illuminate\Support\Facades\Schema;
class ClientInvoiceController {
 public function index(Request $r){$user=$r->user();$invoices=DB::table('invoices')->where('user_id',$user->id)->orderByDesc('id')->get();return view('client.invoices',compact('user','invoices'));}
 public function show(Request $r,int $id){$user=$r->user();$invoice=DB::table('invoices')->where('id',$id)->where('user_id',$user->id)->first();abort_unless($invoice,404);$items=DB::table('invoice_items')->where('invoice_id',$id)->get();$payments=Schema::hasTable('invoice_payments')?DB::table('invoice_payments')->where('invoice_id',$id)->orderByDesc('paid_at')->get():collect();return view('client.invoice',compact('user','invoice','items','payments'));}
}
