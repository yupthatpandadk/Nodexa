<?php

namespace Pterodactyl\Models;

class StoreCoupon extends Model
{
    protected $table = 'nodexa_store_coupons';
    protected $fillable = ['code','type','value','duration_months','max_uses','uses','expires_at','enabled'];
    protected $casts = ['value'=>'decimal:2','duration_months'=>'integer','expires_at'=>'datetime','enabled'=>'boolean'];
}
