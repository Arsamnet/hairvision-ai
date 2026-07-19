<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a v2 profile source image upload.
 *
 * @since 2.0.0
 */
class StoreSourceImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'max:5120'],
            'consent' => ['accepted'],
        ];
    }
}
