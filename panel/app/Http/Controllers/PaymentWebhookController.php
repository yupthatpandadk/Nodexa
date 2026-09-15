<?php
namespace Pterodactyl\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Pterodactyl\Services\Nodexa\PaymentCompletionService;
class PaymentWebhookController extends Controller {
 public function handle(Request $request,string $gateway,PaymentCompletionService $payments){
  abort_unless(in_array($gateway,['stripe','mobilepay','paypal'],true),404);$cfg=DB::table('payment_gateways')->where('gateway',$gateway)->where('enabled',true)->first();abort_unless($cfg,404);
  $secret='';try{$secret=$cfg->webhook_secret?Crypt::decryptString($cfg->webhook_secret):'';}catch(\Throwable $e){}if($secret==='')return response()->json(['error'=>'Webhook secret mangler'],503);
  $raw=$request->getContent();$provided=(string)($request->header('X-Nodexa-Signature')?:$request->header('X-Webhook-Signature'));$expected=hash_hmac('sha256',$raw,$secret);if(!hash_equals($expected,$provided))return response()->json(['error'=>'Ugyldig signatur'],401);
  $data=$request->json()->all();$invoiceId=(int)($data['invoice_id']??0);$transaction=(string)($data['transaction_id']??'');$status=strtolower((string)($data['status']??''));if(!$invoiceId||$transaction===''||!in_array($status,['paid','completed','succeeded'],true))return response()->json(['error'=>'Ugyldigt webhook payload'],422);
  try{$result=$payments->complete($invoiceId,$gateway,$transaction,isset($data['amount'])?(float)$data['amount']:null,$data);return response()->json(['ok'=>true,'already_paid'=>$result['already_paid'],'provisioned'=>$result['provisioned']]);}catch(\Throwable $e){report($e);return response()->json(['error'=>'Betalingen kunne ikke færdigbehandles'],422);}
 }
}
