<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Admin\DiagnosticsController;
use Pterodactyl\Http\Controllers\Admin\UpdateController;
use Pterodactyl\Http\Controllers\Admin\CommerceController;

Route::group(['prefix' => 'updates'], function () {
    Route::get('/', [UpdateController::class, 'index'])->name('admin.updates');
    Route::get('/status', [UpdateController::class, 'status'])->name('admin.updates.status');
    Route::post('/run', [UpdateController::class, 'run'])->name('admin.updates.run');
});

Route::group(['prefix' => 'diagnostics'], function () {
    Route::get('/', [DiagnosticsController::class, 'index'])->name('admin.diagnostics');
    Route::post('/fix', [DiagnosticsController::class, 'fix'])->name('admin.diagnostics.fix');
});

Route::get('/billing', [CommerceController::class, 'billing'])->name('admin.billing');
Route::get('/tickets', [CommerceController::class, 'tickets'])->name('admin.tickets');
