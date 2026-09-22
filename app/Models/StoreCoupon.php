<?php

namespace Pterodactyl\Models;

class StoreCoupon extends Model
{
    protected $table = 'nodexa_store_coupons';
    protected $fillable = ['code','type','value','max_uses','uses','expires_at','enabled'];
    protected $casts = ['value'=>'decimal:2','expires_at'=>'datetime','enabled'=>'boolean'];
}
