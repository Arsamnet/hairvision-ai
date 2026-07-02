<?php

return [
    'title' => 'لیدها',
    'details_title' => 'جزئیات لید',
    'create_title' => 'لید جدید',
    'edit_title' => 'ویرایش لید',
    'manage' => 'مدیریت لیدها',
    'new_lead' => 'لید جدید',
    'edit' => 'ویرایش',
    'details' => 'جزئیات',
    'submit_create' => 'ثبت لید',
    'save_changes' => 'ذخیره تغییرات',
    'cancel' => 'انصراف',
    'no_owner' => 'بدون مسئول',
    'new_note' => 'یادداشت جدید',
    'filter' => 'فیلتر',
    'search_placeholder' => 'جستجو نام، موبایل یا منبع',
    'all_statuses' => 'همه وضعیت‌ها',
    'all_stages' => 'همه مراحل',
    'empty_filtered' => 'هیچ لیدی با این فیلترها پیدا نشد.',
    'empty_name' => 'لید بدون نام',
    'no_name' => 'بدون نام',
    'system_user' => 'سیستم',
    'created' => 'لید جدید ثبت شد.',
    'updated' => 'لید به‌روزرسانی شد.',

    'fields' => [
        'name' => 'نام',
        'full_name' => 'نام کامل',
        'phone' => 'موبایل',
        'mobile' => 'شماره موبایل',
        'status' => 'وضعیت',
        'crm_stage' => 'مرحله CRM',
        'source' => 'منبع',
        'owner' => 'مسئول',
        'created_at' => 'ثبت',
        'tenant' => 'کلینیک',
        'registered_at' => 'زمان ثبت',
    ],

    'statuses' => [
        'new' => 'جدید',
        'verified' => 'تایید شده',
        'contacted' => 'تماس گرفته شد',
        'qualified' => 'واجد شرایط',
        'archived' => 'آرشیو شده',
    ],

    'crm_stages' => [
        'new_lead' => 'جدید',
        'contacted' => 'تماس گرفته شد',
        'no_answer' => 'پاسخ نداده',
        'needs_follow_up' => 'نیاز به پیگیری',
        'consultation_booked' => 'وقت مشاوره ثبت شد',
        'converted' => 'تبدیل شده',
        'lost' => 'از دست رفته',
        'canceled' => 'لغو شده',
    ],

    'uploads' => [
        'title' => 'تصویر بارگذاری شده',
        'empty' => 'برای این لید هنوز تصویری در سیستم ذخیره نشده است.',
        'alt' => 'تصویر بارگذاری شده لید',
        'uploaded_at' => 'بارگذاری در :date',
    ],

    'generations' => [
        'title' => 'پردازش‌ها',
        'empty' => 'هنوز پردازشی برای این لید ثبت نشده است.',
        'group' => 'گروه :uuid',
    ],

    'periods' => [
        'two_weeks' => 'دو هفته بعد از کاشت',
        'intermediate_regrowth' => 'مرحله رشد میانی',
        'final_result' => 'نتیجه نهایی',
        '3_months' => '۳ ماه',
        '6_months' => '۶ ماه',
        '12_months' => '۱۲ ماه',
    ],
];
