<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Base;
use Pterodactyl\Http\Controllers\ClientAreaController;
use Pterodactyl\Http\Middleware\RequireTwoFactorAuthentication;

Route::get('/', [Base\IndexController::class, 'index'])->name('index')->fallback();
Route::middleware('auth')->prefix('client')->name('client.')->group(function () {
    Route::get('/', [ClientAreaController::class, 'index'])->name('area');
    Route::post('/orders', [ClientAreaController::class, 'order'])->name('orders.create');
    Route::post('/tickets', [ClientAreaController::class, 'createTicket'])->name('tickets.create');
    Route::get('/tickets/{id}', [ClientAreaController::class, 'ticket'])->name('tickets.show');
    Route::post('/tickets/{id}/reply', [ClientAreaController::class, 'replyTicket'])->name('tickets.reply');
    Route::post('/tickets/{id}/close', [ClientAreaController::class, 'closeTicket'])->name('tickets.close');
});
Route::get('/account', [Base\IndexController::class, 'index'])->withoutMiddleware(RequireTwoFactorAuthentication::class)->name('account');
Route::get('/locales/locale.json', Base\LocaleController::class)->withoutMiddleware(['auth', RequireTwoFactorAuthentication::class])->where('namespace', '.*');
Route::get('/{react}', [Base\IndexController::class, 'index'])->where('react', '^(?!(\/)?(api|auth|admin|daemon|client)).+');
