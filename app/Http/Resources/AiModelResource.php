<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Safe API representation of an executable AI model.
 *
 * @since 2.0.0
 */
class AiModelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'category_id' => $this->category?->uuid,
            'category_code' => $this->category?->code,
            'type' => $this->type,
            'name' => $this->name,
            'status' => $this->status,
            'priority' => $this->priority,
            'concurrency_limit' => $this->concurrency_limit,
            'settings' => $this->settings ?? [],
            'credentials' => $this->maskedCredentials(),
            'health_status' => $this->health_status,
            'last_assigned_at' => $this->last_assigned_at?->toISOString(),
            'output_configs' => AiModelOutputConfigResource::collection($this->whenLoaded('outputConfigs')),
        ];
    }
}
