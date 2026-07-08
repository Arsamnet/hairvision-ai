# Engineering Architecture

Version: `1.0.0`

This document is the canonical engineering reference for HairVision. It
consolidates architecture, data model, setup, coding standards, frontend rules,
security, performance, debugging, and testing expectations.

## Runtime Shape

Public visitor flow:

```text
Browser
  -> Persian RTL Blade shell
  -> fetch('/api/v1/...')
  -> API controller
  -> service/action
  -> Redis queue job
  -> AI provider adapter
  -> private storage
  -> JSON/stream response
```

Admin flow:

```text
Browser
  -> RTL Blade admin dashboard
  -> Laravel session auth
  -> admin controller/Form Request
  -> policy/middleware/tenant context
  -> service/action
  -> tenant-scoped Eloquent query
```

Controllers validate, authorize, call services/actions/jobs, and return JSON,
streams, redirects, or view shells. Business logic, provider logic, billing
rules, and tenant scoping must not be duplicated in Blade or controllers.

## Tech Stack

- PHP 8.3+
- Laravel 13
- Blade, Tailwind CSS, Vite
- Laravel Sanctum
- Redis, Predis, Laravel Horizon
- MySQL/MariaDB or SQLite for local tests
- `morilog/jalali`
- local Vazirmatn font files
- local UIcons dashboard icon subset

## Multi-Tenancy

Public tenant identity is resolved only from the request host:

1. Read `$request->getHost()`.
2. Lowercase the host.
3. Find an active `tenant_domains` row.
4. Ensure the related tenant is active.
5. Bind the tenant as `currentTenant` and on request attributes.
6. Abort with `404` when host or tenant is not active.

Never accept visitor-controlled `tenant_id`, tenant slug, or tenant domain as
authority. Local development may fall back from `localhost` or `127.0.0.1` to
the first active tenant; tests and production should use explicit hosts.

Tenant-owned records include `tenant_id`. Visitor-owned resources also include
`lead_id` and are scoped to the current verified lead session. Dashboard
clinic filters are accepted only after membership or super-admin checks.

## Session Boundaries

HairVision uses separate cookies for:

- public verified-lead session
- clinic admin dashboard session on tenant hosts
- platform admin dashboard session on the primary host

Admin login/logout/session regeneration must not invalidate a public visitor
lead session in the same browser.

## Public Visitor Flow

Required public routes live under `/api/v1` and are documented in
[`api.md`](api.md). The Blade landing page must call those routes for bootstrap,
OTP, upload, generation, status, results, and assets.

Generation creates three period jobs:

- `two_weeks`
- `intermediate_regrowth`
- `final_result`

The default staged flow dispatches the first pending job, then dispatches the
next stage after each success. GapGPT dispatches all three pending stages
together because its image edits are independent. Each stage remains a separate
job for retries, failures, assets, logs, and credit accounting.

## AI Provider Layer

Required provider flow:

```text
GenerationController
  -> GenerationService
  -> ServiceEntitlementService
  -> AiModelManager / AiModelResolver
  -> generation jobs
  -> RunAiModelAction
  -> ProviderResolver
  -> ImageGenerationProvider or AiModelProviderInterface
  -> CreditLedgerService after success
  -> private generated asset
```

Provider selection and real provider credentials are server-side only. Clients
submit `upload_id`, not provider, tenant, model, or credentials.

Configured model definitions live in `config/ai-models.php`; access records live
in `ai_model_accesses`; clinic assignments live in `clinic_ai_model_access`.
Only active assigned records for the required usage category may run.

`sample_image` is the protected default provider for version `1.0.0`. It returns
configured local sample paths, does not call the internet, and does not generate
or mutate images.

GapGPT image edits must use multipart form data:

```text
POST https://api.gapgpt.app/v1/images/edits
Authorization: Bearer <encrypted access credential>
Accept: application/json
image=<file>
model=gpt-image-2
prompt=<stage prompt>
n=1
response_format=url
size=1024x1024
```

Do not send GapGPT requests as JSON. Do not seed, commit, log, display, or
document real provider keys.

## Billing and Service Access

Public generation requires:

1. active base subscription
2. active `hair_transplant` service entitlement
3. enough remaining service credit for the three periods
4. active assigned AI model access for `hair_transplant`

Plan types:

- `base_subscription`
- `service_add_on`
- `credit_top_up`

Service credits are stored in `subscription_service_entitlements`; all grants,
top-ups, uses, refunds, and adjustments are recorded in `credit_transactions`.
Credit consumption uses a database transaction and row lock. Credit is consumed
only after provider execution succeeds.

