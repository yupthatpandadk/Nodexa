<?php
namespace Pterodactyl\Services\Nodexa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
class PaymentCompletionService {
 public function complete(int $invoiceId,string $gateway,string $transactionId,?float $amount=null,array $meta=[]):array {
  return DB::transaction(function()use($invoiceId,$gateway,$transactionId,$amount,$meta){
   $invoice=DB::table('invoices')->where('id',$invoiceId)->lockForUpdate()->first(); if(!$invoice)throw new RuntimeException('Fakturaen blev ikke fundet.');
   if($invoice->status==='paid')return ['invoice'=>$invoice,'already_paid'=>true,'provisioned'=>false];
   $paid=$amount??(float)$invoice->total; if(round($paid,2)<round((float)$invoice->total,2))throw new RuntimeException('Betalingen dækker ikke fakturabeløbet.');
   if(Schema::hasTable('invoice_payments')){ $exists=DB::table('invoice_payments')->where('transaction_id',$transactionId)->exists(); if(!$exists)DB::table('invoice_payments')->insert(['invoice_id'=>$invoiceId,'amount'=>$paid,'currency'=>$invoice->currency,'method'=>$gateway,'transaction_id'=>$transactionId,'paid_at'=>now(),'created_at'=>now(),'updated_at'=>now()]); }
   if(Schema::hasTable('billing_transactions')){ $exists=DB::table('billing_transactions')->where('transaction_id',$transactionId)->exists(); if(!$exists)DB::table('billing_transactions')->insert(['invoice_id'=>$invoiceId,'gateway'=>$gateway,'transaction_id'=>$transactionId,'amount'=>$paid,'currency'=>$invoice->currency,'status'=>'completed','meta'=>json_encode($meta),'created_at'=>now(),'updated_at'=>now()]); }
   DB::table('invoices')->where('id',$invoiceId)->update(['status'=>'paid','paid_at'=>now(),'updated_at'=>now()]);
   $provisioned=false;if($invoice->order_id&&Schema::hasTable('billing_orders')){DB::table('billing_orders')->where('id',$invoice->order_id)->update(['status'=>'paid','updated_at'=>now()]);$product=DB::table('billing_orders as o')->join('billing_products as p','p.id','=','o.product_id')->where('o.id',$invoice->order_id)->select('p.product_type')->first();if($product&&$product->product_type==='vps'){try{app(ProvisioningService::class)->provisionOrder((int)$invoice->order_id);$provisioned=true;}catch(\Throwable $e){report($e);}}}
   return ['invoice'=>DB::table('invoices')->where('id',$invoiceId)->first(),'already_paid'=>false,'provisioned'=>$provisioned];
  });
 }
}
