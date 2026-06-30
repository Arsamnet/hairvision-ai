<?php

return [
    'title' => 'اشتراک‌ها',
    'active_services' => 'سرویس‌های فعال',
    'remaining_credit' => 'اعتبار باقی‌مانده',
    'used_credit' => 'اعتبار مصرف‌شده',
    'total_credit' => 'اعتبار کل',
    'expires_at' => 'تاریخ پایان',

    'statuses' => [
        'available' => 'قابل استفاده',
        'service_unavailable' => 'سرویس غیرفعال',
        'subscription_required' => 'نیازمند پلن فعال',
        'credit_finished' => 'اعتبار تمام شده',
    ],

    'errors' => [
        'tenant_required' => 'برای خرید این مورد باید کلینیک فعال انتخاب شده باشد.',
        'required' => 'برای استفاده از سرویس‌های هوش مصنوعی باید پلن پایه فعال داشته باشید.',
        'service_unavailable' => 'سرویس :service برای این کلینیک فعال نیست.',
        'add_on_requires_base' => 'برای خرید سرویس افزوده باید ابتدا پلن پایه فعال داشته باشید.',
        'service_duplicate' => 'سرویس :service از قبل برای این اشتراک فعال است.',
        'top_up_requires_base' => 'برای خرید بسته افزایش اعتبار باید پلن پایه فعال داشته باشید.',
        'top_up_requires_service' => 'برای خرید اعتبار سرویس :service، ابتدا باید دسترسی فعال همان سرویس را داشته باشید.',
        'activation_failed' => 'فعال‌سازی اشتراک انجام نشد.',
    ],
];
