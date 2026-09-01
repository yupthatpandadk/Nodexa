<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StorefrontProduct extends Model
{
    protected $fillable = [
        'storefront_site_id','slug','name','description','price_cents','billing_period',
        'type','features','enabled','sort_order','settings',
    ];

    protected $casts = [
        'features' => 'array',
        'settings' => 'array',
        'enabled' => 'boolean',
        'price_cents' => 'integer',
        'sort_order' => 'integer',
    ];

    public function site(): BelongsTo
    {
        return $this->belongsTo(StorefrontSite::class, 'storefront_site_id');
    }
}
