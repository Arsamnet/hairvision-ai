<?php

return [
    'title' => 'پلن‌ها',
    'new_plan' => 'پلن جدید',
    'management_title' => 'مدیریت پلن‌ها',
    'available_plans' => 'پلن‌های فعال',
    'management_hint' => 'تعریف و وضعیت پلن‌ها فقط برای مدیر سیستم یا کاربر دارای مجوز مدیریت پلن قابل تغییر است.',
    'clinic_hint' => 'پلن فعال شما در پیشخوان نمایش داده می‌شود؛ از این بخش فقط برای خرید، تمدید یا ارتقا استفاده کنید.',
    'current_plan' => 'پلن فعال',
    'service_credits' => 'اعتبار سرویس‌ها',
    'services' => 'سرویس‌ها',
    'purchase_or_upgrade' => 'خرید / ارتقا',
    'upgrade' => 'ارتقا',
    'buy_add_on' => 'خرید سرویس افزوده',
    'buy_top_up' => 'خرید اعتبار',
    'not_purchasable' => 'غیرقابل خرید',
    'visible_to_clinics' => 'نمایش برای کلینیک‌ها',
    'hidden_from_clinics' => 'مخفی از کلینیک‌ها',
    'all_types' => 'همه نوع بسته‌ها',
    'create' => 'ثبت',
    'edit' => 'ویرایش',
    'update' => 'به‌روزرسانی',
    'delete' => 'حذف',
    'filter' => 'فیلتر',
    'search_placeholder' => 'جستجوی نام، توضیح یا واحد پلن',
    'all_statuses' => 'همه وضعیت‌ها',
    'created' => 'پلن جدید ساخته شد.',
    'updated' => 'پلن به‌روزرسانی شد.',
    'deleted' => 'پلن حذف شد.',
    'invoice_ready' => 'فاکتور خرید پلن آماده پرداخت است: :number',
    'inactive_plan' => 'این پلن فعال نیست.',
    'same_active_plan_blocked' => 'این پلن در حال حاضر برای شما فعال است و تا پایان زمان یا اعتبار، امکان خرید دوباره همان پلن وجود ندارد.',
    'service_access_already_available' => 'دسترسی سرویس‌های این پلن در حال حاضر برای کلینیک فعال است.',
    'empty' => 'در حال حاضر پلن فعالی برای خرید وجود ندارد.',

    'fields' => [
        'name' => 'نام',
        'description' => 'توضیح',
        'plan_type' => 'نوع پلن',
        'service_key' => 'سرویس',
        'price' => 'قیمت',
        'duration_days' => 'روز',
        'usage_limit' => 'اعتبار',
        'currency' => 'واحد',
        'status' => 'وضعیت',
        'visibility' => 'نمایش',
    ],

    'statuses' => [
        'active' => 'فعال',
        'inactive' => 'غیرفعال',
    ],

    'types' => [
        'base_subscription' => 'پلن پایه',
        'service_add_on' => 'سرویس افزوده',
        'credit_top_up' => 'بسته افزایش اعتبار',
    ],

    'validation' => [
        'duration_required' => 'مدت اعتبار برای پلن پایه و سرویس افزوده الزامی است.',
        'service_credit_required' => 'حداقل برای یک سرویس باید اعتبار مثبت وارد شود.',
    ],

    'purchase_disabled' => [
        'inactive' => 'این پلن فعال نیست.',
        'already_available' => 'دسترسی این سرویس در پلن فعال شما فراهم است.',
        'add_on_requires_base' => 'برای خرید سرویس افزوده ابتدا باید پلن پایه فعال داشته باشید.',
        'top_up_requires_service' => 'برای خرید این بسته، سرویس مربوطه باید از قبل فعال باشد.',
    ],
];
