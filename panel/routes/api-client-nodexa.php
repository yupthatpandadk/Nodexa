<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Api\Client;
use Pterodactyl\Http\Middleware\Activity\ServerSubject;
use Pterodactyl\Http\Middleware\Api\Client\Server\ResourceBelongsToServer;
use Pterodactyl\Http\Middleware\Api\Client\Server\AuthenticateServerAccess;

$serverMiddleware = [
    ServerSubject::class,
    AuthenticateServerAccess::class,
    ResourceBelongsToServer::class,
];

Route::group([
    'prefix' => '/servers/{server}/plugins',
    'middleware' => $serverMiddleware,
], function () {
    Route::get('/search', [Client\Servers\MinecraftPluginController::class, 'search']);
    Route::get('/installed', [Client\Servers\MinecraftPluginController::class, 'installed']);
    Route::post('/install', [Client\Servers\MinecraftPluginController::class, 'install']);
    Route::delete('/{projectId}', [Client\Servers\MinecraftPluginController::class, 'uninstall'])
        ->where('projectId', '[A-Za-z0-9_-]+');
});

Route::group([
    'prefix' => '/servers/{server}/mods',
    'middleware' => $serverMiddleware,
], function () {
    Route::get('/search', [Client\Servers\MinecraftModController::class, 'search']);
    Route::get('/installed', [Client\Servers\MinecraftModController::class, 'installed']);
    Route::post('/install', [Client\Servers\MinecraftModController::class, 'install']);
    Route::delete('/{filename}', [Client\Servers\MinecraftModController::class, 'uninstall'])
        ->where('filename', '[A-Za-z0-9%._+()\\-]+');
});
