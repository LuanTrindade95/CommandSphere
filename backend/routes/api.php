<?php

use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\Auth\AuthenticatedUserController;
use App\Http\Controllers\Api\V1\Auth\DevLoginController;
use App\Http\Controllers\Api\V1\Auth\DiscordAuthController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\FavoriteController;
use App\Http\Controllers\Api\V1\GitHubWebhookController;
use App\Http\Controllers\Api\V1\IngestionController;
use App\Http\Controllers\Api\V1\PluginSyncController;
use App\Http\Controllers\Api\V1\SearchController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/auth/discord/redirect', [DiscordAuthController::class, 'redirect']);
    Route::get('/auth/discord/callback', [DiscordAuthController::class, 'callback']);
    Route::post('/auth/dev-login', DevLoginController::class);
    Route::post('/webhooks/github', GitHubWebhookController::class)->middleware('throttle:webhooks');

    Route::get('/search', SearchController::class)->middleware('throttle:search');

    Route::get('/communities', [CatalogController::class, 'communities']);
    Route::get('/communities/{community:slug}', [CatalogController::class, 'community']);
    Route::get('/plugins', [CatalogController::class, 'plugins']);
    Route::get('/plugins/{slug}', [CatalogController::class, 'plugin']);
    Route::get('/plugins/{slug}/versions/{version}/documents', [CatalogController::class, 'versionDocuments']);
    Route::get('/documents/{document}', [CatalogController::class, 'document']);
    Route::get('/commands/{slug}', [CatalogController::class, 'command']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/auth/me', [AuthenticatedUserController::class, 'show']);
        Route::post('/auth/logout', [AuthenticatedUserController::class, 'destroy']);

        Route::post('/plugins', [CatalogController::class, 'store']);

        Route::get('/favorites', [FavoriteController::class, 'index']);
        Route::post('/favorites', [FavoriteController::class, 'store']);
        Route::delete('/favorites', [FavoriteController::class, 'destroy']);

        Route::post('/commands/{slug}/view', [AnalyticsController::class, 'view']);
        Route::get('/analytics/most-viewed', [AnalyticsController::class, 'mostViewed']);

        Route::post('/plugins/{plugin}/sync', PluginSyncController::class)
            ->middleware('throttle:sync')
            ->middleware('plugin.permission:ingestion.run');

        Route::get('/ingestions', [IngestionController::class, 'index']);
        Route::get('/ingestions/{ingestionRun}', [IngestionController::class, 'show']);
    });
});
