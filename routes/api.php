<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use OTGH\AccessControl\Core\Http\Controllers\Api\AuthTokenController;
use OTGH\AccessControl\Core\Http\Controllers\Api\ReaderControlController;

Route::post('/auth/token', [AuthTokenController::class, 'store'])->middleware('throttle:api');

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::delete('/auth/token', [AuthTokenController::class, 'destroy']);

    Route::prefix('readers')->group(function (): void {
        Route::get('/', [ReaderControlController::class, 'index']);
        Route::get('/{accessReader:identifier}', [ReaderControlController::class, 'show']);
        Route::post('/{accessReader:identifier}/lock', [ReaderControlController::class, 'lock']);
        Route::post('/{accessReader:identifier}/unlock', [ReaderControlController::class, 'unlock']);
        Route::put('/{accessReader:identifier}/autolock', [ReaderControlController::class, 'setAutolock']);
    });
});
