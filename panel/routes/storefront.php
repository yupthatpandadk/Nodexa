<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\StorefrontController;
use Pterodactyl\Http\Controllers\StorefrontCustomerController;

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

if ($panelHost !== '') {
    if (str_starts_with($panelHost, 'panel.')) {
        $baseHost = substr($panelHost, 6);
        $addStorefrontHost($baseHost);
        $addStorefrontHost('www.' . $baseHost);
    } else {
        $addStorefrontHost('www.' . $panelHost);
    }
}

$customerRoutes = static function (): void {
    Route::middleware('auth')->prefix('client')->name('client.')->group(function () {
        Route::get('/', [StorefrontCustomerController::class, 'dashboard'])->name('dashboard');
        Route::get('/services', [StorefrontCustomerController::class, 'services'])->name('services');
        Route::get('/invoices', [StorefrontCustomerController::class, 'invoices'])->name('invoices');
        Route::get('/support', [StorefrontCustomerController::class, 'support'])->name('support');
        Route::get('/account', [StorefrontCustomerController::class, 'account'])->name('account');
    });
};

foreach ($storefrontHosts as $index => $host) {
    Route::domain($host)->name("storefront.host{$index}.")->group(function () use ($customerRoutes) {
        Route::get('/', [StorefrontController::class, 'home'])->name('home');
        Route::get('/games', [StorefrontController::class, 'games'])->name('games');
        Route::get('/pricing', [StorefrontController::class, 'pricing'])->name('pricing');
        Route::get('/features', [StorefrontController::class, 'features'])->name('features');
        Route::get('/support', [StorefrontController::class, 'support'])->name('support');
        $customerRoutes();
    });
}

/*
|--------------------------------------------------------------------------
| Panel customer area
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->prefix('client')->name('client.')->group(function () {
    Route::get('/', [StorefrontCustomerController::class, 'dashboard'])->name('dashboard');
    Route::get('/services', [StorefrontCustomerController::class, 'services'])->name('services');
    Route::get('/invoices', [StorefrontCustomerController::class, 'invoices'])->name('invoices');
    Route::get('/support', [StorefrontCustomerController::class, 'support'])->name('support');
    Route::get('/account', [StorefrontCustomerController::class, 'account'])->name('account');
});

/*
|--------------------------------------------------------------------------
| Storefront compatibility paths
|--------------------------------------------------------------------------
*/
Route::prefix('store')->name('storefront.')->group(function () {
    Route::get('/', [StorefrontController::class, 'home'])->name('home');
    Route::get('/games', [StorefrontController::class, 'games'])->name('games');
    Route::get('/pricing', [StorefrontController::class, 'pricing'])->name('pricing');
    Route::get('/features', [StorefrontController::class, 'features'])->name('features');
    Route::get('/support', [StorefrontController::class, 'support'])->name('support');
});

Route::redirect('/store/client', '/client', 301);
Route::redirect('/store/client/services', '/client/services', 301);
Route::redirect('/store/client/invoices', '/client/invoices', 301);
Route::redirect('/store/client/support', '/client/support', 301);
Route::redirect('/store/client/account', '/client/account', 301);
Route::redirect('/storefront', '/store', 301);
