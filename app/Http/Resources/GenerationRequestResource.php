<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public-safe representation of an independent generation request.
 *
 * @since 2.0.0
 */
class GenerationRequestResource extends JsonResource
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
            'source_image_id' => $this->sourceImage?->uuid,
            'category' => [
                'id' => $this->category?->uuid,
                'code' => $this->category?->code,
                'name' => $this->category?->name,
            ],
            'output' => [
                'id' => $this->output?->uuid,
                'code' => $this->output?->code,
                'name' => $this->output?->name,
                'credit_cost' => $this->output?->credit_cost,
            ],
            'status' => $this->status,
            'asset_url' => $this->currentAsset ? '/api/v1/assets/'.$this->currentAsset->uuid.'/stream' : null,
            'error' => $this->safe_error_message,
            'attempts' => $this->whenLoaded('attempts', fn () => $this->attempts->map(fn ($attempt): array => [
                'id' => $attempt->uuid,
                'status' => $attempt->status,
                'attempt_number' => $attempt->attempt_number,
            ])->values()),
            'requested_at' => $this->requested_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'failed_at' => $this->failed_at?->toISOString(),
        ];
    }
}
