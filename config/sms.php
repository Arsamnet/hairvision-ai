<?php

return [
    'fake' => [
        'enabled' => env('SMS_FAKE_ENABLED', env('APP_ENV') === 'local' || env('APP_ENV') === 'testing'),
    ],

    'http' => [
        'timeout' => (int) env('SMS_HTTP_TIMEOUT', 10),
    ],

    'logging' => [
        'store_message_body' => (bool) env('SMS_STORE_MESSAGE_BODY', false),
        'preview_length' => (int) env('SMS_MESSAGE_PREVIEW_LENGTH', 80),
    ],

    'providers' => [
        'melli_payamak' => [
            'endpoint' => env('SMS_MELLI_PAYAMAK_ENDPOINT', 'https://rest.payamak-panel.com/api/SendSMS/SendSMS'),
        ],
        'kavenegar' => [
            'endpoint' => env('SMS_KAVENEGAR_ENDPOINT', 'https://api.kavenegar.com/v1'),
        ],
        'ippanel' => [
            'endpoint' => env('SMS_IPPANEL_ENDPOINT', 'https://edge.ippanel.com/v1/api/send'),
            'from_number' => env('SMS_IPPANEL_FROM_NUMBER'),
            'otp_pattern_code' => env('SMS_IPPANEL_OTP_PATTERN_CODE', 's5jh64gwzlk0jf3'),
        ],
        'sms_ir' => [
            'endpoint' => env('SMS_IR_ENDPOINT', 'https://api.sms.ir/v1'),
        ],
    ],
];
