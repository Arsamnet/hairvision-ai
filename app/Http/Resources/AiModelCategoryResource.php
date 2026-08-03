<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Safe API representation of an AI model category.
 *
 * @since 2.0.0
 * @since 2.1.0 Behavior updated for the 2.1.0 release.
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
     * @since 2.1.0 Behavior updated for the 2.1.0 release.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'upload_guide_html' => $this->upload_guide_html,
            'status' => $this->status,
            'sort_order' => $this->sort_order,
            'icon' => $this->icon,
            'card_image_url' => $this->cardImageUrl(),
            'outputs' => AiModelCategoryOutputResource::collection($this->whenLoaded('outputs')),
        ];
    }
}
