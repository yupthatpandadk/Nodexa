<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PhpMyAdminGatewayController;
use App\Http\Controllers\WebEntryController;

Route::get('/database-gateway/{token}', PhpMyAdminGatewayController::class)
    ->where('token', '[A-Za-z0-9]{64}')
    ->name('database.gateway');

Route::view('/admin/servers/create', 'admin-server-create')->name('admin.servers.create');
Route::view('/admin/nodes/setup', 'admin.node-setup')->name('admin.nodes.setup');
Route::view('/admin/database-hosts', 'admin.database-hosts')->name('admin.database-hosts');
Route::view('/admin/storefronts', 'admin-storefronts')->name('admin.storefronts');
Route::view('/admin/servers/{server}/startup', 'admin.server-startup')->name('admin.server-startup');
Route::view('/admin/errors', 'admin-errors')->name('admin.errors');
Route::view('/admin/update', 'admin-update')->name('admin.update');

// Multisite entry point. The request hostname selects the storefront from the
// database; the configured panel hostname continues to render the React panel.
Route::get('/{any?}', WebEntryController::class)->where('any', '.*');
