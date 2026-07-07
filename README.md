# HairVision

Version target: `1.0.0`

HairVision is a Laravel 13 multi-tenant MVP for hair transplant clinics. A visitor
lands on a tenant-specific subdomain or custom domain, verifies a phone number by
OTP, becomes a lead for that tenant, uploads a face/scalp photo, and receives
three staged preview images: two weeks after transplant, intermediate regrowth,
and the final result.

The MVP frontend is a Persian, right-to-left Blade experience using the embedded
Vazirmatn font. Business behavior is API-first: Blade calls versioned `/api/v1`
endpoints and does not duplicate backend business logic.

## Version 1.0.0 Scope

- Laravel 13, PHP 8.3+
- Blade + Tailwind CSS MVP UI
- Persian RTL public UI with embedded `resources/fonts/vazirmatn/*.woff2`
- REST JSON API under `/api/v1`
- Host-based tenant resolution
- OTP lead verification with hashed OTP storage and rate limiting
- Private local upload storage
- Provider-agnostic AI adapter layer
- MVP default provider: `sample_image`
- Optional assigned provider: `gapgpt` image edits for staged generation
- Redis Queue and Laravel Horizon for queue processing outside tests
- Feature tests for tenant resolution, OTP, upload validation, sample
  generation, and tenant isolation

## Core Rule

No public visitor workflow may depend on arbitrary external resources. CSS, JS,
fonts, and sample result assets must be local to the project. Tenant logos and
theme images may be served from the primary HairVision domain only.

## Documentation Index

The root `README.md` is the central documentation entry point. Every project
documentation file lives directly inside `/docs` and is linked here with a
relative Markdown link.

- [Architecture](docs/architecture.md)
- [Business Rules FA](docs/business-rules-fa.md)
- [Setup Guide](docs/setup.md)
- [Development Guide](docs/development-guide.md)
- [Database](docs/database.md)
- [Jalali Dates](docs/dates.md)
- [API Documentation](docs/api.md)
- [AI Models](docs/ai-models.md)
- [AI Model Security](docs/ai-model-security.md)
- [AI Service Access](docs/ai-service-access.md)
- [Authentication](docs/authentication.md)
- [Authentication Roadmap](docs/auth-roadmap.md)
- [Tenancy](docs/tenancy.md)
- [Roles and Permissions](docs/roles-permissions.md)
- [Permissions](docs/permissions.md)
- [System Management](docs/system-management.md)
- [Dashboard](docs/dashboard.md)
- [Plans](docs/plans.md)
- [Subscriptions](docs/subscriptions.md)
- [Credits](docs/credits.md)
- [Billing](docs/billing.md)
- [Frontend Blade Standards](docs/frontend-blade-standards.md)
- [Localization](docs/localization.md)
- [Security](docs/security.md)
- [Performance](docs/performance.md)
- [Debugging and Step Logging](docs/debugging.md)
- [Coding Standards](docs/coding-standards.md)
- [Testing](docs/testing.md)
- [Release Notes](docs/release-notes.md)
- [AI Agent Instructions](docs/agent-instructions.md)

## Local Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
npm run build
```

For local development, map `aria.hairvision-ai.test` to `127.0.0.1`, then run:

```bash
php artisan serve --host=127.0.0.1 --port=8000
npm run dev
php artisan horizon
```

## Test Discipline

After every code or behavior change:

1. Add or update tests for the changed behavior.
2. Run the full suite with `php artisan test`.
3. Run `npm run build` when CSS, JS, Blade, fonts, or Vite inputs change.
4. Do not consider the change commit-ready until tests pass.

Current required test command:

```bash
php artisan test
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

## References

- [Laravel](https://laravel.com/docs)
- [Laravel Horizon](https://laravel.com/docs/horizon)
- [Laravel Sanctum](https://laravel.com/docs/sanctum)
