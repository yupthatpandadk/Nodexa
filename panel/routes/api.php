<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\ServerRuntimeController;
use App\Http\Controllers\ServerDatabaseController;
use App\Http\Controllers\NodeController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/servers', [ServerController::class, 'index']);
    Route::post('/servers', [ServerController::class, 'store']);
    Route::get('/servers/{server}', [ServerController::class, 'show']);
    Route::post('/servers/{server}/power', [ServerController::class, 'power']);
    Route::post('/servers/{server}/command', [ServerController::class, 'command']);
    Route::get('/servers/{server}/stats', [ServerRuntimeController::class, 'stats']);
    Route::get('/servers/{server}/logs', [ServerRuntimeController::class, 'logs']);
    Route::get('/servers/{server}/files', [ServerRuntimeController::class, 'files']);
    Route::get('/servers/{server}/file', [ServerRuntimeController::class, 'readFile']);
    Route::put('/servers/{server}/file', [ServerRuntimeController::class, 'writeFile']);
    Route::post('/servers/{server}/directory', [ServerRuntimeController::class, 'mkdir']);
    Route::delete('/servers/{server}/file', [ServerRuntimeController::class, 'deleteFile']);
    Route::post('/servers/{server}/backups', [ServerRuntimeController::class, 'backup']);

    Route::get('/servers/{server}/databases', [ServerDatabaseController::class, 'index']);
    Route::post('/servers/{server}/databases', [ServerDatabaseController::class, 'store']);
    Route::get('/servers/{server}/databases/{database}/credentials', [ServerDatabaseController::class, 'credentials']);
    Route::delete('/servers/{server}/databases/{database}', [ServerDatabaseController::class, 'destroy']);

    Route::get('/nodes', [NodeController::class, 'index']);
    Route::post('/nodes', [NodeController::class, 'store']);
});
