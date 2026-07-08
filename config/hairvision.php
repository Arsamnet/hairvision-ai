<?php

return [
    'version' => env('APP_VERSION', '1.0.0'),

    'default_ai_provider' => env('HAIRVISION_DEFAULT_AI_PROVIDER', 'sample_image'),

    'primary_domain' => env('HAIRVISION_PRIMARY_DOMAIN', 'hairvision-ai.test'),

    'sessions' => [
        'lead_cookie' => env('HAIRVISION_LEAD_SESSION_COOKIE', 'hairvision_lead_session'),
        'clinic_admin_cookie' => env('HAIRVISION_CLINIC_ADMIN_SESSION_COOKIE', 'hairvision_clinic_admin_session'),
        'platform_admin_cookie' => env('HAIRVISION_PLATFORM_ADMIN_SESSION_COOKIE', 'hairvision_platform_admin_session'),
    ],

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
        'name' => env('HAIRVISION_SUPER_ADMIN_NAME', 'مدیر کل'),
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

    'generation' => [
        'message_interval_ms' => (int) env('HAIRVISION_GENERATION_MESSAGE_INTERVAL_MS', 10000),
        'poll_interval_ms' => (int) env('HAIRVISION_GENERATION_POLL_INTERVAL_MS', 5000),

        /*
        |--------------------------------------------------------------------------
        | Generation Polling UX
        |--------------------------------------------------------------------------
        |
        | soft_timeout_ms only changes the frontend message.
        | max_wait_ms is the maximum time the frontend should keep polling
        | in the current page session before telling the user that background
        | processing is still continuing.
        |
        | poll_timeout_ms is kept as a legacy alias so older Blade/JS code
        | that still reads it will not break.
        |
        */

        'soft_timeout_ms' => (int) env('HAIRVISION_GENERATION_SOFT_TIMEOUT_MS', 120000),
        'max_wait_ms' => (int) env('HAIRVISION_GENERATION_MAX_WAIT_MS', 900000),
        'allow_background_completion' => filter_var(
            env('HAIRVISION_GENERATION_ALLOW_BACKGROUND_COMPLETION', true),
            FILTER_VALIDATE_BOOLEAN
        ),

        'poll_timeout_ms' => (int) env(
            'HAIRVISION_GENERATION_POLL_TIMEOUT_MS',
            env('HAIRVISION_GENERATION_MAX_WAIT_MS', 900000)
        ),

        'stages' => [
            'two_weeks' => [
                'order' => 1,
                'label_key' => 'ai.generation.stages.two_weeks',
                'sample_image' => env('HAIRVISION_SAMPLE_IMAGE_TWO_WEEKS', env('HAIRVISION_SAMPLE_IMAGE_3_MONTHS', 'sample-results/two-weeks.jpg')),
                'prompt' => 'Create exactly one realistic hair transplant preview image showing the same person two weeks after a complete hair transplant. Preserve the face identity, camera angle, lighting, skin tone, and background. Only edit the scalp and hairline area. Show an early post-transplant stage with a natural designed hairline, subtle redness, tiny graft marks, mild swelling, and short implanted hair stubble. Keep the result clinical, natural, and photorealistic.',
            ],
            'intermediate_regrowth' => [
                'order' => 2,
                'label_key' => 'ai.generation.stages.intermediate_regrowth',
                'sample_image' => env('HAIRVISION_SAMPLE_IMAGE_INTERMEDIATE_REGROWTH', env('HAIRVISION_SAMPLE_IMAGE_6_MONTHS', 'sample-results/intermediate_regrowth.jpg')),
                'prompt' => 'Create exactly one realistic hair transplant preview image showing the same person in an intermediate regrowth stage after hair transplant. Preserve the face identity, camera angle, lighting, skin tone, and background. Only edit the scalp and hairline area. Show natural early regrowth with reduced healing marks, uneven but believable density, short growing hairs, and a natural hairline that is still filling in. Keep the result photorealistic and medically plausible.',
            ],
            'final_result' => [
                'order' => 3,
                'label_key' => 'ai.generation.stages.final_result',
                'sample_image' => env('HAIRVISION_SAMPLE_IMAGE_FINAL_RESULT', env('HAIRVISION_SAMPLE_IMAGE_12_MONTHS', 'sample-results/final_result.jpg')),
                'prompt' => 'Create exactly one realistic final hair transplant result image showing the same person after full growth from a complete hair transplant. Preserve the face identity, camera angle, lighting, skin tone, and background. Only edit the scalp and hairline area. Show a fuller natural hairline, realistic density, natural direction and texture, and coverage that suits the person’s face. Keep the result photorealistic, balanced, and medically plausible.',
            ],
        ],
        'legacy_stage_map' => [
            '3_months' => 'two_weeks',
            '6_months' => 'intermediate_regrowth',
            '12_months' => 'final_result',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | API Image Preprocessing
    |--------------------------------------------------------------------------
    |
    | The original upload should remain unchanged. Before sending an image to
    | external AI APIs, create an optimized API input copy with a maximum
    | dimension and controlled format/quality.
    |
    */

    'api_image' => [
        'max_dimension' => (int) env('HAIRVISION_API_IMAGE_MAX_DIMENSION', 1024),
        'jpeg_quality' => (int) env('HAIRVISION_API_IMAGE_JPEG_QUALITY', 85),
        'format' => env('HAIRVISION_API_IMAGE_FORMAT', 'jpg'),
    ],

    'sample_images' => [
        '3_months' => env('HAIRVISION_SAMPLE_IMAGE_3_MONTHS', 'sample-results/two-weeks.jpg'),
        '6_months' => env('HAIRVISION_SAMPLE_IMAGE_6_MONTHS', 'sample-results/intermediate_regrowth.jpg'),
        '12_months' => env('HAIRVISION_SAMPLE_IMAGE_12_MONTHS', 'sample-results/final_result.jpg'),
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
        'default_provider' => env('HAIRVISION_DEFAULT_SMS_PROVIDER', 'ippanel'),
        'ippanel' => [
            'authorization_token' => env('HAIRVISION_IPPANEL_AUTHORIZATION_TOKEN'),
            'from_number' => env('HAIRVISION_IPPANEL_FROM_NUMBER', '+983000505'),
            'otp_pattern_code' => env('HAIRVISION_IPPANEL_OTP_PATTERN_CODE', 's5jh64gwzlk0jf3'),
            'otp_pattern_variable' => env('HAIRVISION_IPPANEL_OTP_PATTERN_VARIABLE', 'verification-code'),
            'endpoint_url' => env('HAIRVISION_IPPANEL_ENDPOINT_URL', 'https://edge.ippanel.com/v1/api/send'),
            'timeout_seconds' => (int) env('HAIRVISION_IPPANEL_TIMEOUT_SECONDS', 8),
            'connect_timeout_seconds' => (int) env('HAIRVISION_IPPANEL_CONNECT_TIMEOUT_SECONDS', 3),
        ],
    ],

    // Runs sample image jobs synchronously for local/debug UX.
    'debug_fast_samples' => filter_var(
        env('HAIRVISION_DEBUG_FAST_SAMPLES', env('APP_DEBUG', false)),
        FILTER_VALIDATE_BOOLEAN
    ),
];