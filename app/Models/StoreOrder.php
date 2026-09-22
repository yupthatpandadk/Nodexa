<?php

namespace Pterodactyl\Models;

class StoreOrder extends Model
{
    protected $table = 'nodexa_store_orders';
    protected $fillable = ['user_id','product_id','status','amount','currency'];
    protected $casts = ['amount'=>'decimal:2'];
    public function product(){ return $this->belongsTo(StoreProduct::class,'product_id'); }
    public function user(){ return $this->belongsTo(User::class); }
}
