<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Safe API representation of a category output.
 *
 * @since 2.0.0
 */
class AiModelCategoryOutputResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'category_id' => $this->category?->uuid,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'prompt' => $this->when($request->is('api/v1/system/*') || $request->is('v1/system/*'), $this->prompt),
            'sort_order' => $this->sort_order,
            'credit_cost' => $this->credit_cost,
            'is_active' => $this->is_active,
        ];
    }
}
