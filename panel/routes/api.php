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
use App\Http\Controllers\AdminServerStartupController;
use App\Http\Controllers\SystemIssueController;
use App\Http\Controllers\AdminUpdateController;
use App\Http\Controllers\StorefrontSiteController;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [UserController::class, 'me']);
    Route::get('/admin/users', [UserController::class, 'adminIndex']);

    Route::get('/servers', [ServerController::class, 'index']);
    Route::post('/servers', [ServerController::class, 'store']);
    Route::post('/servers/{server}/retry-install', [ServerController::class, 'retryInstall']);
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

    Route::get('/admin/servers/{server}/startup', [AdminServerStartupController::class, 'show']);
    Route::put('/admin/servers/{server}/startup', [AdminServerStartupController::class, 'update']);

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

    Route::get('/admin/storefronts', [StorefrontSiteController::class, 'index']);
    Route::post('/admin/storefronts', [StorefrontSiteController::class, 'store']);
    Route::put('/admin/storefronts/{site}', [StorefrontSiteController::class, 'update']);
    Route::delete('/admin/storefronts/{site}', [StorefrontSiteController::class, 'destroy']);
    Route::post('/admin/storefronts/{site}/products', [StorefrontSiteController::class, 'storeProduct']);
    Route::put('/admin/storefronts/{site}/products/{product}', [StorefrontSiteController::class, 'updateProduct']);
    Route::delete('/admin/storefronts/{site}/products/{product}', [StorefrontSiteController::class, 'destroyProduct']);

    Route::get('/system-errors', [SystemIssueController::class, 'index']);
    Route::post('/system-errors/scan-all', [SystemIssueController::class, 'scanAll']);
    Route::post('/system-errors/scan-system', [SystemIssueController::class, 'scanSystem']);
    Route::post('/system-errors/scan-nodes', [SystemIssueController::class, 'scanNodes']);
    Route::post('/system-errors/client', [SystemIssueController::class, 'clientError']);
    Route::post('/system-errors/{issue}/resolve', [SystemIssueController::class, 'resolve']);

    Route::get('/admin/update/check', [AdminUpdateController::class, 'check']);
    Route::get('/admin/update/status', [AdminUpdateController::class, 'status']);
    Route::post('/admin/update/start', [AdminUpdateController::class, 'start']);
});
