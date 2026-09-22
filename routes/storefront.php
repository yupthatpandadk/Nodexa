<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\StorefrontController;

Route::get('/', [StorefrontController::class, 'index'])->name('store.home');
Route::get('/store', [StorefrontController::class, 'index'])->name('store.index');
Route::get('/store/{product:slug}', [StorefrontController::class, 'show'])->name('store.product');
Route::middleware('auth.session')->group(function () {
    Route::post('/store/{product:slug}/order', [StorefrontController::class, 'order'])->name('store.order');
    Route::get('/billing/orders', [StorefrontController::class, 'orders'])->name('store.orders');
    Route::get('/billing/orders/{order}/checkout', [StorefrontController::class, 'checkout'])->name('store.checkout');
});
