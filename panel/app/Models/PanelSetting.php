<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PanelSetting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function defaults(): array
    {
        return [
            'panel_name' => (string) config('app.name', 'Nodexa'),
            'company_name' => 'Nodexa Hosting',
            'panel_url' => rtrim((string) config('app.url', 'http://localhost'), '/'),
            'timezone' => (string) config('app.timezone', 'UTC'),
            'locale' => (string) config('app.locale', 'en'),
            'support_email' => '',
        ];
    }

    public static function values(): array
    {
        $stored = static::query()->pluck('value', 'key')->all();
        return array_merge(static::defaults(), $stored);
    }

    public static function value(string $key, mixed $fallback = null): mixed
    {
        return static::query()->where('key', $key)->value('value') ?? $fallback;
    }
}
