<?php

namespace Pterodactyl\Models;

class StoreOrder extends Model
{
    protected $table = 'nodexa_store_orders';
    protected $fillable = ['user_id','product_id','server_id','status','amount','currency','server_name','payment_method','payment_reference','paid_at','provisioned_at'];
    protected $casts = ['amount'=>'decimal:2','paid_at'=>'datetime','provisioned_at'=>'datetime'];
    public function product(){ return $this->belongsTo(StoreProduct::class,'product_id'); }
    public function user(){ return $this->belongsTo(User::class); }
    public function server(){ return $this->belongsTo(Server::class); }
}