Payment verification is idempotent. `invoices.activated_at` prevents repeated
activation from repeated callbacks. Gateway/provider failures are logged with
safe context and shown to users only as localized generic messages.

## SMS Gateways

The version `1.0.0` seed state keeps the system `local` SMS gateway enabled and
default. The seeded system `ippanel` gateway exists with masked credentials for
configuration continuity, but remains disabled and non-default until an
authorized admin explicitly enables it.

Runtime OTP delivery resolves the active default system SMS gateway first. IPPanel
may still be enabled for real delivery after credentials and operational policy
are confirmed.

## Data Model

Every tenant-owned table includes `tenant_id`. Visitor-owned tables include
`tenant_id` and `lead_id`.

Core tables:

- `tenants`, `tenant_domains`, `tenant_landing_page_visits`
- `leads`, `otp_codes`, `lead_notes`
- `users`, `platform_users`, `clinic_users`, `tenant_user`
- `uploads`
- `ai_providers`, `tenant_ai_settings`, `ai_model_accesses`,
  `clinic_ai_model_access`, `ai_model_usage_logs`
- `generation_groups`, `generation_jobs`, `generated_assets`
- `templates`, `tenant_theme_settings`
- `audit_logs`
- `roles`, `permissions`, `permission_role`
- `system_settings`
- `sms_gateways`, `payment_gateways`
- `plans`, `plan_service_entitlements`
- `invoices`, `payments`
- `subscriptions`, `subscription_service_entitlements`
- `credit_transactions`

Required invariants:

- `tenant_domains.domain` maps active hosts to active tenants.
- `leads` are unique by `tenant_id + phone_normalized`.
- `otp_codes` never contain a plain `code` column.
- `lead_notes` writes update CRM state transactionally.
- private file paths are internal metadata and are never exposed directly.
- dashboard upload previews stream private bytes through authorized routes.
- AI model logs never contain credentials, raw payloads, private paths, or user
  photos.
- feature migrations should be consolidated into the related migration while
  the schema is not production-migrated.

## Jalali Dates and Numbers

User-facing business dates use Jalali/Shamsi strings:

```text
YYYY/MM/DD HH:mm:ss
1405/01/31 23:59:59
```

Business date-time columns use `char(19)` so zero-padded values sort and filter
correctly. Current business date-time columns include CRM follow-up, invoice,
payment verification, subscription, and service entitlement date fields.

Laravel system timestamps remain Gregorian in storage:

- `created_at`, `updated_at`, `deleted_at`
- OTP expiry/verification timestamps
- queue/job timestamps
- technical logs and metadata

Use:

- `App\Support\NumberNormalizer`
- `App\Rules\SupportedDigits`
- `App\Support\JalaliDate`
- `App\Rules\JalaliDate`
- `App\Rules\JalaliDateTime`
- `<x-jalali-date />`
- `<x-jalali-date-time />`

Do not duplicate digit or calendar conversion logic.

## Admin Dashboard Standards

Dashboard UI is Persian RTL Blade with local assets only. Do not add React,
Vue, Inertia, Livewire, Next.js, Font Awesome, CDN CSS/JS/fonts/icons, external
SVG sprite sheets, or one-off dashboard icons.

Reusable components:

- `x-admin.icon`
- `x-admin.button`
- `x-admin.card`
- `x-admin.modal`
- `x-admin.badge`
- `x-admin.empty-state`
- `x-admin.validation-summary`
- `x-admin.list-filters`
- `x-admin.table.sortable-heading`
- `x-admin.table.pagination-footer`
- `x-admin.table.action-menu`
- `x-admin.table.action-item`
- `x-admin.mobile-list` components

Responsive strategy:

- below `1024px`: mobile/tablet mode
- from `1024px`: desktop mode

Important tables render desktop tables from `lg` upward and mobile cards below
`lg`. Backend search, filtering, sorting, pagination, policies, and middleware
remain authoritative.

The dashboard defaults to light theme. Dark mode is enabled only by persisted
browser choice in `localStorage` under `hairvision.admin.theme`.

Dashboard list pages should use backend query parameters, shared filters,
sortable headings, shared pagination footers, Jalali date controls, and
permission-aware row action menus at the RTL beginning/right side.
When a desktop table row has a permitted view/show action in its shared action
menu, clicking the row opens that view page and the row displays a pointer
cursor. Row action menu copy for show pages should use "view" instead of
"details".

## Localization

