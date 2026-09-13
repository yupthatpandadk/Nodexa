<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Auth;

/* Authentication routes — Endpoint: /auth */
Route::get('/login', [Auth\LoginController::class, 'index'])->name('auth.login');
Route::get('/register', [Auth\LoginController::class, 'index'])->name('auth.register');
Route::get('/password', [Auth\LoginController::class, 'index'])->name('auth.forgot-password');
Route::get('/password/reset/{token}', [Auth\LoginController::class, 'index'])->name('auth.reset');

// Discord OAuth is intentionally outside the password-login throttle/recaptcha flow.
// OAuth state validation and Discord's authorization endpoint protect the redirect flow.
Route::get('/discord', [Auth\DiscordLoginController::class, 'redirect'])->name('auth.discord');
Route::get('/discord/callback', [Auth\DiscordLoginController::class, 'callback'])->name('auth.discord.callback');

Route::middleware(['throttle:authentication'])->group(function () {
    Route::post('/login', [Auth\LoginController::class, 'login'])->middleware('recaptcha');
    Route::post('/login/checkpoint', Auth\LoginCheckpointController::class)->name('auth.login-checkpoint');
    Route::post('/register', [Auth\RegisterController::class, 'register'])->name('auth.post.register')->middleware('recaptcha');
    Route::post('/password', [Auth\ForgotPasswordController::class, 'sendResetLinkEmail'])->name('auth.post.forgot-password')->middleware('recaptcha');
});

Route::post('/password/reset', Auth\ResetPasswordController::class)->name('auth.reset-password');
Route::post('/logout', [Auth\LoginController::class, 'logout'])->withoutMiddleware('guest')->middleware('auth')->name('auth.logout');
Route::fallback([Auth\LoginController::class, 'index']);
