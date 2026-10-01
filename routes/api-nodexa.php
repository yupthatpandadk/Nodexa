<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Api\Nodexa\AccountController;

Route::get('/me', [AccountController::class, 'profile']);
Route::get('/servers', [AccountController::class, 'servers']);
Route::get('/invoices', [AccountController::class, 'invoices']);
