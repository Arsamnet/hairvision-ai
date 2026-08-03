<?php

/**
 * Registers public API routes revised for the 2.1.0 release.
 *
 * @since 2.1.0
 */

use App\Http\Controllers\Api\AssetController;
use App\Http\Controllers\Api\BootstrapController;
use App\Http\Controllers\Api\GenerationController;
use App\Http\Controllers\Api\IndependentGenerationController;
use App\Http\Controllers\Api\LandingAnalyticsController;
use App\Http\Controllers\Api\OtpController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\System\AiModelCategoryController;
use App\Http\Controllers\Api\System\AiModelCategoryOutputController;
use App\Http\Controllers\Api\System\AiModelController;
use App\Http\Controllers\Api\System\AiModelTypeController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Middleware\ResolveTenantByHost;
use Illuminate\Support\Facades\Route;

Route::middleware(['api', ResolveTenantByHost::class])->prefix('v1')->group(function () {
    Route::get('/bootstrap', BootstrapController::class);
    Route::post('/landing/events', LandingAnalyticsController::class)->middleware('throttle:120,1');

    Route::post('/auth/otp/request', [OtpController::class, 'request']);
    Route::post('/auth/otp/verify', [OtpController::class, 'verify']);

    Route::post('/uploads', [UploadController::class, 'store']);

    Route::get('/profile', [ProfileController::class, 'show']);
    Route::post('/profile/source-image', [ProfileController::class, 'storeSourceImage']);
    Route::get('/profile/source-images/{sourceImage}/stream', [ProfileController::class, 'streamSourceImage']);
    Route::get('/profile/categories', [ProfileController::class, 'categories']);
    Route::get('/profile/generations', [ProfileController::class, 'generations']);

    Route::post('/generations/independent', [IndependentGenerationController::class, 'store']);
    Route::get('/generations/resumable', [IndependentGenerationController::class, 'resumable']);
    Route::get('/generations/independent/{generation}', [IndependentGenerationController::class, 'show']);
    Route::get('/generations/independent/{generation}/results', [IndependentGenerationController::class, 'results']);
    Route::post('/generations/independent/{generation}/regenerate', [IndependentGenerationController::class, 'regenerate']);

    Route::post('/generations', [GenerationController::class, 'store']);
    Route::get('/generations/{groupUuid}/status', [GenerationController::class, 'status']);
    Route::get('/generations/{groupUuid}/results', [GenerationController::class, 'results']);
    Route::get('/generations/{generation}', [IndependentGenerationController::class, 'show']);
    Route::post('/generations/{generation}/regenerate', [IndependentGenerationController::class, 'regenerate']);

    Route::get('/assets/{uuid}', [AssetController::class, 'show']);
    Route::get('/assets/{uuid}/stream', [AssetController::class, 'stream']);
});

Route::middleware(['api', 'auth:sanctum'])->prefix('v1/system')->group(function () {
    Route::get('/ai-model-types', [AiModelTypeController::class, 'index'])->middleware('permission:ai_model_types.view');
    Route::get('/ai-model-types/{type}', [AiModelTypeController::class, 'show'])->middleware('permission:ai_model_types.view');

    Route::get('/ai-model-categories', [AiModelCategoryController::class, 'index'])->middleware('permission:ai_model_categories.view');
    Route::post('/ai-model-categories', [AiModelCategoryController::class, 'store'])->middleware('permission:ai_model_categories.create');
    Route::get('/ai-model-categories/{category}', [AiModelCategoryController::class, 'show'])->middleware('permission:ai_model_categories.view');
    Route::patch('/ai-model-categories/{category}', [AiModelCategoryController::class, 'update'])->middleware('permission:ai_model_categories.update');
    Route::delete('/ai-model-categories/{category}', [AiModelCategoryController::class, 'destroy'])->middleware('permission:ai_model_categories.delete');

    Route::get('/ai-model-categories/{category}/outputs', [AiModelCategoryOutputController::class, 'index'])->middleware('permission:ai_model_categories.view');
    Route::post('/ai-model-categories/{category}/outputs', [AiModelCategoryOutputController::class, 'store'])->middleware('permission:ai_model_category_outputs.manage');
    Route::get('/ai-model-categories/{category}/outputs/{output}', [AiModelCategoryOutputController::class, 'show'])->middleware('permission:ai_model_categories.view');
    Route::patch('/ai-model-categories/{category}/outputs/{output}', [AiModelCategoryOutputController::class, 'update'])->middleware('permission:ai_model_category_outputs.manage');
    Route::delete('/ai-model-categories/{category}/outputs/{output}', [AiModelCategoryOutputController::class, 'destroy'])->middleware('permission:ai_model_category_outputs.manage');

    Route::get('/ai-models', [AiModelController::class, 'index'])->middleware('permission:ai_models.view');
    Route::post('/ai-models', [AiModelController::class, 'store'])->middleware('permission:ai_models.create');
    Route::get('/ai-models/{model}', [AiModelController::class, 'show'])->middleware('permission:ai_models.view');
    Route::delete('/ai-models/{model}', [AiModelController::class, 'destroy'])->middleware('permission:ai_models.delete');
    Route::post('/ai-models/{model}/activate', [AiModelController::class, 'activate'])->middleware('permission:ai_models.activate');
    Route::post('/ai-models/{model}/pause', [AiModelController::class, 'pause'])->middleware('permission:ai_models.update');
});
