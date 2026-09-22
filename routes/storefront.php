<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\StorefrontController;
use Pterodactyl\Http\Controllers\SupportTicketController;

Route::get('/', [StorefrontController::class, 'index'])->name('store.home');
Route::get('/hosting', [StorefrontController::class, 'hosting'])->name('store.hosting');
Route::get('/features', [StorefrontController::class, 'features'])->name('store.features');
Route::get('/about', [StorefrontController::class, 'about'])->name('store.about');
Route::get('/support', [StorefrontController::class, 'support'])->name('store.support');
Route::get('/store', [StorefrontController::class, 'hosting'])->name('store.index');
Route::get('/store/{product:slug}', [StorefrontController::class, 'show'])->name('store.product');
Route::middleware('auth.session')->group(function () {
    Route::get('/client', [StorefrontController::class, 'dashboard'])->name('store.client');
    Route::get('/client/servers', [StorefrontController::class, 'clientServers'])->name('store.client.servers');
    Route::get('/client/billing', [StorefrontController::class, 'clientBilling'])->name('store.client.billing');
    Route::get('/client/profile', [StorefrontController::class, 'clientProfile'])->name('store.client.profile');
    Route::get('/client/tickets', [SupportTicketController::class, 'index'])->name('store.client.tickets');
    Route::get('/client/tickets/new', [SupportTicketController::class, 'create'])->name('store.client.tickets.create');
    Route::post('/client/tickets', [SupportTicketController::class, 'store'])->name('store.client.tickets.store');
    Route::get('/client/tickets/{ticket}', [SupportTicketController::class, 'show'])->name('store.client.tickets.show');
    Route::post('/client/tickets/{ticket}/reply', [SupportTicketController::class, 'reply'])->name('store.client.tickets.reply');
    Route::post('/client/tickets/{ticket}/close', [SupportTicketController::class, 'close'])->name('store.client.tickets.close');
    Route::get('/account', [StorefrontController::class, 'dashboard'])->name('store.dashboard');
    Route::post('/store/{product:slug}/order', [StorefrontController::class, 'order'])->name('store.order');
    Route::get('/billing/orders', [StorefrontController::class, 'orders'])->name('store.orders');
    Route::get('/billing/orders/{order}/checkout', [StorefrontController::class, 'checkout'])->name('store.checkout');
});
