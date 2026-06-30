<?php

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
    ],
];
