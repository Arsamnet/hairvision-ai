# 17 - VSCode + Codex Start Guide

این فایل برای شروع پروژه HairVision در VSCode و استفاده از ChatGPT Plus / Codex آماده شده است.

## پیش‌نیازها روی سیستم توسعه

- PHP 8.3+
- Composer
- Node.js LTS
- MySQL یا MariaDB
- Redis
- Git
- VSCode
- Codex IDE Extension یا Codex CLI

## مرحله 1: ساخت پروژه Laravel 13

```bash
composer create-project laravel/laravel HairVision
cd HairVision
git init
```

## مرحله 2: کپی داکیومنت‌ها

فایل‌های زیر را در ریشه پروژه قرار دهید:

```text
README.md
AGENTS.md
docs/
.vscode/
```

## مرحله 3: نصب packageهای اصلی

```bash
composer require laravel/sanctum
composer require laravel/horizon
composer require predis/predis
```

## مرحله 4: نصب API و Horizon

```bash
php artisan install:api
php artisan horizon:install
```

## مرحله 5: نصب Frontend MVP

```bash
npm install
npm install alpinejs
npm run build
```

در صورت نیاز Tailwind را مطابق starter نصب‌شده Laravel تنظیم کنید.

## مرحله 6: تنظیم .env

```env
APP_NAME=HairVision
APP_URL=http://clinic1.hairvision.test

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_CLIENT=predis

HAIRVISION_DEFAULT_AI_PROVIDER=sample_image
```

## مرحله 7: اجرای local

Terminal 1:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Terminal 2:

```bash
npm run dev
```

Terminal 3:

```bash
php artisan horizon
```

## مرحله 8: prompt شروع برای Codex در VSCode

این prompt را در Codex IDE Extension وارد کنید:

```text
You are working on the HairVision Laravel 13 project.

Before making any code changes, read AGENTS.md and these docs:
- docs/00-ai-agent-context.md
- docs/01-product-scope-mvp.md
- docs/04-api-first-contract.md
- docs/06-multi-tenancy-domain-routing.md
- docs/07-data-model.md
- docs/10-ai-provider-adapters.md
- docs/11-sample-image-provider-mvp.md
- docs/14-security-threat-model.md

Implement Phase 1 only:
1. Create migrations and models for tenants, tenant_domains, leads, otp_codes, uploads, ai_providers, tenant_ai_settings, generation_groups, generation_jobs, generated_assets, templates, tenant_theme_settings, and audit_logs.
2. Add tenant resolution middleware based on request host.
3. Add seeders for one test tenant, one test domain, and the sample_image AI provider.
4. Add API routes under /api/v1 for bootstrap, OTP request, OTP verify, upload, generation creation, generation status, and results.
5. Implement services/actions for OTP, uploads, and generation creation.
6. Implement ImageGenerationProvider interface and SampleImageProvider.
7. Implement queued job that uses SampleImageProvider to copy local sample images into tenant result storage.
8. Add basic Blade pages that call the APIs with fetch. Blade must not duplicate backend business logic.
9. Add feature tests for tenant isolation, OTP flow, upload validation, and sample generation flow.

Constraints:
- Do not implement real AI providers yet.
- Do not add React, Vue, Inertia, Livewire, or Next.js.
- Do not put uploaded user images in public webroot.
- Do not trust tenant_id from request body.
- Use Redis queue and Horizon.
- Keep code small, testable, and aligned with the docs.

After implementation, show the changed files, required commands, and tests to run.
```

## مرحله 9: prompt دوم پس از تکمیل فاز اول

```text
Review the Phase 1 implementation against AGENTS.md and docs/*.md.
Find any violations of:
- API-first Blade rule
- tenant isolation
- private storage rule
- SampleImageProvider no-external-call rule
- OTP hashing and rate limiting
- Redis queue / Horizon usage

Fix only the issues required for MVP correctness. Do not add new features.
```

## مرحله 10: prompt تست

```text
Write or complete Laravel feature tests for HairVision MVP:
1. tenant is resolved by host
2. unknown host returns 404
3. OTP request stores hashed code only
4. OTP verify creates or updates a lead
5. upload requires verified lead session
6. invalid files are rejected
7. generation creates 3 jobs for 3, 6, 12 months
8. SampleImageProvider creates generated assets without external HTTP calls
9. tenant A cannot access tenant B assets

Run the test suite and report failing tests with fixes.
```

## مرحله 11: دستورات مفید توسعه

```bash
php artisan migrate:fresh --seed
php artisan route:list --path=api/v1
php artisan test
php artisan horizon
php artisan queue:failed
php artisan optimize:clear
npm run dev
```

## مرحله 12: commitهای پیشنهادی

```bash
git add .
git commit -m "docs: add HairVision MVP architecture"
git commit -m "feat: add tenant data model and resolver"
git commit -m "feat: add OTP lead authentication flow"
git commit -m "feat: add media upload and private storage"
git commit -m "feat: add sample image AI provider"
git commit -m "feat: add blade API-first MVP screens"
git commit -m "test: cover MVP tenant and generation flows"
```
