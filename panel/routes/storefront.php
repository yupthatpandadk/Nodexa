<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\StorefrontController;
use Pterodactyl\Http\Controllers\ClientAreaController;
use Pterodactyl\Http\Controllers\ClientInvoiceController;
use Pterodactyl\Http\Controllers\DiscountOrderController;

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

$registerPublicStorefront = static function (): void {
    Route::get('/', [StorefrontController::class, 'home'])->name('home');
    Route::get('/games', [StorefrontController::class, 'games'])->name('games');
    Route::get('/pricing', [StorefrontController::class, 'pricing'])->name('pricing');
    Route::get('/vps', [StorefrontController::class, 'vps'])->name('vps');
    Route::get('/features', [StorefrontController::class, 'features'])->name('features');
    Route::get('/support', [StorefrontController::class, 'support'])->name('support');
};

$registerClientArea = static function (): void {
    Route::middleware('auth')->prefix('client')->name('client.')->group(function () {
        Route::get('/', [ClientAreaController::class, 'index'])->name('area');
        Route::get('/invoices', [ClientInvoiceController::class, 'index'])->name('invoices');
        Route::get('/invoices/{id}', [ClientInvoiceController::class, 'show'])->name('invoices.show');
        Route::get('/vps/{id}', [ClientAreaController::class, 'vps'])->name('vps.show');
        Route::post('/vps/{id}/power', [ClientAreaController::class, 'vpsPower'])->name('vps.power');
        Route::post('/vps/{id}/rebuild', [ClientAreaController::class, 'vpsRebuild'])->name('vps.rebuild');
        Route::post('/vps/{id}/reset-password', [ClientAreaController::class, 'vpsResetPassword'])->name('vps.reset-password');
        Route::get('/order/{product}', [ClientAreaController::class, 'orderPage'])->name('orders.show');
        Route::post('/orders', [DiscountOrderController::class, 'order'])->name('orders.create');
        Route::get('/checkout/{invoice}', [ClientAreaController::class, 'checkout'])->name('checkout.show');
        Route::post('/tickets', [ClientAreaController::class, 'createTicket'])->name('tickets.create');
        Route::get('/tickets/{id}', [ClientAreaController::class, 'ticket'])->name('tickets.show');
        Route::post('/tickets/{id}/reply', [ClientAreaController::class, 'replyTicket'])->name('tickets.reply');
        Route::post('/tickets/{id}/close', [ClientAreaController::class, 'closeTicket'])->name('tickets.close');
    });
};

foreach ($storefrontHosts as $index => $host) {
    Route::domain($host)->name("storefront.host{$index}.")->group(function () use ($registerPublicStorefront, $registerClientArea) {
        $registerPublicStorefront();
        $registerClientArea();
    });
}

Route::prefix('store')->name('storefront.')->group(function () use ($registerPublicStorefront) {
    $registerPublicStorefront();
});

Route::redirect('/storefront', '/store', 301);
