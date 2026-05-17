<?php

use App\Http\Controllers\Api\V1\MonitorController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/', fn (): string => 'API is active');

    Route::middleware('throttle:60,1')->group(function (): void {
        Route::apiResource('monitors', MonitorController::class)->only(['index']);
        Route::get('monitors/{id}/history', [MonitorController::class, 'history']);
    });

    Route::middleware('throttle:10,1')->group(function (): void {
        Route::apiResource('monitors', MonitorController::class)->only(['store']);
    });
});
