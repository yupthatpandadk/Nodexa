<?php
namespace Pterodactyl\Services\Nodexa;
use Illuminate\Support\Facades\Crypt;use Illuminate\Support\Facades\DB;use RuntimeException;
class CheckoutService {
 public function create(object $invoice,object $order,object $gateway):array {
  if($invoice->status==='paid')return ['status'=>'paid','redirect'=>url('/client/invoices/'.$invoice->id)];
  $name=strtolower((string)$gateway->gateway);$return=url('/client/checkout/'.$invoice->id.'?return=1');$webhook=url('/payments/webhook/'.$name);$reference=(string)$invoice->number;$amount=round((float)$invoice->total,2);$currency=strtoupper((string)$invoice->currency);
  $secret=$this->secret($gateway,'secret_key');$client=$this->secret($gateway,'client_secret');$merchant=$this->secret($gateway,'merchant_id');
  if($gateway->test_mode)return ['status'=>'test','gateway'=>$name,'reference'=>$reference,'amount'=>$amount,'currency'=>$currency,'return_url'=>$return,'webhook_url'=>$webhook];
  if($name==='stripe'){if(!$secret)throw new RuntimeException('Stripe secret key mangler.');return ['status'=>'configuration_required','gateway'=>'stripe','message'=>'Stripe er konfigureret, men direkte Checkout Session API-adapter er endnu ikke aktiveret.'];}
  if($name==='mobilepay'){if(!$client||!$merchant)throw new RuntimeException('MobilePay client secret eller merchant ID mangler.');return ['status'=>'configuration_required','gateway'=>'mobilepay','message'=>'MobilePay er konfigureret, men direkte betalingssession kræver providerens API-endpoint/credentials.'];}
  if($name==='paypal'){if(!$client)throw new RuntimeException('PayPal client secret mangler.');return ['status'=>'configuration_required','gateway'=>'paypal','message'=>'PayPal er konfigureret, men direkte Orders API-adapter er endnu ikke aktiveret.'];}
  throw new RuntimeException('Betalingsgateway understøttes ikke.');
 }
 private function secret(object $g,string $key):?string{$value=$g->{$key}??null;if(!$value)return null;try{return Crypt::decryptString($value);}catch(\Throwable $e){return null;}}
}
