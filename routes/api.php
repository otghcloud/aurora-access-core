<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use OTGH\AccessControl\Core\Http\Controllers\Api\AreaStatusController;
use OTGH\AccessControl\Core\Http\Controllers\Api\AuthTokenController;
use OTGH\AccessControl\Core\Http\Controllers\Api\HAStatusPollerController;
use OTGH\AccessControl\Core\Http\Controllers\Api\HAWebhookController;
use OTGH\AccessControl\Core\Http\Controllers\Api\LightControlController;
use OTGH\AccessControl\Core\Http\Controllers\Api\LockControlController;
use OTGH\AccessControl\Core\Http\Controllers\Api\ReaderControlController;
use OTGH\AccessControl\Core\Http\Controllers\Api\SensorStateController;
use OTGH\AccessControl\Core\Http\Controllers\Api\StatusController;

Route::post('/auth/token', [AuthTokenController::class, 'store'])->middleware('throttle:api');

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::delete('/auth/token', [AuthTokenController::class, 'destroy']);

    // General status endpoint
    Route::get('/status', [StatusController::class, 'index']);

    // Area status endpoints
    Route::prefix('areas')->group(function (): void {
        Route::get('/{area}', [AreaStatusController::class, 'show']);
        Route::put('/{area}/autolock', [AreaStatusController::class, 'updateAutolock']);
    });

    // Lock control endpoints
    Route::prefix('locks')->group(function (): void {
        Route::get('/{lock}', [LockControlController::class, 'show']);
        Route::post('/{lock}/lock', [LockControlController::class, 'lock']);
        Route::post('/{lock}/unlock', [LockControlController::class, 'unlock']);
        Route::post('/{lock}/toggle', [LockControlController::class, 'toggle']);
        Route::put('/{lock}/autolock', [LockControlController::class, 'updateAutolock']);
    });

    // Sensor state endpoints
    Route::prefix('sensors')->group(function (): void {
        Route::get('/', [SensorStateController::class, 'index']);
        Route::get('/{sensor}', [SensorStateController::class, 'show']);
    });

    // Light control endpoints
    Route::prefix('lights')->group(function (): void {
        Route::get('/{light}', [LightControlController::class, 'show']);
        Route::post('/{light}/on', [LightControlController::class, 'on']);
        Route::post('/{light}/off', [LightControlController::class, 'off']);
        Route::post('/{light}/toggle', [LightControlController::class, 'toggle']);
        Route::put('/{light}/brightness', [LightControlController::class, 'setBrightness']);
        Route::put('/{light}/color', [LightControlController::class, 'setColor']);
    });

    // Home Assistant integration endpoints
    Route::prefix('ha')->group(function (): void {
        // Polling endpoints (GET requests for HA to fetch status)
        Route::get('/status', [HAStatusPollerController::class, 'getStatus']);
        Route::get('/status/{area}', [HAStatusPollerController::class, 'getAreaStatus']);
        Route::get('/discovery', [HAStatusPollerController::class, 'getDiscoveryManifests']);
        Route::get('/discovery/locks/{lock}', [HAStatusPollerController::class, 'getLockDiscovery']);
        Route::get('/discovery/sensors/{sensor}', [HAStatusPollerController::class, 'getSensorDiscovery']);
        Route::get('/discovery/lights/{light}', [HAStatusPollerController::class, 'getLightDiscovery']);

        // Webhook endpoints (POST requests from HA when user controls devices)
        Route::post('/webhook', [HAWebhookController::class, 'handleWebhook']);
    });

    // Legacy readers endpoints (kept for backward compatibility)
    Route::prefix('readers')->group(function (): void {
        Route::get('/', [ReaderControlController::class, 'index']);
        Route::get('/{accessReader:identifier}', [ReaderControlController::class, 'show']);
        Route::post('/{accessReader:identifier}/lock', [ReaderControlController::class, 'lock']);
        Route::post('/{accessReader:identifier}/unlock', [ReaderControlController::class, 'unlock']);
        Route::put('/{accessReader:identifier}/autolock', [ReaderControlController::class, 'setAutolock']);
    });
});
