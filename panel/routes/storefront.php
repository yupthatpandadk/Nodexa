<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\StorefrontController;

/*
|--------------------------------------------------------------------------
| Dedicated Storefront hosts
|--------------------------------------------------------------------------
|
| The panel and storefront can share the same Laravel installation and Nginx
| vhost. These host-constrained routes make the public website behave like a
| normal multipage storefront while the configured panel host keeps `/` as the
| authenticated Nodexa dashboard.
|
*/
$panelHost = strtolower((string) (parse_url((string) config('app.url'), PHP_URL_HOST) ?: ''));
$configuredStorefront = strtolower(trim((string) config('nodexa.storefront_domain', '')));
$storefrontHosts = [];

$addStorefrontHost = static function (string $host) use (&$storefrontHosts, $panelHost): void {
    $host = strtolower(trim($host));
    $host = preg_replace('#^https?://#', '', $host) ?: $host;
    $host = explode('/', $host, 2)[0];
    $host = explode(':', $host, 2)[0];
    $host = rtrim($host, '.');

    if ($host === '' || $host === $panelHost || !preg_match('/^([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $host)) {
        return;
    }

    if (!in_array($host, $storefrontHosts, true)) {
        $storefrontHosts[] = $host;
    }
};

if ($configuredStorefront !== '') {
    $addStorefrontHost($configuredStorefront);
    if (!str_starts_with($configuredStorefront, 'www.')) {
        $addStorefrontHost('www.' . $configuredStorefront);
    }
}

// Automatic sensible default:
// - panel.example.com -> example.com + www.example.com are storefronts
// - example.com       -> www.example.com is the storefront and example.com
//                        remains the panel if APP_URL points there.
if ($panelHost !== '') {
    if (str_starts_with($panelHost, 'panel.')) {
        $baseHost = substr($panelHost, 6);
        $addStorefrontHost($baseHost);
        $addStorefrontHost('www.' . $baseHost);
    } else {
        $addStorefrontHost('www.' . $panelHost);
    }
}

foreach ($storefrontHosts as $index => $host) {
    Route::domain($host)->name("storefront.host{$index}.")->group(function () {
        Route::get('/', [StorefrontController::class, 'home'])->name('home');
        Route::get('/games', [StorefrontController::class, 'games'])->name('games');
        Route::get('/pricing', [StorefrontController::class, 'pricing'])->name('pricing');
        Route::get('/features', [StorefrontController::class, 'features'])->name('features');
        Route::get('/support', [StorefrontController::class, 'support'])->name('support');
    });
}

/*
|--------------------------------------------------------------------------
| Storefront compatibility paths
|--------------------------------------------------------------------------
|
| Keep /store available from the panel host as a preview and for existing
| bookmarks. Dedicated storefront hosts use the clean root URLs above.
|
*/
Route::prefix('store')->name('storefront.')->group(function () {
    Route::get('/', [StorefrontController::class, 'home'])->name('home');
    Route::get('/games', [StorefrontController::class, 'games'])->name('games');
    Route::get('/pricing', [StorefrontController::class, 'pricing'])->name('pricing');
    Route::get('/features', [StorefrontController::class, 'features'])->name('features');
    Route::get('/support', [StorefrontController::class, 'support'])->name('support');
});

Route::redirect('/storefront', '/store', 301);
