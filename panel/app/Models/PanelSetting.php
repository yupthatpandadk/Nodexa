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
            // Shared branding values. The panel and any Nodexa WWW frontend can
            // consume these values from the public /api/theme endpoint.
            'theme_primary' => '#745cff',
            'theme_secondary' => '#5b46e8',
            'theme_background' => '#0b0d12',
            'theme_surface' => '#121620',
            'theme_text' => '#f5f7fb',
        ];
    }

    public static function values(): array
    {
        $stored = static::query()->pluck('value', 'key')->all();
        return array_merge(static::defaults(), $stored);
    }

    public static function theme(): array
    {
        $values = static::values();

        return [
            'primary' => $values['theme_primary'],
            'secondary' => $values['theme_secondary'],
            'background' => $values['theme_background'],
            'surface' => $values['theme_surface'],
            'text' => $values['theme_text'],
        ];
    }

    public static function value(string $key, mixed $fallback = null): mixed
    {
        return static::query()->where('key', $key)->value('value') ?? $fallback;
    }
}
