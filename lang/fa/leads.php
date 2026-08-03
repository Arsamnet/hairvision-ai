<?php

/**
 * Provides Persian lead-management copy revised for the 2.1.0 release.
 *
 * @since 2.1.0
 * @since 2.1.1 Adds lead-list note tooltip fallback copy.
 */
return [
    'title' => 'لیدها',
    'details_title' => 'مشاهده لید',
    'create_title' => 'لید جدید',
    'edit_title' => 'ویرایش لید',
    'manage' => 'مدیریت لیدها',
    'new_lead' => 'لید جدید',
    'edit' => 'ویرایش',
    'delete' => 'حذف لید',
    'details' => 'مشاهده',
    'submit_create' => 'ثبت لید',
    'save_changes' => 'ذخیره تغییرات',
    'cancel' => 'انصراف',
    'no_owner' => 'بدون مسئول',
    'new_note' => 'یادداشت جدید',
    'filter' => 'فیلتر',
    'search_placeholder' => 'جستجو نام، موبایل، شهر یا منبع',
    'all_statuses' => 'همه وضعیت‌ها',
    'empty_filtered' => 'هیچ لیدی با این فیلترها پیدا نشد.',
    'empty_name' => 'لید بدون نام',
    'no_name' => 'بدون نام',
    'no_note' => 'یادداشتی برای این لید ثبت نشده است.',
    'system_user' => 'سیستم',
    'created' => 'لید جدید ثبت شد.',
    'updated' => 'لید به‌روزرسانی شد.',
    'deleted' => 'لید و اطلاعات وابسته آن حذف شد.',
    'confirm_delete' => 'با حذف این لید، اطلاعات CRM، تصاویر و تاریخچه پردازش آن برای همیشه حذف می‌شود. ادامه می‌دهید؟',
    'delete_blocked_active_generation' => 'تا پایان یا لغو پردازش‌های فعال، حذف این لید امکان‌پذیر نیست.',

    'fields' => [
        'name' => 'نام',
        'full_name' => 'نام کامل',
        'age' => 'سن',
        'city' => 'شهر',
        'phone' => 'موبایل',
        'mobile' => 'شماره موبایل',
        'status' => 'وضعیت',
        'source' => 'منبع',
        'owner' => 'مسئول',
        'created_at' => 'ثبت',
        'tenant' => 'کلینیک',
        'registered_at' => 'زمان ثبت',
    ],

    'statuses' => [
        'new' => 'جدید',
        'needs_follow_up' => 'نیاز به پیگیری',
        'no_answer' => 'عدم پاسخ',
        'successful' => 'موفق شده',
        'canceled' => 'لغو شده',
    ],

    'filters' => [
        'age_from' => 'سن از',
        'age_until' => 'سن تا',
    ],

    'upload_statuses' => [
        'uploaded' => 'بارگذاری شده',
        'validated' => 'اعتبارسنجی شده',
        'rejected' => 'رد شده',
    ],

    'uploads' => [
        'title' => 'تصویر اصلی لید',
        'empty' => 'برای این لید هنوز تصویری در سیستم ذخیره نشده است.',
        'alt' => 'تصویر بارگذاری شده لید',
        'uploaded_at' => 'بارگذاری در :date',
    ],

    'source_images' => [
        'alt' => 'تصویر اصلی لید',
        'version' => 'نسخه :version',
        'current' => 'تصویر اصلی فعلی',
        'archived' => 'تصویر قبلی',
    ],

    'generations' => [
        'title' => 'پردازش‌ها',
        'description' => 'هر نوبت بارگذاری را همراه با تصویر ورودی، وضعیت درخواست‌ها و همه نسخه‌های نتیجه مرور کنید.',
        'empty' => 'هنوز پردازشی برای این لید ثبت نشده است.',
        'group' => 'گروه :uuid',
        'independent' => 'پردازش مستقل',
        'requested_at' => 'درخواست در :date',
        'unknown_category' => 'کاربرد نامشخص',
        'unknown_output' => 'خروجی نامشخص',
        'asset_alt' => 'تصویر نتیجه :period',
        'asset_pending' => 'تصویر هنوز آماده نیست',
        'source_image' => 'تصویر ورودی',
        'results' => 'نتایج این تصویر',
        'no_results' => 'برای این تصویر هنوز نتیجه‌ای درخواست نشده است.',
        'upload_round' => 'نوبت بارگذاری :version',
        'legacy_upload' => 'پردازش قدیمی',
        'result_version' => 'نسخه نتیجه :version',
        'current_result' => 'نتیجه فعلی',
        'archived_result' => 'نسخه قبلی نتیجه',
        'not_requested' => 'برای این خروجی هنوز درخواستی ثبت نشده است.',
        'rounds_count' => ':count نوبت بارگذاری',
        'results_count' => ':count تصویر ساخته‌شده',
    ],

    'gallery' => [
        'title' => 'گالری تصاویر لید',
        'open' => 'مشاهده گالری',
        'open_image' => 'نمایش :title در گالری',
        'close' => 'بستن گالری',
        'previous' => 'تصویر قبلی',
        'next' => 'تصویر بعدی',
        'download' => 'دانلود تصویر',
        'current_source_title' => 'تصویر اصلی فعلی لید',
        'source_title' => 'تصویر ورودی نوبت :version',
        'source_description' => 'بارگذاری‌شده در :date و مبنای پردازش‌های این نوبت',
        'legacy_source_title' => 'تصویر بارگذاری‌شده لید',
        'legacy_source_description' => 'بارگذاری‌شده در :date برای پردازش قدیمی',
        'generated_description' => ':category؛ :version، :state',
        'legacy_generated_description' => 'خروجی پردازش قدیمی؛ :state',
    ],

    'periods' => [
        'two_weeks' => 'یک روز بعد از کاشت مو',
        'intermediate_regrowth' => '۶ ماه بعد از کاشت مو',
        'final_result' => 'یک سال بعد از کاشت مو',
        'eyebrow_final_result' => 'یک سال بعد از کاشت ابرو',
        '3_months' => '۳ ماه',
        '6_months' => '۶ ماه',
        '12_months' => '۱۲ ماه',
    ],
];
