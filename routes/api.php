<?php

use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\BootstrapController;
use App\Http\Controllers\Api\GenerationController;
use App\Http\Controllers\Api\OtpController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Middleware\ResolveTenantByHost;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', ResolveTenantByHost::class])->prefix('v1')->group(function () {
    Route::get('/bootstrap', BootstrapController::class);

    Route::post('/auth/otp/request', [OtpController::class, 'request']);
    Route::post('/auth/otp/verify', [OtpController::class, 'verify']);

    Route::post('/uploads', [UploadController::class, 'store']);

    Route::post('/generations', [GenerationController::class, 'store']);
    Route::get('/generations/{groupUuid}/status', [GenerationController::class, 'status']);
    Route::get('/generations/{groupUuid}/results', [GenerationController::class, 'results']);

    Route::get('/assets/{uuid}', [AssetController::class, 'show']);
});
