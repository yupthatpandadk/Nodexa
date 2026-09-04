<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Api\Client;
use Pterodactyl\Http\Middleware\Activity\ServerSubject;
use Pterodactyl\Http\Middleware\Api\Client\Server\ResourceBelongsToServer;
use Pterodactyl\Http\Middleware\Api\Client\Server\AuthenticateServerAccess;

Route::group([
    'prefix' => '/servers/{server}/plugins',
    'middleware' => [
        ServerSubject::class,
        AuthenticateServerAccess::class,
        ResourceBelongsToServer::class,
    ],
], function () {
    Route::get('/search', [Client\Servers\MinecraftPluginController::class, 'search']);
    Route::get('/installed', [Client\Servers\MinecraftPluginController::class, 'installed']);
    Route::post('/install', [Client\Servers\MinecraftPluginController::class, 'install']);
    Route::delete('/{projectId}', [Client\Servers\MinecraftPluginController::class, 'uninstall'])
        ->where('projectId', '[A-Za-z0-9_-]+');
});
