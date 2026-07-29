<?php

return [
    'version' => env('APP_VERSION', '2.0.3'),

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
        'email' => env('HAIRVISION_SUPER_ADMIN_EMAIL', 'satrapit@gmail.com'),
        'phone' => env('HAIRVISION_SUPER_ADMIN_PHONE', '09124248164'),
    ],

    'pagination' => [
        'default_per_page' => (int) env('HAIRVISION_API_PER_PAGE', 15),
        'max_per_page' => (int) env('HAIRVISION_API_MAX_PER_PAGE', 100),
    ],

    'visitor_messages' => [
        'preparing_image' => env('HAIRVISION_PREPARING_IMAGE_MESSAGE', 'در حال آماده سازی تصویر و ساخت نتایج نمونه هستیم. لطفا چند لحظه صبر کنید.'),
    ],

    'queue_worker' => [
        'cpanel_cron_enabled' => filter_var(
            env('HAIRVISION_CPANEL_QUEUE_CRON_ENABLED', false),
            FILTER_VALIDATE_BOOLEAN
        ),
        'cpanel_connection' => env('HAIRVISION_CPANEL_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'database')),
        'queues' => env('HAIRVISION_CPANEL_QUEUE_NAMES', 'ai-generation,default'),
        'processes' => max(1, (int) env('HAIRVISION_CPANEL_QUEUE_PROCESSES', 1)),
        'window_seconds' => (int) env('HAIRVISION_CPANEL_QUEUE_WINDOW_SECONDS', 58),
        'sleep_seconds' => (int) env('HAIRVISION_CPANEL_QUEUE_SLEEP_SECONDS', 1),
        'timeout_seconds' => (int) env('HAIRVISION_QUEUE_WORKER_TIMEOUT', 1020),
        'overlap_expiration_minutes' => (int) env('HAIRVISION_CPANEL_QUEUE_OVERLAP_MINUTES', 30),
    ],

    'generation' => [
        'queue_connection' => env('HAIRVISION_GENERATION_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'database')),
        'queue_name' => env('HAIRVISION_GENERATION_QUEUE_NAME', 'ai-generation'),
        'reservation_stale_seconds' => (int) env('HAIRVISION_GENERATION_RESERVATION_STALE_SECONDS', 300),
        'processing_review_seconds' => (int) env('HAIRVISION_GENERATION_PROCESSING_REVIEW_SECONDS', 1800),
        'provider_rate_limit_retries' => (int) env('HAIRVISION_PROVIDER_RATE_LIMIT_RETRIES', 3),
        'provider_rate_limit_delay_seconds' => (int) env('HAIRVISION_PROVIDER_RATE_LIMIT_DELAY_SECONDS', 30),
        'provider_rate_limit_max_delay_seconds' => (int) env('HAIRVISION_PROVIDER_RATE_LIMIT_MAX_DELAY_SECONDS', 300),
        'provider_failure_threshold' => (int) env('HAIRVISION_PROVIDER_FAILURE_THRESHOLD', 5),
        'provider_failure_cooldown_seconds' => (int) env('HAIRVISION_PROVIDER_FAILURE_COOLDOWN_SECONDS', 300),
        'message_interval_ms' => (int) env('HAIRVISION_GENERATION_MESSAGE_INTERVAL_MS', 10000),
        'poll_interval_ms' => (int) env('HAIRVISION_GENERATION_POLL_INTERVAL_MS', 10000),

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
            env('HAIRVISION_GENERATION_ALLOW_BACKGROUND_COMPLETION', false),
            FILTER_VALIDATE_BOOLEAN
        ),

        'poll_timeout_ms' => (int) env(
            'HAIRVISION_GENERATION_POLL_TIMEOUT_MS',
            env('HAIRVISION_GENERATION_MAX_WAIT_MS', 900000)
        ),

        'services' => [
            'hair_transplant' => [
                'stages' => ['two_weeks', 'intermediate_regrowth', 'final_result'],
            ],
            'eyebrow_transplant' => [
                'stages' => ['eyebrow_final_result'],
            ],
        ],

        'stages' => [
            'two_weeks' => [
                'order' => 1,
                'label_key' => 'ai.generation.stages.two_weeks',
                'sample_image' => env('HAIRVISION_SAMPLE_IMAGE_TWO_WEEKS', env('HAIRVISION_SAMPLE_IMAGE_3_MONTHS', 'sample-results/two-weeks.jpg')),
                'prompt' => 'Use the uploaded photo as the direct reference for an image-to-image/inpainting edit showing the same person 1 day after a complete hair transplant. Modify ONLY the scalp, hair, and hairline. Preserve the exact identity, face, age, skin tone, facial features, facial hair, expression, pose, camera angle, framing, clothing, background, objects, environment, and original lighting. No ring light, studio lighting, beauty filters, skin smoothing, retouching, or composition changes. Reconstruct a natural, age-appropriate hairline and fully implant all necessary bald or thinning recipient areas, including the frontal hairline, temples, frontal scalp, mid-scalp, and top. Do not limit the transplant to existing hair. Show a realistic day-one result: closely shaved recipient areas, densely placed visible grafts, tiny red implantation dots, mild redness, minimal swelling, and fresh micro-scabbing. The graft pattern must clearly cover the previously bald areas, but must remain medically believable and not overly graphic. Do not show grown or dense hair at this stage. Match the person’s natural hair color and texture. Ultra-realistic, high-resolution, unedited documentary medical-photo style. The only difference from the source image must be the newly transplanted scalp and reconstructed hairline.',
            ],
            'intermediate_regrowth' => [
                'order' => 2,
                'label_key' => 'ai.generation.stages.intermediate_regrowth',
                'sample_image' => env('HAIRVISION_SAMPLE_IMAGE_INTERMEDIATE_REGROWTH', env('HAIRVISION_SAMPLE_IMAGE_6_MONTHS', 'sample-results/intermediate_regrowth.jpg')),
                'prompt' => 'Use the uploaded photo as the direct reference for an image-to-image/inpainting edit showing the same person 6 months after a complete hair transplant. Modify ONLY the scalp, hair, and hairline. Preserve the exact identity, face, age, skin tone, facial features, facial hair, expression, pose, camera angle, framing, clothing, background, objects, environment, and original lighting. No ring light, studio lighting, beauty filters, skin smoothing, retouching, or composition changes. Show intermediate regrowth across all transplanted areas, including the reconstructed frontal hairline, temples, frontal scalp, mid-scalp, and top. New hair must extend into previously bald areas rather than only thickening the original hair. The wounds, redness, and scabs must be fully healed. The established hairline should be visible but still sparse, short, thin, slightly uneven, and incomplete. Keep some scalp visible and avoid final-stage density. Match the person’s natural hair color, direction, and texture. Ultra-realistic, high-resolution, unedited documentary style. The only difference from the source image must be the realistic 6-month hair-transplant growth.',
            ],
            'final_result' => [
                'order' => 3,
                'label_key' => 'ai.generation.stages.final_result',
                'sample_image' => env('HAIRVISION_SAMPLE_IMAGE_FINAL_RESULT', env('HAIRVISION_SAMPLE_IMAGE_12_MONTHS', 'sample-results/final_result.jpg')),
                'prompt' => 'Use the uploaded photo as the direct reference for an image-to-image/inpainting edit showing the same person 1 year after a complete and successful hair transplant. Modify ONLY the scalp, hair, and hairline. Preserve the exact identity, face, age, skin tone, facial features, facial hair, expression, pose, camera angle, framing, clothing, background, objects, environment, and original lighting. No ring light, studio lighting, beauty filters, skin smoothing, retouching, or composition changes. Naturally restore all previously bald or thinning areas, including the frontal hairline, temples, frontal scalp, mid-scalp, and top. Do not merely thicken the remaining original hair. Create a natural, age-appropriate hairline proportional to the person’s face. Show fully grown, healthy, naturally fuller hair with realistic density, direction, color, and texture matching the original person. The transplanted areas should no longer appear bald, while retaining slight natural irregularity and subtle scalp visibility where appropriate. Avoid excessive density, sharp artificial hairlines, perfect symmetry, helmet hair, or a wig-like appearance. Ultra-realistic, high-resolution, unedited documentary style. The only difference from the source image must be the natural final hair-transplant result.',
            ],
            'eyebrow_final_result' => [
                'order' => 1,
                'label_key' => 'ai.generation.stages.eyebrow_final_result',
                'sample_image' => env('HAIRVISION_SAMPLE_IMAGE_EYEBROW_FINAL_RESULT', 'sample-results/eyebrow_final_result.jpg'),
                'prompt' => 'Use the uploaded photo as the direct reference for an image-to-image/inpainting edit showing the same person 1 year after a successful eyebrow transplant. Modify ONLY the eyebrows. Preserve the exact identity, face, age, skin tone, eyes, expression, pose, camera angle, framing, clothing, head hair, facial hair, background, and original lighting. No ring light, studio lighting, beauty filters, skin smoothing, retouching, or composition changes. Create naturally fuller, well-defined, age-appropriate eyebrows proportional to the face. Match the person’s natural eyebrow color and texture, with realistic individual hairs and growth direction: slightly upward at the inner brow, gradually angled outward through the arch and tail. Keep subtle irregularity and natural asymmetry. The transplant must appear fully healed, with no redness, scabs, swelling, or visible procedure marks. Avoid excessive thickness, sharp edges, perfect symmetry, painted-on makeup, tattooed or microbladed appearance, and artificial density. Ultra-realistic, high-resolution, unedited documentary-photo style. The only difference from the source image must be the realistic 1-year eyebrow-transplant result.',
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
    | AI Image Normalization
    |--------------------------------------------------------------------------
    |
    | The original upload remains unchanged. Provider inputs are prepared as
    | private copies and generated results are persisted with the same default
    | longest-side dimension and JPEG quality.
    |
    */

    'api_image' => [
        'max_dimension' => (int) env('HAIRVISION_API_IMAGE_MAX_DIMENSION', 800),
        'jpeg_quality' => (int) env('HAIRVISION_API_IMAGE_JPEG_QUALITY', 85),
        'format' => env('HAIRVISION_API_IMAGE_FORMAT', 'jpg'),
    ],

    'sample_images' => [
        '3_months' => env('HAIRVISION_SAMPLE_IMAGE_3_MONTHS', 'sample-results/two-weeks.jpg'),
        '6_months' => env('HAIRVISION_SAMPLE_IMAGE_6_MONTHS', 'sample-results/intermediate_regrowth.jpg'),
        '12_months' => env('HAIRVISION_SAMPLE_IMAGE_12_MONTHS', 'sample-results/final_result.jpg'),
        'eyebrow_final_result' => env('HAIRVISION_SAMPLE_IMAGE_EYEBROW_FINAL_RESULT', 'sample-results/eyebrow_final_result.jpg'),
        'fallback' => env('HAIRVISION_SAMPLE_IMAGE_FALLBACK', 'sample-results/hairvision-sample-result.jpg'),
    ],

    'otp' => [
        'max_requests' => (int) env('HAIRVISION_OTP_MAX_REQUESTS', 5),
        'decay_seconds' => (int) env('HAIRVISION_OTP_DECAY_SECONDS', 600),
        'max_attempts' => (int) env('HAIRVISION_OTP_MAX_ATTEMPTS', 5),
        'expires_seconds' => (int) env('HAIRVISION_OTP_EXPIRES_SECONDS', 120),
    ],

    'admin_otp' => [
        'max_requests' => (int) env('HAIRVISION_ADMIN_OTP_MAX_REQUESTS', 5),
        'decay_seconds' => (int) env('HAIRVISION_ADMIN_OTP_DECAY_SECONDS', 600),
        'max_verify_attempts' => (int) env('HAIRVISION_ADMIN_OTP_MAX_VERIFY_ATTEMPTS', 5),
        'verify_decay_seconds' => (int) env('HAIRVISION_ADMIN_OTP_VERIFY_DECAY_SECONDS', 600),
        'max_attempts' => (int) env('HAIRVISION_ADMIN_OTP_MAX_ATTEMPTS', 5),
        'expires_seconds' => (int) env('HAIRVISION_ADMIN_OTP_EXPIRES_SECONDS', 300),
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
            'send_attempts' => max(3, (int) env('HAIRVISION_IPPANEL_SEND_ATTEMPTS', 3)),
            'retry_delay_milliseconds' => max(0, (int) env('HAIRVISION_IPPANEL_RETRY_DELAY_MILLISECONDS', 200)),
        ],
    ],

    // Runs sample image jobs synchronously for local/debug UX.
    'debug_fast_samples' => filter_var(
        env('HAIRVISION_DEBUG_FAST_SAMPLES', env('APP_DEBUG', false)),
        FILTER_VALIDATE_BOOLEAN
    ),

    'debug_steps' => [
        'database_queries' => filter_var(
            env('HAIRVISION_DEBUG_STEP_DATABASE_QUERIES', true),
            FILTER_VALIDATE_BOOLEAN
        ),
        'model_events' => filter_var(
            env('HAIRVISION_DEBUG_STEP_MODEL_EVENTS', true),
            FILTER_VALIDATE_BOOLEAN
        ),
        'slow_query_ms' => (float) env('HAIRVISION_DEBUG_STEP_SLOW_QUERY_MS', 0),
    ],
];
