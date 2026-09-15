<?php
namespace Pterodactyl\Services\Nodexa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
class ProvisioningService {
 public function provisionOrder(int $orderId):array {
  if(!Schema::hasTable('billing_orders')||!Schema::hasTable('billing_products')||!Schema::hasTable('vps_services')) throw new RuntimeException('Provisioning-tabeller mangler. Kør Nodexa migrations.');
  $order=DB::table('billing_orders')->where('id',$orderId)->lockForUpdate()->first(); if(!$order)throw new RuntimeException('Ordren blev ikke fundet.');
  if(!in_array($order->status,['paid','processing'],true))throw new RuntimeException('Ordren skal være betalt før provisioning.');
  $existing=DB::table('vps_services')->where('order_id',$orderId)->first(); if($existing&&$existing->provider_server_id)return ['service'=>$existing,'already_provisioned'=>true];
  $product=DB::table('billing_products')->where('id',$order->product_id)->first(); if(!$product)throw new RuntimeException('Produktet blev ikke fundet.');
  if(($product->product_type??'')!=='vps')throw new RuntimeException('Automatisk provisioning understøtter endnu kun VPS-produkter.');
  $cfg=json_decode((string)($product->provisioning??''),true)?:[]; $productId=(string)($product->provider_product_id??$cfg['product_id']??''); $osId=(string)($product->default_os_id??$cfg['os_id']??'');
  if($productId===''||$osId==='')throw new RuntimeException('VPS-pakken mangler provider product ID eller operativsystem.');
  DB::table('billing_orders')->where('id',$orderId)->update(['status'=>'processing','updated_at'=>now()]);
  $provider=DB::table('vps_providers')->where('active',true)->first()?:DB::table('vps_providers')->first(); if(!$provider)throw new RuntimeException('Flax VPS provider er ikke konfigureret.');
  $serviceId=$existing?->id; if(!$serviceId)$serviceId=DB::table('vps_services')->insertGetId(['user_id'=>$order->user_id,'product_id'=>$product->id,'provider_id'=>$provider->id,'order_id'=>$orderId,'provider_server_id'=>null,'hostname'=>null,'ip_address'=>null,'os_id'=>$osId,'status'=>'provisioning','meta'=>json_encode(['started_at'=>now()->toIso8601String()]),'created_at'=>now(),'updated_at'=>now()]);
  try{$api=new FlaxVpsClient($provider);$response=$api->create($productId,$osId);$remote=(string)($response['id']??$response['server_id']??$response['serverId']??$response['uuid']??$response['service_id']??'');if($remote==='')throw new RuntimeException('Flax oprettede VPS’en, men returnerede ikke et server-ID.');DB::table('vps_services')->where('id',$serviceId)->update(['provider_server_id'=>$remote,'status'=>'provisioning','meta'=>json_encode(['started_at'=>now()->toIso8601String(),'provider_response'=>$response]),'updated_at'=>now()]);DB::table('billing_orders')->where('id',$orderId)->update(['status'=>'completed','updated_at'=>now()]);return ['service'=>DB::table('vps_services')->where('id',$serviceId)->first(),'already_provisioned'=>false];}
  catch(\Throwable $e){DB::table('vps_services')->where('id',$serviceId)->update(['status'=>'provision_failed','meta'=>json_encode(['failed_at'=>now()->toIso8601String(),'error'=>$e->getMessage()]),'updated_at'=>now()]);DB::table('billing_orders')->where('id',$orderId)->update(['status'=>'paid','updated_at'=>now()]);throw $e;}
 }
}
