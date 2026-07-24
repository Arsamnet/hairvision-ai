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
    /**
     * Display the ai model type listing.
     *
     * @param  AiModelTypeRegistry  $registry  The registry value.
     * @return JsonResponse JSON response payload.
     *
     * @since 2.0.0
     */
    public function index(AiModelTypeRegistry $registry): JsonResponse
    {
        return response()->json([
            'data' => $registry->publicDefinitions(),
        ]);
    }

    /**
     * Display the requested ai model type resource.
     *
     * @param  string  $type  The type value.
     * @param  AiModelTypeRegistry  $registry  The registry value.
     * @return JsonResponse JSON response payload.
     *
     * @since 2.0.0
     */
    public function show(string $type, AiModelTypeRegistry $registry): JsonResponse
    {
        return response()->json([
            'data' => $registry->publicDefinition($type),
        ]);
    }
}
