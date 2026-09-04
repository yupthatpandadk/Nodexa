<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Admin\AddonController;

Route::group(['prefix' => 'addons'], function () {
    Route::get('/', [AddonController::class, 'index'])->name('admin.addons');
    Route::post('/{slug}/install', [AddonController::class, 'install'])
        ->where('slug', '[a-z0-9][a-z0-9_-]{1,79}')
        ->name('admin.addons.install');
    Route::post('/{slug}/toggle', [AddonController::class, 'toggle'])
        ->where('slug', '[a-z0-9][a-z0-9_-]{1,79}')
        ->name('admin.addons.toggle');
    Route::delete('/{slug}', [AddonController::class, 'uninstall'])
        ->where('slug', '[a-z0-9][a-z0-9_-]{1,79}')
        ->name('admin.addons.uninstall');
});
