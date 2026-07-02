<?php

use App\Services\AI\Providers\GapGptImageEditProvider;
use App\Services\AI\Providers\MockupAiProvider;

return [
    'usage_categories' => [
        'hair_transplant' => [
            'key' => 'hair_transplant',
            'label_key' => 'ai_models.usage_categories.hair_transplant',
            'description_key' => 'ai_models.usage_category_descriptions.hair_transplant',
        ],
        'eyebrow_transplant' => [
            'key' => 'eyebrow_transplant',
            'label_key' => 'ai_models.usage_categories.eyebrow_transplant',
            'description_key' => 'ai_models.usage_category_descriptions.eyebrow_transplant',
        ],
    ],

    'models' => [
        'sample_image' => [
            'key' => 'sample_image',
            'display_name_key' => 'ai_models.models.sample_image.name',
            'provider' => 'HairVision',
            'vendor' => 'HairVision',
            'description_key' => 'ai_models.models.sample_image.description',
            'driver' => MockupAiProvider::class,
            'required_credentials' => [],
            'optional_credentials' => ['api_key'],
            'supported_usage_categories' => ['hair_transplant', 'eyebrow_transplant'],
            'default_timeout' => 5,
            'endpoint' => null,
            'is_system' => true,
            'is_demo' => true,
            'is_protected' => true,
            'supports_prompt_customization' => true,
            'supports_credit_based_usage' => true,
        ],
        'gapgpt' => [
            'key' => 'gapgpt',
            'display_name_key' => 'ai_models.models.gapgpt.name',
            'provider' => 'GapGPT',
            'vendor' => 'GapGPT',
            'description_key' => 'ai_models.models.gapgpt.description',
            'driver' => GapGptImageEditProvider::class,
            'required_credentials' => ['api_key'],
            'optional_credentials' => [],
            'configurable_settings' => [
                'endpoint_url',
                'model',
                'image_field',
                'size',
                'quality',
                'output_format',
                'timeout_seconds',
                'connect_timeout_seconds',
                'retry_attempts',
                'retry_sleep_ms',
            ],
            'supported_usage_categories' => ['hair_transplant'],
            'default_timeout' => (int) env('GAPGPT_IMAGE_EDIT_TIMEOUT_SECONDS', 90),
            'default_connect_timeout' => (int) env('GAPGPT_IMAGE_EDIT_CONNECT_TIMEOUT_SECONDS', 10),
            'endpoint' => env('GAPGPT_IMAGE_EDIT_ENDPOINT', 'https://api.gapgpt.app/v1/images/edits'),
            'default_model' => env('GAPGPT_IMAGE_EDIT_MODEL', 'gpt-image-2'),
            'image_field' => env('GAPGPT_IMAGE_EDIT_IMAGE_FIELD', 'image'),
            'is_system' => true,
            'is_demo' => false,
            'is_protected' => false,
            'supports_prompt_customization' => true,
            'supports_credit_based_usage' => true,
        ],
    ],
];
