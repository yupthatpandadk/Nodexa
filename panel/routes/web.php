<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PhpMyAdminGatewayController;

Route::get('/database-gateway/{token}', PhpMyAdminGatewayController::class)
    ->where('token', '[A-Za-z0-9]{64}')
    ->name('database.gateway');

Route::view('/admin/database-hosts', 'admin.database-hosts')->name('admin.database-hosts');
Route::view('/admin/servers/{server}/startup', 'admin.server-startup')->name('admin.server-startup');
Route::view('/admin/errors', 'admin-errors')->name('admin.errors');
Route::view('/{any?}', 'app')->where('any', '.*');
