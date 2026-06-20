# 00 - AI Agent Context

این سند برای AI Agentهایی مثل Codex، ChatGPT Agent، Copilot Agent یا Cursor Agent نوشته شده است.

## هدف پروژه

HairVision یک سرویس چندمشتری برای کلینیک‌های کاشت مو است. هر مشتری می‌تواند روی ساب‌دامین خود یا دامنه اختصاصی، صفحه‌ای برای جذب لید داشته باشد. کاربر شماره موبایل خود را تایید می‌کند، عکس آپلود می‌کند و خروجی شبیه‌سازی رشد مو را دریافت می‌کند.

## وضعیت فعلی پروژه

مرحله فعلی: MVP

در MVP اتصال به AI واقعی الزامی نیست. باید یک provider تستی ساخته شود که خروجی تصویری آماده را برگرداند.

## معماری اصلی

```text
Browser
  -> Blade page
  -> fetch('/api/v1/...')
  -> Laravel API Controller
  -> Service / Action
  -> Redis Queue Job
  -> AI Provider Adapter
  -> Storage
  -> JSON response
```

## دلیل API-first بودن Blade

Blade فقط برای سریع‌تر شدن MVP استفاده می‌شود. اما تصمیم معماری این است که در آینده Frontend بتواند با Next.js جدا شود. بنابراین:

- business logic داخل Blade controller نباشد.
- routeهای web فقط view shell برگردانند.
- عملیات OTP، upload، generation، status و results از API انجام شود.
- API contract پایدار و versioned باشد.

## قوانین اصلی برای AI Agent

1. ابتدا docs را بخوان.
2. تغییرات را کوچک و مرحله‌ای انجام بده.
3. هر مرحله باید قابل تست باشد.
4. Provider واقعی AI را قبل از تکمیل SampleImageProvider پیاده‌سازی نکن.
5. برای هر feature، migration، model، service، route، controller و test مرتبط را بساز.
6. tenant isolation را در همه queryها رعایت کن.
7. از `tenant_id` ارسالی کاربر استفاده نکن.
8. کد OTP، API key و مسیر private فایل‌ها را نمایش نده.

## خروجی مورد انتظار فاز اول

در پایان فاز اول باید بتوانیم:

1. Tenant تستی بسازیم.
2. با host تست، tenant را resolve کنیم.
3. شماره موبایل ثبت کنیم.
4. OTP را تایید کنیم.
5. عکس آپلود کنیم.
6. job تولید خروجی بسازیم.
7. SampleImageProvider سه خروجی نمونه برای 3، 6 و 12 ماه ثبت کند.
8. Blade page با API وضعیت را نمایش دهد.
