<?php

use App\Services\AI\InputImageProcessingSettings;
use App\Services\AI\Providers\GapGptImageEditProvider;
use App\Services\AI\Providers\HairVisionDemoProvider;

$defaultInputImageMaxDimension = (int) env(
    'HAIRVISION_API_IMAGE_MAX_DIMENSION',
    InputImageProcessingSettings::DEFAULT_MAX_DIMENSION,
);
$defaultInputImageJpegQuality = (int) env(
    'HAIRVISION_API_IMAGE_JPEG_QUALITY',
    InputImageProcessingSettings::DEFAULT_JPEG_QUALITY,
);

$inputImageProcessingSettings = [
    [
        'key' => 'input_image_processing_enabled',
        'label' => 'فعال‌سازی پردازش تصویر ورودی',
        'type' => 'boolean',
        'required' => false,
        'rules' => ['nullable', 'boolean'],
        'default' => InputImageProcessingSettings::DEFAULT_ENABLED,
        'secret' => false,
    ],
    [
        'key' => 'input_image_max_dimension',
        'label' => 'اندازه ضلع بزرگ‌تر تصویر ورودی',
        'type' => 'integer',
        'required' => false,
        'rules' => ['nullable', 'required_if:input_image_processing_enabled,1,true', 'integer', 'min:1', 'max:8192'],
        'default' => $defaultInputImageMaxDimension,
        'secret' => false,
    ],
    [
        'key' => 'input_image_jpeg_quality',
        'label' => 'کیفیت JPEG تصویر ورودی',
        'type' => 'integer',
        'required' => false,
        'rules' => ['nullable', 'required_if:input_image_processing_enabled,1,true', 'integer', 'min:1', 'max:100'],
        'default' => $defaultInputImageJpegQuality,
        'secret' => false,
    ],
];

return [
    'types' => [
        'sample_image' => [
            'key' => 'sample_image',
            'label' => 'مدل نمونه',
            'provider' => HairVisionDemoProvider::class,
            'credentials' => [],
            'settings' => $inputImageProcessingSettings,
            'output_fields' => [
                [
                    'key' => 'sample_path',
                    'label' => 'مسیر تصویر نمونه خصوصی',
                    'type' => 'text',
                    'required' => true,
                    'rules' => ['required', 'string', 'max:500'],
                    'secret' => false,
                ],
            ],
            'defaults' => [
                'input_image_processing_enabled' => InputImageProcessingSettings::DEFAULT_ENABLED,
                'input_image_max_dimension' => $defaultInputImageMaxDimension,
                'input_image_jpeg_quality' => $defaultInputImageJpegQuality,
                'concurrency_limit' => 10,
            ],
            'timeouts' => [
                'provider_seconds' => 5,
            ],
            'testable' => false,
        ],
        'gapgpt' => [
            'key' => 'gapgpt',
            'label' => 'GapGPT',
            'provider' => GapGptImageEditProvider::class,
            'credentials' => [
                [
                    'key' => 'api_key',
                    'label' => 'API Key',
                    'type' => 'secret',
                    'required' => true,
                    'rules' => ['required', 'string', 'max:2000'],
                    'secret' => true,
                ],
            ],
            'settings' => [
                ...$inputImageProcessingSettings,
                [
                    'key' => 'endpoint',
                    'label' => 'Endpoint',
                    'type' => 'url',
                    'required' => true,
                    'rules' => ['required', 'url'],
                    'default' => 'https://api.gapgpt.app/v1/images/edits',
                    'secret' => false,
                ],
                [
                    'key' => 'model',
                    'label' => 'Model',
                    'type' => 'text',
                    'required' => true,
                    'rules' => ['required', 'string', 'max:100'],
                    'default' => 'gpt-image-2',
                    'secret' => false,
                ],
                [
                    'key' => 'size',
                    'label' => 'Size',
                    'type' => 'select',
                    'required' => true,
                    'rules' => ['required', 'in:1024x1024'],
                    'options' => ['1024x1024'],
                    'default' => '1024x1024',
                    'secret' => false,
                ],
                [
                    'key' => 'timeout_seconds',
                    'label' => 'Timeout',
                    'type' => 'integer',
                    'required' => true,
                    'rules' => ['required', 'integer', 'min:60', 'max:900'],
                    'default' => 900,
                    'secret' => false,
                ],
            ],
            'output_fields' => [
                [
                    'key' => 'prompt',
                    'label' => 'پرامپت خروجی',
                    'type' => 'textarea',
                    'required' => true,
                    'rules' => ['required', 'string', 'max:20000'],
                    'secret' => false,
                ],
            ],
            'defaults' => [
                'input_image_processing_enabled' => InputImageProcessingSettings::DEFAULT_ENABLED,
                'input_image_max_dimension' => $defaultInputImageMaxDimension,
                'input_image_jpeg_quality' => $defaultInputImageJpegQuality,
                'endpoint' => 'https://api.gapgpt.app/v1/images/edits',
                'model' => 'gpt-image-2',
                'size' => '1024x1024',
                'timeout_seconds' => 900,
                'concurrency_limit' => 1,
            ],
            'timeouts' => [
                'provider_seconds' => 900,
                'connect_seconds' => 30,
            ],
            'testable' => true,
        ],
    ],

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
            'provider' => 'demo',
            'vendor' => 'HairVision',
            'description_key' => 'ai_models.models.sample_image.description',
            'driver' => HairVisionDemoProvider::class,
            'required_credentials' => [],
            'optional_credentials' => [],
            'supported_usage_categories' => ['*'],
            'default_timeout' => 5,
            'endpoint' => null,
            'is_system' => true,
            'is_demo' => true,
            'is_protected' => false,
            'requires_credentials' => false,
            'supports_prompt' => true,
            'supports_image_edit' => true,
            'supports_prompt_customization' => true,
            'supports_credit_based_usage' => true,
            'supports_concurrent_stage_dispatch' => false,
            'access_creatable' => true,
            'access_editable' => true,
        ],
        'gapgpt' => [
            'key' => 'gapgpt',
            'display_name_key' => 'ai_models.models.gapgpt.name',
            'provider' => 'gapgpt',
            'vendor' => 'GapGPT',
            'description_key' => 'ai_models.models.gapgpt.description',
            'driver' => GapGptImageEditProvider::class,
            'required_credentials' => ['api_key'],
            'optional_credentials' => [],
            'configurable_settings' => [
                'endpoint_url',
                'model',
                'size',
                'response_format',
                'timeout_seconds',
                'connect_timeout_seconds',
            ],
            'supported_usage_categories' => ['*'],
            'default_timeout' => 900,
            'default_connect_timeout' => 30,
            'endpoint' => 'https://api.gapgpt.app/v1/images/edits',
            'default_model' => 'gpt-image-2',
            'default_size' => '1024x1024',
            'default_response_format' => 'url',
            'default_n' => 1,
            'image_field' => 'image',
            'is_system' => true,
            'is_demo' => false,
            'is_protected' => false,
            'requires_credentials' => true,
            'supports_prompt' => true,
            'supports_image_edit' => true,
            'supports_prompt_customization' => true,
            'supports_credit_based_usage' => true,
            'supports_concurrent_stage_dispatch' => false,
            'access_creatable' => true,
            'access_editable' => true,
        ],
    ],
];
