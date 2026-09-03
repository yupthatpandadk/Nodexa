<?php

namespace App\Providers;

use App\Models\PanelSetting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        try {
            if (!Schema::hasTable('panel_settings')) {
                return;
            }

            $settings = PanelSetting::values();
            config([
                'app.name' => $settings['panel_name'] ?? config('app.name'),
                'app.url' => $settings['panel_url'] ?? config('app.url'),
                'app.timezone' => $settings['timezone'] ?? config('app.timezone'),
                'app.locale' => $settings['locale'] ?? config('app.locale'),
            ]);

            $timezone = (string) ($settings['timezone'] ?? config('app.timezone', 'UTC'));
            if ($timezone !== '') {
                date_default_timezone_set($timezone);
            }
        } catch (Throwable) {
            // During first install/migrations the database may not be ready yet.
            // Nodexa falls back to the normal Laravel .env/config values.
        }
    }
}
