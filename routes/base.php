<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Base;
use Pterodactyl\Http\Middleware\RequireTwoFactorAuthentication;

Route::get('/server/{react?}', function (?string $react = null) {
    $canonicalUrl = rtrim((string) config('app.url'), '/');
    $canonicalHost = strtolower((string) parse_url($canonicalUrl, PHP_URL_HOST));
    $currentHost = strtolower(request()->getHost());

    // Keep server management on the canonical APP_URL origin. Wings validates
    // the browser WebSocket Origin, so mixing storefront and legacy hostnames
    // can otherwise leave the console stuck on "Connecting".
    if ($canonicalHost !== '' && $currentHost !== $canonicalHost) {
        return redirect()->away($canonicalUrl . request()->getRequestUri(), 302);
    }

    return app(Base\IndexController::class)->index();
})->where('react', '.*');

Route::get('/panel', [Base\IndexController::class, 'index'])->name('index')->fallback();
Route::get('/account', [Base\IndexController::class, 'index'])
    ->withoutMiddleware(RequireTwoFactorAuthentication::class)
    ->name('account');

Route::get('/locales/locale.json', Base\LocaleController::class)
    ->withoutMiddleware(['auth', RequireTwoFactorAuthentication::class])
    ->where('namespace', '.*');

Route::get('/{react}', [Base\IndexController::class, 'index'])
    ->where('react', '^(?!(\/)?(api|auth|admin|daemon)).+');
