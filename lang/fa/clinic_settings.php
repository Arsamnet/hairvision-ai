<?php

/**
 * Provides Persian clinic-setting copy introduced in the 2.1.0 release.
 *
 * @since 2.1.0
 */
return [
    'title' => 'تنظیمات کلینیک',
    'page_intro_title' => 'مدیریت تنظیمات کلینیک',
    'page_hint' => 'تنظیمات کلینیک در بخش‌های مستقل دسته‌بندی شده‌اند. تغییرات هر بخش را با دکمه ذخیره همان بخش ثبت کنید.',
    'landing_link_title' => 'لینک بازگشت به سایت کلینیک',
    'landing_link_hint' => 'متن، نشانی و نحوه باز شدن دکمه‌ای را تعیین کنید که مراجعه‌کننده را از HairVision به سایت شما بازمی‌گرداند.',
    'save_landing_link' => 'ذخیره لینک بازگشت',
    'url_hint_before' => 'نشانی باید با',
    'url_hint_or' => 'یا',
    'url_hint_after' => 'شروع شود.',
    'fields' => [
        'back_link_title' => 'متن دکمه',
        'back_link_url' => 'لینک سایت کلینیک',
        'back_link_target' => 'نحوه باز شدن لینک',
    ],
    'placeholders' => [
        'back_link_title' => 'بازگشت به سایت کلینیک',
    ],
    'target_options' => [
        '_self' => 'باز شدن در همین صفحه',
        '_blank' => 'باز شدن در زبانه جدید',
    ],
    'messages' => [
        'updated' => 'تنظیمات لینک بازگشت کلینیک ذخیره شد.',
    ],
    'validation' => [
        'template_missing' => 'هیچ قالب لندینگ فعالی برای این کلینیک وجود ندارد.',
    ],
];
