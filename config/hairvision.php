<?php

return [
    'version' => env('APP_VERSION', '1.0.0'),

    'default_ai_provider' => env('HAIRVISION_DEFAULT_AI_PROVIDER', 'sample_image'),

    'primary_domain' => env('HAIRVISION_PRIMARY_DOMAIN', 'hairvision-ai.test'),

    'billing' => [
        'default_service_key' => env('HAIRVISION_DEFAULT_SERVICE_KEY', 'hair_transplant'),
        'allow_duplicate_service_add_ons' => false,
    ],

    'ai_services' => [
        'hair_transplant' => [
            'label_key' => 'services.keys.hair_transplant',
            'description_key' => 'services.descriptions.hair_transplant',
        ],
        'eyebrow_transplant' => [
            'label_key' => 'services.keys.eyebrow_transplant',
            'description_key' => 'services.descriptions.eyebrow_transplant',
        ],
    ],

    'super_admin' => [
        'name' => env('HAIRVISION_SUPER_ADMIN_NAME', 'مدیر سیستم HairVision'),
        'email' => env('HAIRVISION_SUPER_ADMIN_EMAIL', 'superadmin@example.com'),
        'phone' => env('HAIRVISION_SUPER_ADMIN_PHONE', '09129999999'),
    ],

    'pagination' => [
        'default_per_page' => (int) env('HAIRVISION_API_PER_PAGE', 15),
        'max_per_page' => (int) env('HAIRVISION_API_MAX_PER_PAGE', 100),
    ],

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

    'admin_otp' => [
        'max_requests' => (int) env('HAIRVISION_ADMIN_OTP_MAX_REQUESTS', 5),
        'decay_seconds' => (int) env('HAIRVISION_ADMIN_OTP_DECAY_SECONDS', 600),
        'max_verify_attempts' => (int) env('HAIRVISION_ADMIN_OTP_MAX_VERIFY_ATTEMPTS', 5),
        'verify_decay_seconds' => (int) env('HAIRVISION_ADMIN_OTP_VERIFY_DECAY_SECONDS', 600),
        'max_attempts' => (int) env('HAIRVISION_ADMIN_OTP_MAX_ATTEMPTS', 5),
    ],

    'sms' => [
        'ippanel' => [
            'authorization_token' => env('HAIRVISION_IPPANEL_AUTHORIZATION_TOKEN'),
            'from_number' => env('HAIRVISION_IPPANEL_FROM_NUMBER', '+983000505'),
            'otp_pattern_code' => env('HAIRVISION_IPPANEL_OTP_PATTERN_CODE', 's5jh64gwzlk0jf3'),
            'endpoint_url' => env('HAIRVISION_IPPANEL_ENDPOINT_URL', 'https://edge.ippanel.com/v1/api/send'),
        ],
    ],

    // Runs sample image jobs synchronously for local/debug UX.
    'debug_fast_samples' => env('HAIRVISION_DEBUG_FAST_SAMPLES', env('APP_DEBUG', false)),
];
