<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Admin\UpdateController;

Route::group(['prefix' => 'updates'], function () {
    Route::get('/', [UpdateController::class, 'index'])->name('admin.updates');
    Route::get('/status', [UpdateController::class, 'status'])->name('admin.updates.status');
    Route::post('/run', [UpdateController::class, 'run'])->name('admin.updates.run');
});
