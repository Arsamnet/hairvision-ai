<?php

return [
    'version' => env('APP_VERSION', '1.0.0'),

    'default_ai_provider' => env('HAIRVISION_DEFAULT_AI_PROVIDER', 'sample_image'),

    'primary_domain' => env('HAIRVISION_PRIMARY_DOMAIN', 'hairvision-ai.test'),

    'visitor_messages' => [
        'preparing_image' => env('HAIRVISION_PREPARING_IMAGE_MESSAGE', 'در حال آماده سازی تصویر و ساخت نتایج نمونه هستیم. لطفا چند لحظه صبر کنید.'),
    ],

    'sample_images' => [
        '3_months' => env('HAIRVISION_SAMPLE_IMAGE_3_MONTHS', 'sample-results/3-months.jpg'),
        '6_months' => env('HAIRVISION_SAMPLE_IMAGE_6_MONTHS', 'sample-results/6-months.jpg'),
        '12_months' => env('HAIRVISION_SAMPLE_IMAGE_12_MONTHS', 'sample-results/12-months.jpg'),
        'fallback' => env('HAIRVISION_SAMPLE_IMAGE_FALLBACK', 'sample-results/hairvision-sample-result.jpg'),
    ],

    'otp' => [
        'max_requests' => (int) env('HAIRVISION_OTP_MAX_REQUESTS', 5),
        'decay_seconds' => (int) env('HAIRVISION_OTP_DECAY_SECONDS', 600),
        'max_attempts' => (int) env('HAIRVISION_OTP_MAX_ATTEMPTS', 5),
    ],

    // When true, sample image jobs run synchronously for local/debug UX.
    'debug_fast_samples' => env('HAIRVISION_DEBUG_FAST_SAMPLES', env('APP_DEBUG', false)),
];
