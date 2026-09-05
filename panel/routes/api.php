<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ClientApiKeyController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\ServerRuntimeController;
use App\Http\Controllers\ServerDatabaseController;
use App\Http\Controllers\ServerSubuserController;
use App\Http\Controllers\ServerAllocationController;
use App\Http\Controllers\ServerSettingsController;
use App\Http\Controllers\AdminAllocationController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\NodeController;
use App\Http\Controllers\DatabaseHostController;
use App\Http\Controllers\AdminServerStartupController;
use App\Http\Controllers\SystemIssueController;
use App\Http\Controllers\AdminUpdateController;
use App\Http\Controllers\AdminPanelSettingsController;
use App\Http\Controllers\StorefrontSiteController;
use App\Http\Controllers\Api\ServerSftpController;

Route::post('/login',[AuthController::class,'login'])->middleware('throttle:10,1');

Route::middleware('auth:sanctum')->group(function(){
Route::post('/logout',[AuthController::class,'logout']);
Route::get('/me',[UserController::class,'me']);
Route::get('/client-api',[ClientApiKeyController::class,'info']);
Route::get('/client-api-keys',[ClientApiKeyController::class,'index']);
Route::post('/client-api-keys',[ClientApiKeyController::class,'store']);
Route::delete('/client-api-keys/{token}',[ClientApiKeyController::class,'destroy']);
Route::get('/admin/users',[UserController::class,'adminIndex']);
Route::get('/servers',[ServerController::class,'index']);Route::post('/servers',[ServerController::class,'store']);Route::post('/servers/{server}/retry-install',[ServerController::class,'retryInstall']);Route::post('/servers/{server}/reinstall',[ServerController::class,'reinstall']);Route::get('/servers/{server}',[ServerController::class,'show']);Route::put('/servers/{server}',[ServerSettingsController::class,'update']);Route::post('/servers/{server}/power',[ServerController::class,'power']);Route::post('/servers/{server}/command',[ServerController::class,'command']);Route::get('/servers/{server}/stats',[ServerRuntimeController::class,'stats']);Route::get('/servers/{server}/logs',[ServerRuntimeController::class,'logs']);Route::get('/servers/{server}/logs/stream',[ServerRuntimeController::class,'streamLogs']);
Route::get('/servers/{server}/sftp',[ServerSftpController::class,'show']);Route::post('/servers/{server}/sftp/sync',[ServerSftpController::class,'sync']);
Route::get('/servers/{server}/files',[ServerRuntimeController::class,'files']);Route::get('/servers/{server}/file',[ServerRuntimeController::class,'readFile']);Route::put('/servers/{server}/file',[ServerRuntimeController::class,'writeFile']);Route::post('/servers/{server}/file/rename',[ServerRuntimeController::class,'renameFile']);Route::post('/servers/{server}/directory',[ServerRuntimeController::class,'mkdir']);Route::post('/servers/{server}/upload',[ServerRuntimeController::class,'upload']);Route::get('/servers/{server}/download',[ServerRuntimeController::class,'download']);Route::post('/servers/{server}/archive',[ServerRuntimeController::class,'archive']);Route::post('/servers/{server}/extract',[ServerRuntimeController::class,'extract']);Route::delete('/servers/{server}/file',[ServerRuntimeController::class,'deleteFile']);
Route::get('/servers/{server}/backups',[ServerRuntimeController::class,'backups']);Route::post('/servers/{server}/backups',[ServerRuntimeController::class,'backup']);Route::get('/servers/{server}/backups/{name}/download',[ServerRuntimeController::class,'downloadBackup']);Route::post('/servers/{server}/backups/{name}/restore',[ServerRuntimeController::class,'restoreBackup']);Route::delete('/servers/{server}/backups/{name}',[ServerRuntimeController::class,'deleteBackup']);
Route::get('/servers/{server}/allocations',[ServerAllocationController::class,'index']);Route::post('/servers/{server}/allocations',[ServerAllocationController::class,'store']);Route::put('/servers/{server}/allocations/{allocation}',[ServerAllocationController::class,'update']);Route::post('/servers/{server}/allocations/{allocation}/primary',[ServerAllocationController::class,'primary']);Route::delete('/servers/{server}/allocations/{allocation}',[ServerAllocationController::class,'destroy']);
Route::get('/servers/{server}/databases',[ServerDatabaseController::class,'index']);Route::post('/servers/{server}/databases',[ServerDatabaseController::class,'store']);Route::get('/servers/{server}/databases/{database}/credentials',[ServerDatabaseController::class,'credentials']);Route::post('/servers/{server}/databases/{database}/rotate',[ServerDatabaseController::class,'rotateCredentials']);Route::post('/servers/{server}/databases/{database}/open',[ServerDatabaseController::class,'openPhpMyAdmin']);Route::delete('/servers/{server}/databases/{database}',[ServerDatabaseController::class,'destroy']);
Route::get('/servers/{server}/schedules',[ScheduleController::class,'index']);Route::post('/servers/{server}/schedules',[ScheduleController::class,'store']);Route::put('/servers/{server}/schedules/{schedule}',[ScheduleController::class,'update']);Route::post('/servers/{server}/schedules/{schedule}/run',[ScheduleController::class,'run']);Route::delete('/servers/{server}/schedules/{schedule}',[ScheduleController::class,'destroy']);
Route::get('/servers/{server}/users',[ServerSubuserController::class,'index']);Route::post('/servers/{server}/users',[ServerSubuserController::class,'store']);Route::put('/servers/{server}/users/{subuser}',[ServerSubuserController::class,'update']);Route::delete('/servers/{server}/users/{subuser}',[ServerSubuserController::class,'destroy']);
Route::get('/admin/servers/{server}/startup',[AdminServerStartupController::class,'show']);Route::put('/admin/servers/{server}/startup',[AdminServerStartupController::class,'update']);
Route::get('/admin/settings',[AdminPanelSettingsController::class,'show']);Route::put('/admin/settings',[AdminPanelSettingsController::class,'update']);
Route::get('/nodes',[NodeController::class,'index']);Route::post('/nodes',[NodeController::class,'store']);Route::get('/nodes/{node}',[NodeController::class,'show']);Route::get('/nodes/{node}/config',[NodeController::class,'config']);Route::post('/nodes/{node}/rotate-token',[NodeController::class,'rotateToken']);Route::get('/nodes/{node}/allocations',[AdminAllocationController::class,'index']);Route::post('/nodes/{node}/allocations',[AdminAllocationController::class,'store']);Route::post('/nodes/{node}/allocations/range',[AdminAllocationController::class,'range']);Route::delete('/nodes/{node}/allocations/{allocation}',[AdminAllocationController::class,'destroy']);
Route::get('/database-hosts',[DatabaseHostController::class,'index']);Route::post('/database-hosts',[DatabaseHostController::class,'store']);Route::delete('/database-hosts/{databaseHost}',[DatabaseHostController::class,'destroy']);

Route::get('/system/issues',[SystemIssueController::class,'index']);
Route::post('/system/issues/scan-all',[SystemIssueController::class,'scanAll']);
Route::post('/system/issues/scan-system',[SystemIssueController::class,'scanSystem']);
Route::post('/system/issues/scan-nodes',[SystemIssueController::class,'scanNodes']);
Route::post('/system/issues/{issue}/resolve',[SystemIssueController::class,'resolve']);
Route::post('/system/issues/{issue}/reopen',[SystemIssueController::class,'reopen']);

Route::get('/system-errors',[SystemIssueController::class,'index']);
Route::post('/system-errors/scan-all',[SystemIssueController::class,'scanAll']);
Route::post('/system-errors/scan-system',[SystemIssueController::class,'scanSystem']);
Route::post('/system-errors/scan-nodes',[SystemIssueController::class,'scanNodes']);
Route::post('/system-errors/{issue}/resolve',[SystemIssueController::class,'resolve']);
Route::post('/system-errors/{issue}/reopen',[SystemIssueController::class,'reopen']);

Route::get('/admin/update/check',[AdminUpdateController::class,'check']);Route::post('/admin/update/start',[AdminUpdateController::class,'start']);Route::post('/admin/update/run',[AdminUpdateController::class,'start']);Route::get('/admin/update/status',[AdminUpdateController::class,'status']);
Route::get('/storefront-sites',[StorefrontSiteController::class,'index']);Route::post('/storefront-sites',[StorefrontSiteController::class,'store']);Route::put('/storefront-sites/{site}',[StorefrontSiteController::class,'update']);Route::delete('/storefront-sites/{site}',[StorefrontSiteController::class,'destroy']);Route::post('/storefront-sites/{site}/products',[StorefrontSiteController::class,'attachProduct']);Route::delete('/storefront-sites/{site}/products/{product}',[StorefrontSiteController::class,'detachProduct']);

// Dedicated Nodexa Client API. These endpoints only accept tokens created as
// Client API keys (nxa_...), not regular panel-login sessions.
Route::prefix('client')->middleware('client.api')->group(function(){
    Route::get('/', [ClientApiKeyController::class, 'info']);
    Route::get('/account', [UserController::class, 'me']);
    Route::get('/servers', [ServerController::class, 'index']);
    Route::get('/servers/{server}', [ServerController::class, 'show']);
    Route::get('/servers/{server}/resources', [ServerRuntimeController::class, 'stats']);
    Route::get('/servers/{server}/logs', [ServerRuntimeController::class, 'logs']);
    Route::post('/servers/{server}/power', [ServerController::class, 'power']);
    Route::post('/servers/{server}/command', [ServerController::class, 'command']);

    Route::get('/servers/{server}/files', [ServerRuntimeController::class, 'files']);
    Route::get('/servers/{server}/file', [ServerRuntimeController::class, 'readFile']);
    Route::put('/servers/{server}/file', [ServerRuntimeController::class, 'writeFile']);
    Route::delete('/servers/{server}/file', [ServerRuntimeController::class, 'deleteFile']);

    Route::get('/servers/{server}/backups', [ServerRuntimeController::class, 'backups']);
    Route::post('/servers/{server}/backups', [ServerRuntimeController::class, 'backup']);
    Route::post('/servers/{server}/backups/{name}/restore', [ServerRuntimeController::class, 'restoreBackup']);
    Route::delete('/servers/{server}/backups/{name}', [ServerRuntimeController::class, 'deleteBackup']);

    Route::get('/servers/{server}/databases', [ServerDatabaseController::class, 'index']);
    Route::post('/servers/{server}/databases', [ServerDatabaseController::class, 'store']);
    Route::get('/servers/{server}/databases/{database}/credentials', [ServerDatabaseController::class, 'credentials']);
    Route::post('/servers/{server}/databases/{database}/rotate', [ServerDatabaseController::class, 'rotateCredentials']);
    Route::delete('/servers/{server}/databases/{database}', [ServerDatabaseController::class, 'destroy']);

    Route::get('/servers/{server}/schedules', [ScheduleController::class, 'index']);
    Route::post('/servers/{server}/schedules/{schedule}/run', [ScheduleController::class, 'run']);

    Route::get('/servers/{server}/users', [ServerSubuserController::class, 'index']);
    Route::get('/servers/{server}/allocations', [ServerAllocationController::class, 'index']);
});
});
