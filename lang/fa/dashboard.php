<?php

/**
 * Provides Persian dashboard copy revised for the 2.1.0 release.
 *
 * @since 2.1.0
 */
return [
    'admin_title' => 'پنل مدیریت HairVision',
    'product_name' => 'HairVision',
    'clinic_panel' => 'پنل کلینیک',
    'management_panel' => 'پنل مدیریت',
    'dashboard' => 'پیشخوان',
    'all_clinics' => 'همه کلینیک‌ها',
    'platform_overview' => 'نمای کل پلتفرم',
    'logout' => 'خروج',
    'tenant_changed' => 'کلینیک فعال تغییر کرد.',
    'theme' => [
        'toggle' => 'حالت نمایش',
        'menu_item' => 'حالت شب',
        'dark' => 'تغییر به حالت تاریک',
        'light' => 'تغییر به حالت روشن',
    ],
    'user_menu' => [
        'account' => 'حساب کاربری',
        'open' => 'منوی کاربر',
    ],
    'mobile_menu' => [
        'title' => 'منوی اصلی پنل',
        'open' => 'باز کردن منوی پنل',
        'close' => 'بستن منوی پنل',
    ],
    'breadcrumbs' => [
        'label' => 'مسیر سایت',
    ],

    'cards' => [
        'total_leads' => 'کل لیدها',
        'new_leads' => 'لیدهای جدید',
        'follow_up' => 'نیازمند پیگیری',
        'successful' => 'موفق‌شده',
    ],

    'recent_leads' => 'آخرین لیدها',
    'view_all' => 'مشاهده همه',
    'empty_recent_leads' => 'هنوز لیدی برای این محدوده ثبت نشده است.',
    'no_name' => 'بدون نام',

    'performance' => [
        'title' => 'عملکرد ۷ روز اخیر',
        'description' => 'خلاصه مسیر کاربران از ورود به لندینگ تا درخواست تولید تصویر',
        'landing_views' => 'بازدید لندینگ',
        'verified_leads' => 'لید تأییدشده',
        'generations' => 'درخواست تولید',
        'conversion_rate' => 'نرخ تبدیل نهایی',
        'view_report' => 'مشاهده گزارش کامل',
        'chart_label' => 'نمودار بازدید و درخواست تولید تصویر در هفت روز اخیر',
        'day_tooltip' => ':date — :views بازدید، :generations درخواست تولید',
    ],

    'plan' => [
        'status_title' => 'وضعیت پلن',
        'details_title' => 'جزئیات پلن',
        'active' => 'پلن فعال است.',
        'exempt' => 'حساب مدیریتی بدون نیاز به پلن فعال است.',
        'inactive' => 'برای دسترسی به امکانات سیستم باید یک پلن تهیه یا تمدید کنید.',
        'expired' => 'پلن شما منقضی شده است. برای ادامه استفاده، پلن را تمدید کنید.',
        'credit_finished' => 'اعتبار پلن شما تمام شده است. برای ادامه استفاده، پلن را تمدید یا ارتقا دهید.',
        'plan_disabled' => 'پلن خریداری‌شده این کلینیک توسط مدیر سیستم غیرفعال شده است و کاربردهای عمومی آن تا فعال‌سازی دوباره نمایش داده نمی‌شوند.',
        'disabled_by_system_badge' => 'غیرفعال توسط مدیر سیستم',
        'active_until' => 'فعال تا :date',
        'remaining_credit' => ':count اعتبار باقی مانده',
        'service_credits' => 'اعتبار سرویس‌ها',
        'no_service_credit' => 'برای این اشتراک هنوز سرویس فعالی ثبت نشده است.',
        'view_plans' => 'مشاهده پلن‌ها',
        'renew_plan' => 'تمدید پلن',
        'upgrade_plan' => 'ارتقای پلن',
    ],

    'applications' => [
        'title' => 'وضعیت کاربردهای لندینگ',
        'unavailable' => 'غیرفعال',
        'statuses' => [
            'available' => 'این کاربرد در صفحه لندینگ کلینیک فعال و قابل درخواست است.',
            'plan_disabled_by_system' => 'پلن خریداری‌شده توسط مدیر سیستم غیرفعال شده و این کاربرد در لندینگ نمایش داده نمی‌شود.',
            'entitlement_inactive' => 'دسترسی این کاربرد در اشتراک کلینیک غیرفعال است.',
            'category_disabled_by_system' => 'این کاربرد توسط مدیر سیستم غیرفعال شده و برای همه کلینیک‌ها در لندینگ پنهان است.',
            'service_unavailable' => 'دسترسی فعال این کاربرد برای کلینیک پیدا نشد.',
            'model_disabled_by_system' => 'مدل هوش مصنوعی متصل به این کاربرد توسط مدیر سیستم غیرفعال شده و در لندینگ این کلینیک نمایش داده نمی‌شود.',
            'model_credit_finished' => 'مدل متصل به این کاربرد اعتبار قابل استفاده ندارد.',
            'clinic_model_unavailable' => 'برای این کاربرد مدل فعال و متصل به کلینیک وجود ندارد.',
            'output_unavailable' => 'این کاربرد خروجی فعال قابل نمایش ندارد.',
            'executable_model_unavailable' => 'برای خروجی‌های فعال این کاربرد مدل اجرایی سالم و فعال وجود ندارد.',
        ],
    ],
];
