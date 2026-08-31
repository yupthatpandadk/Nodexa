<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\ServerRuntimeController;
use App\Http\Controllers\ServerDatabaseController;
use App\Http\Controllers\ServerSubuserController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\NodeController;
use App\Http\Controllers\DatabaseHostController;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [UserController::class, 'me']);

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
    Route::post('/servers/{server}/databases/{database}/open', [ServerDatabaseController::class, 'openPhpMyAdmin']);
    Route::delete('/servers/{server}/databases/{database}', [ServerDatabaseController::class, 'destroy']);

    Route::get('/servers/{server}/schedules', [ScheduleController::class, 'index']);
    Route::post('/servers/{server}/schedules', [ScheduleController::class, 'store']);
    Route::put('/servers/{server}/schedules/{schedule}', [ScheduleController::class, 'update']);
    Route::post('/servers/{server}/schedules/{schedule}/run', [ScheduleController::class, 'run']);
    Route::delete('/servers/{server}/schedules/{schedule}', [ScheduleController::class, 'destroy']);

    Route::get('/servers/{server}/users', [ServerSubuserController::class, 'index']);
    Route::post('/servers/{server}/users', [ServerSubuserController::class, 'store']);
    Route::put('/servers/{server}/users/{subuser}', [ServerSubuserController::class, 'update']);
    Route::delete('/servers/{server}/users/{subuser}', [ServerSubuserController::class, 'destroy']);

    Route::get('/nodes', [NodeController::class, 'index']);
    Route::post('/nodes', [NodeController::class, 'store']);
    Route::get('/nodes/{node}/configuration', [NodeController::class, 'configuration']);
    Route::post('/nodes/{node}/rotate-token', [NodeController::class, 'rotateToken']);

    Route::get('/database-hosts', [DatabaseHostController::class, 'index']);
    Route::post('/database-hosts', [DatabaseHostController::class, 'store']);
    Route::put('/database-hosts/{databaseHost}', [DatabaseHostController::class, 'update']);
    Route::post('/database-hosts/{databaseHost}/test', [DatabaseHostController::class, 'test']);
    Route::get('/database-hosts/{databaseHost}/credentials', [DatabaseHostController::class, 'credentials']);
    Route::delete('/database-hosts/{databaseHost}', [DatabaseHostController::class, 'destroy']);
});
