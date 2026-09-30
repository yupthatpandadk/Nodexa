<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Base;
use Pterodactyl\Http\Middleware\RequireTwoFactorAuthentication;

Route::get('/server/{react?}', function (?string $react = null) {
    $panelUrl = rtrim((string) config('app.url'), '/');
    $panelHost = strtolower((string) parse_url($panelUrl, PHP_URL_HOST));
    $currentHost = strtolower(request()->getHost());

    // Server management must always run on the canonical panel host. The
    // storefront shares authentication, but Wings/WebSocket access is tied to
    // the panel origin and can fail when React is opened on nordicnode.org.
    if ($panelHost !== '' && $currentHost !== $panelHost) {
        return redirect()->away($panelUrl . request()->getRequestUri(), 302);
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
