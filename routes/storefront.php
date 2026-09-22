<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\StorefrontController;

Route::get('/', [StorefrontController::class, 'index'])->name('store.home');
Route::get('/hosting', [StorefrontController::class, 'hosting'])->name('store.hosting');
Route::get('/features', [StorefrontController::class, 'features'])->name('store.features');
Route::get('/about', [StorefrontController::class, 'about'])->name('store.about');
Route::get('/support', [StorefrontController::class, 'support'])->name('store.support');
Route::get('/store', [StorefrontController::class, 'hosting'])->name('store.index');
Route::get('/store/{product:slug}', [StorefrontController::class, 'show'])->name('store.product');
Route::middleware('auth.session')->group(function () {
    Route::get('/account', [StorefrontController::class, 'dashboard'])->name('store.dashboard');
    Route::post('/store/{product:slug}/order', [StorefrontController::class, 'order'])->name('store.order');
    Route::get('/billing/orders', [StorefrontController::class, 'orders'])->name('store.orders');
    Route::get('/billing/orders/{order}/checkout', [StorefrontController::class, 'checkout'])->name('store.checkout');
});
