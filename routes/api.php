<?php

use App\Http\Controllers\Api\V1\MonitorController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/', fn (): string => 'API is active');

    Route::apiResource('monitors', MonitorController::class)->only(['index', 'store']);
    Route::get('monitors/{id}/history', [MonitorController::class, 'history']);
});
