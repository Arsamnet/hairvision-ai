<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Safe API representation of an AI model category.
 *
 * @since 2.0.0
 */
class AiModelCategoryResource extends JsonResource
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
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
            'sort_order' => $this->sort_order,
            'icon' => $this->icon,
            'card_image_url' => $this->cardImageUrl(),
            'outputs' => AiModelCategoryOutputResource::collection($this->whenLoaded('outputs')),
        ];
    }
}
