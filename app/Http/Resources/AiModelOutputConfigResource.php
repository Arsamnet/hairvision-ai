<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Safe API representation of a model output configuration.
 *
 * @since 2.0.0
 */
class AiModelOutputConfigResource extends JsonResource
{
    /**
     * Transform the resource into an API response array.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return array<string, mixed>
     *
     * @since 2.0.0
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'output_id' => $this->output?->uuid,
            'output_code' => $this->output?->code,
            'is_active' => $this->is_active,
            'configuration' => $this->configuration ?? [],
        ];
    }
}
