<?php

use App\Http\Controllers\Api\V1\Auth\AuthenticatedUserController;
use App\Http\Controllers\Api\V1\Auth\DevLoginController;
use App\Http\Controllers\Api\V1\Auth\DiscordAuthController;
use App\Http\Controllers\Api\V1\IngestionController;
use App\Http\Controllers\Api\V1\PluginSyncController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/auth/discord/redirect', [DiscordAuthController::class, 'redirect']);
    Route::get('/auth/discord/callback', [DiscordAuthController::class, 'callback']);
    Route::post('/auth/dev-login', DevLoginController::class);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/auth/me', [AuthenticatedUserController::class, 'show']);
        Route::post('/auth/logout', [AuthenticatedUserController::class, 'destroy']);

        Route::post('/plugins/{plugin}/sync', PluginSyncController::class)
            ->middleware('plugin.permission:ingestion.run');

        Route::get('/ingestions', [IngestionController::class, 'index']);
        Route::get('/ingestions/{ingestionRun}', [IngestionController::class, 'show']);
    });
});
