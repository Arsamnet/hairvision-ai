<?php

namespace App\Http\Controllers\Api\System;

use App\Http\Controllers\Controller;
use App\Services\AI\AiModelTypeRegistry;
use Illuminate\Http\JsonResponse;

/**
 * Read-only system API for configured AI model type schemas.
 *
 * @since 2.0.0
 */
class AiModelTypeController extends Controller
{
    public function index(AiModelTypeRegistry $registry): JsonResponse
    {
        return response()->json([
            'data' => $registry->publicDefinitions(),
        ]);
    }

    public function show(string $type, AiModelTypeRegistry $registry): JsonResponse
    {
        return response()->json([
            'data' => $registry->publicDefinition($type),
        ]);
    }
}
