<?php
namespace Pterodactyl\Services\Nodexa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
class PaymentCompletionService {
 public function complete(int $invoiceId,string $gateway,string $transactionId,?float $amount=null,array $meta=[]):array {
  $orderId=null;$alreadyPaid=false;
  DB::transaction(function()use($invoiceId,$gateway,$transactionId,$amount,$meta,&$orderId,&$alreadyPaid){
   $invoice=DB::table('invoices')->where('id',$invoiceId)->lockForUpdate()->first(); if(!$invoice)throw new RuntimeException('Fakturaen blev ikke fundet.');$orderId=$invoice->order_id?(int)$invoice->order_id:null;$alreadyPaid=$invoice->status==='paid';
   $paid=$amount??(float)$invoice->total;if(!$alreadyPaid&&round($paid,2)<round((float)$invoice->total,2))throw new RuntimeException('Betalingen dækker ikke fakturabeløbet.');
   if(Schema::hasTable('invoice_payments')){$exists=DB::table('invoice_payments')->where('transaction_id',$transactionId)->exists();if(!$exists)DB::table('invoice_payments')->insert(['invoice_id'=>$invoiceId,'amount'=>$paid,'currency'=>$invoice->currency,'method'=>$gateway,'transaction_id'=>$transactionId,'paid_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);}
   if(Schema::hasTable('billing_transactions')){$exists=DB::table('billing_transactions')->where('transaction_id',$transactionId)->exists();if(!$exists)DB::table('billing_transactions')->insert(['invoice_id'=>$invoiceId,'gateway'=>$gateway,'transaction_id'=>$transactionId,'amount'=>$paid,'currency'=>$invoice->currency,'status'=>'completed','meta'=>json_encode($meta),'created_at'=>now(),'updated_at'=>now()]);}
   if(!$alreadyPaid)DB::table('invoices')->where('id',$invoiceId)->update(['status'=>'paid','paid_at'=>now(),'updated_at'=>now()]);
   if($orderId&&Schema::hasTable('billing_orders')){$order=DB::table('billing_orders')->where('id',$orderId)->first();if($order&&!in_array($order->status,['processing','completed'],true))DB::table('billing_orders')->where('id',$orderId)->update(['status'=>'paid','updated_at'=>now()]);}
  });
  $provisioned=false;$provisionError=null;if($orderId&&Schema::hasTable('billing_orders')&&Schema::hasTable('billing_products')){$product=DB::table('billing_orders as o')->join('billing_products as p','p.id','=','o.product_id')->where('o.id',$orderId)->select('p.product_type')->first();if($product&&$product->product_type==='vps'){try{$result=app(ProvisioningService::class)->provisionOrder($orderId);$provisioned=(bool)($result['service']??false);}catch(\Throwable $e){report($e);$provisionError=$e->getMessage();}}}
  return ['invoice'=>DB::table('invoices')->where('id',$invoiceId)->first(),'already_paid'=>$alreadyPaid,'provisioned'=>$provisioned,'provision_error'=>$provisionError];
 }
}
