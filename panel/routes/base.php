<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Base;
use Pterodactyl\Http\Middleware\RequireTwoFactorAuthentication;

Route::get('/', [Base\IndexController::class, 'index'])->name('index')->fallback();
Route::get('/account', [Base\IndexController::class, 'index'])
    ->withoutMiddleware(RequireTwoFactorAuthentication::class)
    ->name('account');

Route::get('/tickets', [Base\TicketController::class, 'index'])->name('tickets.index');
Route::post('/tickets', [Base\TicketController::class, 'store'])->name('tickets.store');
Route::post('/tickets/{ticket}/reply', [Base\TicketController::class, 'reply'])->name('tickets.reply');
Route::post('/tickets/{ticket}/close', [Base\TicketController::class, 'close'])->name('tickets.close');

Route::get('/locales/locale.json', Base\LocaleController::class)
    ->withoutMiddleware(['auth', RequireTwoFactorAuthentication::class])
    ->where('namespace', '.*');

Route::get('/{react}', [Base\IndexController::class, 'index'])
    ->where('react', '^(?!(\/)?(api|auth|admin|daemon|tickets)).+');
