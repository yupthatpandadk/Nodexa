<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\StorefrontController;

Route::prefix('store')->name('storefront.')->group(function () {
    Route::get('/', [StorefrontController::class, 'home'])->name('home');
    Route::get('/games', [StorefrontController::class, 'games'])->name('games');
    Route::get('/pricing', [StorefrontController::class, 'pricing'])->name('pricing');
    Route::get('/features', [StorefrontController::class, 'features'])->name('features');
    Route::get('/support', [StorefrontController::class, 'support'])->name('support');
});

Route::redirect('/storefront', '/store', 301);
