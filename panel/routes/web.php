<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PhpMyAdminGatewayController;

Route::get('/database-gateway/{token}', PhpMyAdminGatewayController::class)
    ->where('token', '[A-Za-z0-9]{64}')
    ->name('database.gateway');

Route::view('/admin/database-hosts', 'admin.database-hosts')->name('admin.database-hosts');
Route::view('/{any?}', 'app')->where('any', '.*');
