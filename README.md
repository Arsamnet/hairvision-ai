# HairVision

HairVision یک سرویس چندمشتری برای کلینیک‌ها و وب‌سایت‌های فعال در حوزه کاشت مو است. هدف MVP این است که کاربر روی ساب‌دامین اختصاصی مشتری وارد شود، شماره موبایل خود را با OTP تایید کند، به‌عنوان لید همان مشتری ثبت شود، یک عکس آپلود کند و سپس خروجی تستی/واقعی شبیه‌سازی رشد مو را دریافت کند.

در MVP، UI با Blade و Tailwind ساخته می‌شود، اما همه عملیات اصلی از طریق APIهای نسخه‌بندی‌شده `/api/v1` انجام می‌شود تا در آینده بتوان Frontend را بدون بازنویسی Backend به Next.js منتقل کرد.

## تصمیم‌های اصلی MVP

- Framework: Laravel 13
- PHP: 8.3 یا بالاتر
- UI اولیه: Blade + Tailwind + Alpine.js
- API: REST JSON، نسخه‌بندی‌شده با `/api/v1`
- Auth کاربر مراجعه‌کننده: OTP + Session/Sanctum-ready
- Auth مدیر: Laravel auth/session، قابل توسعه به Sanctum
- Queue: Redis Queue
- Queue Monitoring: Laravel Horizon
- Cache: Redis، با امکان استفاده محدود از Memcached برای cacheهای خاص
- Server: WHM/cPanel با دسترسی root
- Storage: local private storage در MVP، قابل مهاجرت به S3/MinIO بعداً
- AI: provider-agnostic adapter layer
- MVP Test AI Provider: `SampleImageProvider` که هیچ تصویر جدیدی تولید نمی‌کند و فقط تصویر نمونه آماده را به‌عنوان خروجی تست برمی‌گرداند.

## Documentation

Kept core MVP docs in `docs/` for developer onboarding and agent workflows:

```text
docs/
  00-ai-agent-context.md
  01-product-scope-mvp.md
  04-api-first-contract.md
  10-ai-provider-adapters.md
  11-sample-image-provider-mvp.md
  17-vscode-codex-start.md
```

## ترتیب مطالعه برای توسعه‌دهنده انسانی

1. `docs/01-product-scope-mvp.md`
2. `docs/02-tech-stack-requirements.md`
3. `docs/03-architecture-whm-cpanel.md`
4. `docs/04-api-first-contract.md`
5. `docs/10-ai-provider-adapters.md`
6. `docs/11-sample-image-provider-mvp.md`
7. `docs/17-vscode-codex-start.md`

## ترتیب مطالعه برای AI Agent / Codex

Codex باید ابتدا این فایل‌ها را بخواند:

1. `AGENTS.md`
2. `docs/00-ai-agent-context.md`
3. `docs/04-api-first-contract.md`
4. `docs/10-ai-provider-adapters.md`
5. `docs/11-sample-image-provider-mvp.md`
6. `docs/17-vscode-codex-start.md`

## شروع سریع توسعه

```bash
composer create-project laravel/laravel HairVision
cd HairVision
git init
```

سپس این فایل‌ها را به ریشه پروژه کپی کنید:

```text
README.md
AGENTS.md
docs/
.vscode/
```

بعد دستورات مرحله اول را از این فایل اجرا کنید:

```text
docs/17-vscode-codex-start.md
```

## قانون مهم MVP

تا زمانی که provider واقعی AI وصل نشده است، خروجی AI نباید mock بی‌ساختار یا random باشد. در MVP باید از `SampleImageProvider` استفاده شود. این provider هیچ پردازش AI انجام نمی‌دهد و فقط برای هر بازه زمانی، تصویر نمونه آماده را در خروجی job ثبت می‌کند.

## References

- Laravel 13 Release Notes: https://laravel.com/docs/13.x/releases
- Laravel Horizon: https://laravel.com/docs/13.x/horizon
- Laravel Sanctum: https://laravel.com/docs/13.x/sanctum
- Laravel Cache: https://laravel.com/docs/13.x/cache
- OpenAI Codex Help: https://help.openai.com/en/articles/11369540-getting-started-with-codex
- OpenAI Codex IDE Docs: https://developers.openai.com/codex/ide
