# Engineering Architecture

Version: [`2.0.0`](../CHANGELOG.md#200)

This document is the canonical engineering reference for HairVision. It
consolidates architecture, data model, setup, coding standards, frontend rules,
security, performance, debugging, and testing expectations.

## Version 2 AI Category Architecture

HairVision `2.0.0` moves AI service access from fixed config-only usage keys to
system-managed AI model categories. Categories such as `hair_transplant`,
`eyebrow_transplant`, and future keys like `filler_under_the_eyes` are
system-owned product capabilities. They are used consistently by plan service
credits, tenant entitlements, public profile categories, and AI model access
records.

Core concepts:

- `ai_model_categories`: system-owned usage categories, no `tenant_id`.
- `ai_model_category_outputs`: requestable outputs for a category, including
  the server-side prompt used for that output.
- `ai_models`: executable v2 model instances backed by a configured type.
- `ai_model_output_configs`: per-output model settings such as sample asset
  path, provider parameters, or output-specific adapter configuration.
- `lead_profiles` and `lead_source_images`: tenant/lead-scoped current source
  image with archived previous versions.
- `generation_requests` and `generation_attempts`: independent output requests
  and model execution attempts.

Configured model types remain code-owned in `config/ai-models.php`. The
database never creates provider types; it creates executable instances or legacy
access records that reference a configured type such as `sample_image` or
`gapgpt`.

The dashboard exposes category CRUD under system management. Creating an active
category immediately makes it available in plan service-credit inputs and AI
model access forms. For example, a system admin can create
`filler_under_the_eyes` with the Persian name `فیلر زیر چشم`, then grant credits
for it in a plan and create a `sample_image` or `gapgpt` model access for that
usage category.

The category dashboard is a table-based system screen with a dedicated category
icon and row action menu. Each row exposes view/edit plus exactly one lifecycle
action: unused categories can be deleted, while categories referenced by models,
plans, subscriptions, credit ledger rows, or generation requests can only be
archived. Model counts include both v2 `ai_models` and legacy
`ai_model_accesses`; output counts come from `ai_model_category_outputs`.
Category view includes the category summary, usage references, and the standard
outputs table. Output creation and editing happen in modals on the same view,
with output row actions, clear parent-category context, placeholders, and prompt
fields for each output. Output prompts are not exposed to public visitor APIs.

Plans assign one or more category codes through a clear multi-select UI but
carry one total credit amount.
Base plans, add-ons, and top-ups add to the active subscription's global credit
ledger. Generation consumes the selected output's `credit_cost` from that global
balance rather than from a service-specific bucket.

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

Clinic notifications use a system-authored message plus tenant delivery rows:
`clinic_notifications` stores the plain and sanitized rich-text message, type,
importance, optional visibility window, and targeting rules;
`clinic_notification_recipients` snapshots the recipient tenant and stores read
state. The system dashboard creates immediate or scheduled messages for all
clinics, one clinic, or selected clinics, then optionally narrows that selected
tenant set with filters such as active/inactive plan state, AI application
entitlement, or assigned AI model access. The clinic dashboard header reads
unread counts only from the current `adminTenant`, and opening a message records
the first reader and read time on that tenant delivery row. Clinic inbox lists
show metadata only; the message body is rendered after the tenant opens the
message.

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

- `two_weeks`: one day after the hair transplant (stable legacy key)
- `intermediate_regrowth`: six months after the hair transplant
- `final_result`: one year after the hair transplant

The staged flow dispatches the first pending job, then dispatches the next
stage only after the previous stage succeeds. This preserves the public result
order and ensures a failed stage stops later stages before they call a paid
provider. Each stage remains a separate job for failures, assets, logs, and
credit accounting.

## AI Provider Layer

Required provider flow:

```text
GenerationController
  -> GenerationService
  -> ServiceEntitlementService
  -> AiModelManager / AiModelResolver
  -> generation jobs
  -> RunAiModelAction
  -> AiModelResolver
  -> AiModelProviderInterface
  -> CreditLedgerService after success
  -> private generated asset
```

Provider selection and real provider credentials are server-side only. Clients
submit `upload_id`, not provider, tenant, model, or credentials. There is no
hard-coded application-wide provider. When a paid base plan or service add-on
is defined, the system administrator selects exactly one executable model
access for every included category. Invoice creation snapshots those ids, and
paid activation assigns exactly those models to the clinic. System
administrators may subsequently extend or override the clinic assignment
independently.

Configured model definitions live in `config/ai-models.php`; access records live
in `ai_model_accesses`; clinic assignments live in `clinic_ai_model_access`.
Only active assigned records for the required usage category may run. A clinic
may have multiple active assigned access records for the same category. Routing
uses the lowest numeric priority first while that model has queue capacity; when
all assigned models are at capacity, the resolver selects the model with the
lowest active queue load and then the lowest numeric priority.

Input-image preprocessing is a shared `RunAiModelAction` concern rather than a
provider implementation detail. Each model access can optionally enable it with
`input_image_processing_enabled`, `input_image_max_dimension`, and
`input_image_jpeg_quality`. When enabled, both numeric settings are required.
New model forms and every seeded model access default to enabled processing with
an exact 800-pixel longest side and JPEG quality 85. Administrators may still
override or explicitly disable input processing for an individual model.
`ApiImagePreprocessor` creates a private temporary JPEG, scales proportionally
so the longest side exactly matches the configured dimension, applies the
configured JPEG quality, and leaves the original source untouched. The shared
action replaces the provider payload path with this prepared file and removes
the temporary file after the synchronous provider call, including failed calls.
When disabled, the original private image path remains in the provider payload.

Provider results pass through the shared `JpegImageNormalizer` in both legacy
staged jobs and independent generation attempts. Before permanent private
storage, every valid result is proportionally scaled without cropping to an
exact 800-pixel longest side and encoded as JPEG at quality 85. Asset paths,
MIME, byte size, and checksums describe this normalized JPEG rather than the raw
provider response. The GapGPT request field `size=1024x1024` remains a separate
provider contract and does not change this private storage profile.

`sample_image` is a built-in local model type. Admins with model management
permissions can create, edit, and assign its access records like other model
types. It is eligible only when actively assigned to the clinic. It returns
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

GapGPT calls are long-running paid requests. Do not use automatic HTTP or queue
retries for the provider call itself. Keep the HTTP timeout, queued job timeout,
Horizon worker timeout, and Redis `retry_after` aligned so the worker waits for
the provider response instead of issuing a duplicate request.

Independent generation uses `ModelQueueCoordinator` as a database-backed FIFO
and capacity gate. Model selection, attempt creation, and clinic-credit
reservation occur inside one short transaction. Only the oldest queued attempts
up to `ai_models.concurrency_limit` receive a slot and enter the Laravel queue.
Workers atomically claim transported attempts, so duplicate queue delivery cannot
start a second provider call. A definite `429` response creates a delayed attempt
and model cooldown; ambiguous failures retain their credit reservation for
review. This path does not require Redis when the database queue is selected.

Every model execution log stores the owning `lead_id` when execution originates
from a visitor generation. Usage reporting is shared by the clinic credit report,
the system clinic detail, and the system model detail. Clinic queries are always
scoped by `clinic_id`; system model queries may span clinics. Tables show the
localized usage category and link the lead phone to the policy-protected lead
record without storing request images or private provider payloads in the log.

## Billing and Service Access

Public generation requires:

1. active base subscription whose purchased plan definition is still active
2. active service entitlement for the requested AI model category
3. active AI model category/application
4. enough remaining service credit for the requested output or staged flow
5. active assigned/eligible AI model access for the requested category and
   clinic
6. active executable model/output config for the requested output

`PublicApplicationAvailabilityService` is the shared runtime gate for public
landing bootstrap, verified profile categories, dashboard availability
summaries, and independent generation validation. It must hide applications
when the system admin disables the category, disables the purchased plan,
disables the global AI model access, disables the clinic model assignment, or
when no executable output model remains.

Plan types:

- `base_subscription`
- `service_add_on`
- `credit_top_up`

Service credits are stored in `subscription_service_entitlements`; all grants,
top-ups, uses, refunds, and adjustments are recorded in `credit_transactions`.
`balance_after` represents the total active subscription balance for the clinic.
Clinic billing screens show the active subscription summary. The separate
clinic report page `گزارش مصرف` under `گزارش‌های من` shows positive credit
grants individually and aggregate usage deductions by day for transparency.
Credit consumption uses a database transaction and row lock. Credit is consumed
only after provider execution succeeds.

Base-plan and service-add-on forms require exactly one explicit model selection
for every selected category. Each selected model must be active, executable,
belong to that category, and individually cover every active output. Checkout
revalidates the invoice-time model snapshot before redirecting to a gateway.
Paid activation creates the subscription entitlements, grants credit, and
assigns those exact model accesses in the same transaction. The operation is
idempotent; credit top-ups neither require nor change model assignments. Legacy
plans without an explicit mapping use the priority-based compatible fallback
until they are edited or backfilled.

Plan service keys are resolved through `ServiceCatalog`, which reads active
`ai_model_categories` when the v2 table exists and falls back to configured
service keys before migrations are available. This keeps deployments safe while
allowing new categories to be created from the dashboard without code changes.

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

IPPanel delivery performs at least three bounded attempts when the HTTP request
times out, throws, or receives a rejected/unsuccessful provider response. A
recovered attempt returns normally. Exhausted delivery writes one Laravel
warning with safe operational context such as gateway id, endpoint host,
attempt count, response status, and exception class; it excludes OTP values,
phone numbers, credentials, request payloads, and raw responses. Visitor and
dashboard callers receive only the shared localized generic message.

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
- `ai_model_categories`, `ai_model_category_outputs`, `ai_models`,
  `ai_model_output_configs`
- `lead_profiles`, `lead_source_images`
- `generation_requests`, `generation_attempts`
- `generation_groups`, `generation_jobs`, `generated_assets`
- `templates`, `tenant_theme_settings`
- `audit_logs`
- `roles`, `permissions`, `permission_role`
- `system_settings`
- `sms_gateways`, `payment_gateways`
- `plans`, `plan_service_entitlements`, `plan_ai_model_access`
- `invoices`, `payments`
- `subscriptions`, `subscription_service_entitlements`
- `credit_transactions`

Required invariants:

- `tenant_domains.domain` maps active hosts to active tenants.
- `leads` are unique by `tenant_id + phone_normalized`.
- Currency amounts use unsigned BIGINT columns: `plans.price`,
  `invoices.amount`, and `payments.amount`.
- `otp_codes` never contain a plain `code` column.
- `lead_notes` writes update CRM state transactionally.
- private file paths are internal metadata and are never exposed directly.
- dashboard upload previews stream private bytes through authorized routes.
- AI model logs never contain credentials, raw payloads, private paths, or user
  photos.
- feature migrations should be consolidated into the related migration while
  the schema is not production-migrated.

V1 generation tables remain for compatibility. V2 additive migrations must not
drop `ai_model_accesses`, `generation_groups`, or `generation_jobs` in the same
release that introduces category-based generation.

`tenant_ai_settings` is retained as legacy schema only and is not model-selection
authority. Runtime model selection requires an active `clinic_ai_model_access`
assignment created by an authorized system administrator.

Lead detail history is source-image-centric. Dashboard queries load each
tenant/lead-scoped `lead_source_images` record with its generation requests and
all generated asset versions, ordered newest source version first. The view
renders the source image and its results in one bounded history item; regenerated
assets remain under the same request with current/archive labels. The current
source is also rendered separately as the lead's primary image. Legacy
`generation_groups` follow the same presentation contract by pairing each group
with its original `upload`, jobs, and assets. Clinic and system users share this
view after the normal policy and tenant-scope checks.

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

Dashboard quantity and monetary inputs use text controls with numeric keyboards
so browsers accept Persian, Arabic, and Latin digits. The shared admin numeric
input module inserts thousands separators while typing and submits an
unformatted Latin value. `NormalizeNumericInputs` applies the same recursive
normalization before backend validation, including API requests and requests
made without JavaScript, while excluding credential, token, secret, merchant,
and password fields.

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
- `x-admin.breadcrumbs`
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

Every dashboard page rendered through `admin.layout` includes the reusable
`x-admin.breadcrumbs` component at the top of the main content area. The
component builds a Persian RTL, accessible breadcrumb trail from the active
route so system and clinic pages share one navigation contract without
duplicating breadcrumb markup in individual pages.

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

Allowed status and categorical state codes are code-owned in
`config/state-catalog.php`. The config maps stable technical values to
translation keys, while `App\Support\StateCatalog` supplies localized options,
allowed values, and safe labels to forms and validation. These catalogs are not
stored in `system_settings` and are not editable from the dashboard. Dynamic AI
categories remain database-owned, and model types remain owned by
`config/ai-models.php` rather than being duplicated in the state catalog.

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

Project-wide debug instrumentation is registered centrally through
`DebugStepServiceProvider` and `LogDebugSteps`. When `APP_DEBUG=true`, the app
records safe step traces for request lifecycle, route matching, database query
templates, Eloquent model lifecycle events, outgoing Laravel HTTP client
requests/responses, queue jobs, console commands, auth events, reported
exceptions, and framework-visible service/job side effects.
The logger must be silent when `APP_DEBUG=false`. Do not add per-file logger
imports to ordinary controllers, services, jobs, or models; extend the central
provider/middleware instrumentation instead. Do not use raw `Log::debug()` for
ordered workflow traces unless the exception is documented. Context is
sanitized by `DebugStepLogger` before logging, so credentials, OTP codes, bearer
tokens, sessions, cookies, raw payloads, authority values, storage/private
paths, uploads, and long values are redacted or truncated.

A thin legacy bridge remains under the Support namespace only for backward
compatibility. New application code should not import the logger directly
unless it is extending the central debug instrumentation layer.

Database query tracing logs SQL templates and binding counts, not binding
values. It is enabled by default in debug mode and can be tuned with:

```env
HAIRVISION_DEBUG_STEP_DATABASE_QUERIES=true
HAIRVISION_DEBUG_STEP_MODEL_EVENTS=true
HAIRVISION_DEBUG_STEP_SLOW_QUERY_MS=0
```

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

PHPDoc should be useful, not ornamental. Add it for all project-owned named
classes, interfaces, traits, enums, functions, and methods. Include purpose,
input/output types, important data shapes, side effects, exceptions, and the
actual introduction version (`@since 1.0.0` or `@since 2.0.0`). Prefer native
types where Laravel conventions allow, and enforce the contract with
`composer phpdoc:audit`.

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
127.0.0.1 arya.hairvision-ai.test
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
- Demo clinic admin (مهسا فرهمند): `09004666673`
- Demo clinic admin (کامران فضلی): `09127072327`

The seeded demo clinic uses the combined hair-and-eyebrow starter plan. Its
`hair_transplant` and `eyebrow_transplant` applications are both assigned to
active category-specific GapGPT model access records.

Baseline configuration:

```env
APP_VERSION=2.0.0
APP_LOCALE=fa
APP_FAKER_LOCALE=fa_IR
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_CLIENT=predis
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
REDIS_QUEUE_RETRY_AFTER=1200
HAIRVISION_QUEUE_WORKER_TIMEOUT=1020
```

Run Horizon through Supervisor, systemd, or the hosting panel process manager.

### Shared cPanel Queue Fallback

When cPanel cannot keep Horizon or `queue:work` alive as a persistent process,
use the bounded cron worker. Do not enable it while Horizon is running.

For a database-backed cPanel queue:

```env
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=1200
HAIRVISION_QUEUE_WORKER_TIMEOUT=1020
HAIRVISION_CPANEL_QUEUE_CRON_ENABLED=true
HAIRVISION_CPANEL_QUEUE_CONNECTION=database
HAIRVISION_CPANEL_QUEUE_NAMES=ai-generation,default
HAIRVISION_CPANEL_QUEUE_PROCESSES=3
HAIRVISION_CPANEL_QUEUE_WINDOW_SECONDS=58
HAIRVISION_CPANEL_QUEUE_SLEEP_SECONDS=1
HAIRVISION_CPANEL_QUEUE_OVERLAP_MINUTES=30
```

After caching the deployment configuration, add one cPanel Cron Job that runs
Laravel's scheduler every minute:

```cron
* * * * * /usr/local/bin/php /home/CPANEL_USER/path/to/artisan schedule:run >> /home/CPANEL_USER/path/to/storage/logs/cron.log 2>&1
```

Replace the PHP binary and project paths with the values shown by cPanel. Each
configured lane keeps one worker ready for 58 seconds and checks the queue every
second, so newly dispatched image jobs do not wait for the next full cron minute.
Start with one process and increase gradually after checking cPanel CPU/RAM
limits. Each lane has an independent overlap lock that remains valid longer than
a paid image request. The `jobs` / `failed_jobs` migrations must be present when
using the database queue; CLI PHP should provide `pcntl` so worker and job
timeouts are enforced reliably.

For an equivalent local macOS session, run
`./scripts/cpanel-queue-local` from the VS Code terminal. It starts Laravel
`schedule:work` with the database queue, generation coordinator, dedicated
`ai-generation,default` queue order, and three worker lanes without changing
the developer's `.env`. It clears the local config cache first so process-only
overrides cannot be shadowed. The `--once` mode mirrors one cPanel
`schedule:run` invocation and `--list` prints the events that would run.

## Performance

- Paginate lists and enforce `HAIRVISION_API_MAX_PER_PAGE`.
- Use tenant-scoped indexes for filters and reports.
- Eager load detail-page relationships intentionally.
- Keep public assets local and avoid loading dashboard code into public flows.
- Cache system settings and clear cache after updates.
- Runtime code must read impactful settings such as invoice due days and
  pagination limits from `SystemSettingService`, with safe config fallbacks.
- Read code-owned status options from `StateCatalog`; do not recreate editable
  database copies of the state catalog.
- Keep management reports aggregate-based.
- Keep generation outside HTTP with Redis/Horizon outside tests.

## Testing

Required after behavior changes:

```bash
php artisan test
composer phpdoc:audit
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
