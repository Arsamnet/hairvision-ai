<?php

namespace App\Contracts\Ai;

use App\Data\Ai\AiModelRequestData;
use App\Data\Ai\AiModelResponseData;

/**
 * Common contract for configured AI model providers.
 *
 * @since 1.0.0
 */
interface AiModelProviderInterface
{
    /**
     * Execute a normalized AI model request and return a safe response object.
     */
    public function run(AiModelRequestData $request): AiModelResponseData;
}
