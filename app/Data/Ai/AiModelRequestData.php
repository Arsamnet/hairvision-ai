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
     * Create a new ai model request data instance.
     *
     * @param  array<string, mixed>  $modelConfig
     * @param  array<string, mixed>  $credentials
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $payload
     * @param  Tenant  $clinic  Tenant in the current operation scope.
     * @param  AiModelAccess  $access  The access value.
     * @param  string  $usageCategoryKey  The usage category key value.
     * @param  string|null  $prompt  The prompt value.
     * @param  User|null  $user  Authenticated user performing the operation.
     *
     * @since 1.0.0
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
