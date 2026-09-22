<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\StorefrontController;

Route::get('/store', [StorefrontController::class, 'index'])->name('store.index');
Route::get('/store/{product:slug}', [StorefrontController::class, 'show'])->name('store.product');
Route::post('/store/{product:slug}/order', [StorefrontController::class, 'order'])->middleware('auth.session')->name('store.order');
