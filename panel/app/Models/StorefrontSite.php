<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StorefrontSite extends Model
{
    protected $fillable = [
        'name','slug','primary_domain','aliases','enabled','is_default','logo_url',
        'primary_color','accent_color','currency','locale','title','tagline','description',
        'support_email','panel_url','settings',
    ];

    protected $casts = [
        'aliases' => 'array',
        'settings' => 'array',
        'enabled' => 'boolean',
        'is_default' => 'boolean',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(StorefrontProduct::class)->orderBy('sort_order')->orderBy('id');
    }

    public static function normalizeDomain(?string $domain): string
    {
        $domain = strtolower(trim((string) $domain));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = explode('/', $domain, 2)[0];
        $domain = explode(':', $domain, 2)[0];
        return rtrim($domain, '.');
    }

    public static function resolveDomain(string $host): ?self
    {
        $host = self::normalizeDomain($host);
        if ($host === '') return null;

        $direct = static::query()->where('enabled', true)->where('primary_domain', $host)->first();
        if ($direct) return $direct;

        return static::query()->where('enabled', true)->get()->first(function (self $site) use ($host) {
            return in_array($host, array_map([self::class, 'normalizeDomain'], $site->aliases ?? []), true);
        });
    }
}
