# VSCode + Codex Start Guide

This guide is for starting HairVision development in VSCode with Codex or any AI
coding agent.

## Prerequisites

- PHP 8.3+
- Composer
- Node.js LTS
- MySQL/MariaDB or SQLite for local testing
- Redis
- Git
- VSCode
- Codex IDE extension or Codex CLI

## Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
npm run build
```

Map the test tenant host:

```text
127.0.0.1 clinic1.hairvision-ai.test
```

Run the app:

```bash
php artisan serve --host=127.0.0.1 --port=8000
npm run dev
php artisan horizon
```

Open:

```text
http://clinic1.hairvision-ai.test:8000
```

## Required Agent Prompt

```text
You are working on the HairVision Laravel 13 project.

Before making code changes, read AGENTS.md and all files listed in its
"Must Read First" section.

Keep the project on version 1.0.0 / Phase 1 unless explicitly asked otherwise.

Core constraints:
- Laravel 13 conventions.
- API-first Blade.
- Persian RTL public UI with embedded Vazirmatn font.
- Tenant header, HairVision application body, tenant footer, and optional back-to-site link.
- Header, application body, and footer should be full-width.
- No testing/deployment/setup copy in the visitor-facing template.
- Short AI-style progress cast before showing results.
- The result gallery should show the original upload plus three result periods and support zoom.
- No arbitrary external frontend resources.
- Tenant is resolved from request host only.
- Do not trust tenant_id from requests.
- Store OTP hashes only.
- Store uploads and generated assets privately.
- Use SampleImageProvider only.
- SampleImageProvider must not call external services or generate images.
- Redis Queue and Horizon are required outside tests.
- Add or update tests for every behavior change.
- Run php artisan test after every change.

After implementation, report changed files, commands run, and test results.
```

## Phase 1 Implementation Checklist

1. Migrations and models exist for the Phase 1 data model.
2. Tenant resolution middleware uses request host.
3. Seeder creates one test tenant, one tenant domain, and `sample_image`.
4. `/api/v1` routes exist for bootstrap, OTP, upload, generation, status, results, and assets.
5. OTP request stores hashes and is rate-limited.
6. OTP verify creates a verified lead session.
7. Upload requires a verified lead session.
8. Generation requires the current tenant and current session lead.
9. `SampleImageProvider` returns local sample paths only.
10. Jobs copy samples into private tenant/lead result paths.
11. Blade is Persian RTL and calls `/api/v1`.
12. Tenant theme settings support header, footer, and back-to-site link.
13. Landing page visits are tracked before lead creation.
14. Lead CRM stage and admin note structure exists.
15. Feature tests cover the public flow, theme fields, CRM structure, visit tracking, and tenant isolation.

## Test Command

```bash
php artisan test
```

Run this after every behavior change. If a change affects CSS, JS, Blade, fonts,
or Vite inputs, also run:

```bash
npm run build
```

## Useful Commands

```bash
php artisan migrate:fresh --seed
php artisan route:list --path=api/v1
php artisan test
php artisan horizon
php artisan queue:failed
php artisan optimize:clear
npm run build
```
