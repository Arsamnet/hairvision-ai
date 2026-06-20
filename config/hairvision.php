<?php

return [
    'default_ai_provider' => env('HAIRVISION_DEFAULT_AI_PROVIDER', 'sample_image'),

    'visitor_messages' => [
        'preparing_image' => env('HAIRVISION_PREPARING_IMAGE_MESSAGE', 'Preparing your photo and generating your sample results. Please wait a moment.'),
    ],

    'sample_images' => [
        '3_months' => env('HAIRVISION_SAMPLE_IMAGE_3_MONTHS', 'sample-results/3-months.jpg'),
        '6_months' => env('HAIRVISION_SAMPLE_IMAGE_6_MONTHS', 'sample-results/6-months.jpg'),
        '12_months' => env('HAIRVISION_SAMPLE_IMAGE_12_MONTHS', 'sample-results/12-months.jpg'),
        'fallback' => env('HAIRVISION_SAMPLE_IMAGE_FALLBACK', 'sample-results/hairvision-sample-result.jpg'),
    ],
    // When true, generation jobs for sample images run synchronously (faster in local/debug)
    'debug_fast_samples' => env('HAIRVISION_DEBUG_FAST_SAMPLES', false),
];
