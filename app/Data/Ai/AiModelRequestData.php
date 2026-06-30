<?php

namespace App\Data\Ai;

use App\Models\AiModelAccess;
use App\Models\Tenant;
use App\Models\User;

/**
 * Normalized request payload passed to AI model providers.
 *
 * @since 1.0.0
 */
final class AiModelRequestData
{
    /**
     * @param  array<string, mixed>  $modelConfig
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly Tenant $clinic,
        public readonly AiModelAccess $access,
        public readonly string $usageCategoryKey,
        public readonly array $modelConfig,
        public readonly array $credentials,
        public readonly array $settings,
        public readonly ?string $prompt,
        public readonly array $payload = [],
        public readonly ?User $user = null,
    ) {}
}
