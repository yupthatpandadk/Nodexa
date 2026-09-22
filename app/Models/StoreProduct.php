<?php

namespace Pterodactyl\Models;

class StoreProduct extends Model
{
    protected $table = 'nodexa_store_products';
    protected $fillable = ['name','slug','game','description','price_monthly','egg_id','memory','disk','cpu','databases','backups','allocations','enabled','featured'];
    protected $casts = ['price_monthly'=>'decimal:2','enabled'=>'boolean','featured'=>'boolean'];
    public function egg(){ return $this->belongsTo(Egg::class); }
}