User-facing copy is Persian and belongs in `lang/fa`. Technical identifiers
such as route names, provider codes, status keys, permission codes, currencies,
and model keys remain stable English identifiers. Seeded display names should
be Persian where safe.

## Security and Error Handling

Primary risks:

- tenant data leakage
- OTP brute force or disclosure
- public exposure of private photos/assets
- UUID guessing for another lead's assets
- external resource injection through tenant themes
- accidental real provider calls
- credential, billing, CRM, and gateway data leakage

Controls:

- host-based tenant resolution
- tenant and lead scoping
- hashed, expiring, single-use OTPs with throttling
- private storage and scoped streaming
- encrypted credentials with masked display
- backend policies, `permission:*`, `active_plan`, and scoped services
- safe usage logs and gateway/provider logs
- generic localized user-facing errors

Catch operational failures at the controller/service/job/adapter boundary where
they can be handled safely. Log real exceptions with non-sensitive context.
Never expose OTPs, credentials, bearer tokens, private paths, raw provider or
gateway responses, uploaded payloads, SQL errors, or stack traces to users.

## Debug Logging

Use `App\Support\DebugStepLogger` for ordered local workflow tracing:

```php
DebugStepLogger::step($request, 'generation.store', 'Upload resolved', [
    'tenant_id' => $tenant->id,
    'lead_id' => $lead->id,
    'upload_uuid' => $upload->uuid,
]);
```

The logger must be silent when `APP_DEBUG=false`. Do not use raw `Log::debug()`
for ordered workflow traces unless the exception is documented. Log only safe
metadata.

## Coding Standards

- Follow Laravel conventions and PSR-12.
- Keep controllers thin and services/actions/jobs focused.
- Use Form Requests for validation and policies/gates for authorization.
- Use API Resources when serialization grows beyond simple arrays.
- Prefer dependency injection over service location for new code.
- Use config files for environment-based values.
- Do not build provider-specific database columns.
- Do not make synchronous real AI calls from HTTP lifecycle.
- Do not expose storage paths directly.
- Reuse shared support classes instead of duplicating formatting,
  normalization, pagination, filtering, date conversion, or permission logic.
- Add abstractions only when they remove real complexity or match local
  patterns.

PHPDoc should be useful, not ornamental. Add it for public classes/methods,
controllers, services, Form Requests, models, policies, jobs, middleware,
events/listeners, and complex protected/private methods. Include purpose,
important data shapes, side effects, exceptions, and `@since 1.0.0` where
version history is useful. Prefer native types where Laravel conventions allow.

## Local Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
npm run build
```

Local hosts:

```text
127.0.0.1 aria.hairvision-ai.test
127.0.0.1 hairvision-ai.test
```

Run:

```bash
php artisan serve --host=127.0.0.1 --port=8000
npm run dev
php artisan horizon
```

Demo users after seeding:

- Super admin: `09129999999`
- Clinic admin: `09217359491`
- Editor: `09120000001`
- Viewer: `09120000002`

Baseline configuration:

```env
APP_VERSION=1.0.0
APP_LOCALE=fa
APP_FAKER_LOCALE=fa_IR
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_CLIENT=predis
HAIRVISION_DEFAULT_AI_PROVIDER=sample_image
```

## Production Shape

```text
Nginx/Apache vhost
  -> Laravel public/index.php
  -> Laravel app
  -> MySQL/MariaDB
  -> Redis
  -> Horizon worker process
  -> storage/app/private
```

Production queue-heavy work uses:

```env
QUEUE_CONNECTION=redis
```

Run Horizon through Supervisor, systemd, or the hosting panel process manager.

## Performance

- Paginate lists and enforce `HAIRVISION_API_MAX_PER_PAGE`.
- Use tenant-scoped indexes for filters and reports.
- Eager load detail-page relationships intentionally.
- Keep public assets local and avoid loading dashboard code into public flows.
- Cache system settings and clear cache after updates.
- Keep management reports aggregate-based.
- Keep generation outside HTTP with Redis/Horizon outside tests.

## Testing

Required after behavior changes:

```bash
php artisan test
```

Run after CSS, JavaScript, Blade, font, or Vite changes:

```bash
npm run build
```

Add focused tests for changed behavior. Important coverage areas include tenant
isolation, OTP security, upload authorization, generation/provider behavior,
asset scoping, admin auth, roles/permissions, active-plan middleware, billing,
credits, AI model access, SMS/payment gateway security, Jalali dates, number
normalization, localization keys, shared dashboard components, and responsive
dashboard contracts.
