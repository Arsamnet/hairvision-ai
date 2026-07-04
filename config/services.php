<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'gapgpt' => [
        'api_key' => env('GAPGPT_API_KEY'),

        'image_edit_endpoint' => env(
            'GAPGPT_IMAGE_EDIT_ENDPOINT',
            env('GAPGPT_IMAGE_EDIT_ENDPOINT_URL', 'https://api.gapgpt.app/v1/images/edits')
        ),

        'image_model' => env(
            'GAPGPT_IMAGE_MODEL',
            env('GAPGPT_IMAGE_EDIT_MODEL', 'gpt-image-2')
        ),

        'image_size' => env('GAPGPT_IMAGE_SIZE', '1024x1024'),
        'image_response_format' => env('GAPGPT_IMAGE_RESPONSE_FORMAT', 'url'),

        /*
        |--------------------------------------------------------------------------
        | GapGPT HTTP / Polling Timeouts
        |--------------------------------------------------------------------------
        |
        | These values are important because real image generation can take longer
        | than sample image generation. The frontend may keep polling while the
        | queued job continues in the background.
        |
        */

        'connect_timeout' => (int) env('GAPGPT_CONNECT_TIMEOUT_SECONDS', 15),
        'timeout' => (int) env('GAPGPT_TIMEOUT_SECONDS', 300),
        'poll_interval' => (int) env('GAPGPT_POLL_INTERVAL_SECONDS', 5),
        'max_poll_attempts' => (int) env('GAPGPT_MAX_POLL_ATTEMPTS', 120),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];