# Engineering Architecture

Version: [`2.1.0`](../CHANGELOG.md#210)

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
`gapgpt` or `openai`.

The fresh-install seed uses OpenAI exclusively for execution. Only the OpenAI
provider, category-specific access records, executable models, seeded-clinic
assignments, and base/add-on plan mappings are active. Seeded `sample_image` and
`gapgpt` records remain present but paused/inactive and are not attached to any
seeded plan. Credit top-ups continue to carry no model mapping.

The dashboard exposes category CRUD under system management. Creating an active
category immediately makes it available in plan service-credit inputs and AI
model access forms. For example, a system admin can create
`filler_under_the_eyes` with the Persian name `فیلر زیر چشم`, then grant credits
for it in a plan and create a `sample_image`, `gapgpt`, or `openai` model access
for that usage category. OpenAI-backed access records use the same category and
output boundaries.

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

## Managed Landing Templates

Landing templates are system-owned records. A system administrator edits their
header, optional body, and footer as HTML fragments, selects one active default,
and assigns active templates to clinics from the system dashboard. A clinic has
one `tenant_theme_settings` row; its `template_id` is the assignment and
`use_template_content` records whether centralized content has replaced its
legacy per-tenant markup.

`LandingTemplateResolver` is the single read boundary used by the public Blade
shell and `/api/v1/bootstrap`. It falls back to the active default when an
assignment is missing or inactive. New-clinic creation and default assignment
run in one database transaction. Existing tenant markup remains authoritative
until an administrator explicitly assigns a managed template.

Template HTML is trusted administrator-authored code. Validation accepts inline
`<style>` and `<script>` but rejects full documents, externally sourced scripts
or styles, unsafe schemes, embedded documents, and asset URLs outside the
primary HairVision domain. Public rendering must use only the resolved
presentation and must never accept tenant or template identity from visitor
input. The dashboard code fields use a dedicated theme-aware editor surface
with explicit foreground, background, caret, selection, border, and focus
contrast in both light and dark modes.

## Runtime Shape

Public visitor flow:

```text
Browser
  -> Persian RTL Blade shell
  -> fetch('/api/v1/...')
  -> API controller
  -> service/action
  -> Laravel database/Redis queue job
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
- MySQL database queue for shared cPanel; Redis/Predis/Horizon when a persistent
  process manager is available
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

The legacy staged flow creates and dispatches every eligible output as an
independent job after the creation transaction commits. Result presentation
keeps the configured output order, but execution order is not coupled: a failed
output does not cancel queued or processing siblings, and successful assets are
shown as soon as each becomes available. Each output keeps independent failure,
asset, timing, provider-reference, and credit-accounting state.

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

OpenAI image edits use a separate `OpenAiImageEditProvider` and the current
Images Edit multipart contract:

```text
POST <configured API URL>
Authorization: Bearer <encrypted access credential>
Accept: application/json
image[]=<private source file>
model=<configured model; seeded default gpt-image-2>
prompt=<selected category-output prompt snapshot>
quality=<low|medium|high; default low>
size=<configured output size; product default 816x816>
output_format=<jpeg|png|webp; default jpeg>
background=<opaque|transparent|auto; default opaque>
moderation=<auto|low; default auto>
n=1
output_compression=<0..100; default 70>
```

The adapter does not send `response_format`; GPT Image returns base64 image data
without that request field. It decodes `data[0].b64_json` and returns only image
bytes plus safe metadata to the shared JPEG normalizer. It never returns a
provider URL to the visitor. The product keeps `816x816` as the configurable seed default requested
for compatible endpoints; direct `api.openai.com` installations must select a
size currently accepted by the upstream API (`auto`, `1024x1024`, `1536x1024`,
or `1024x1536`).

GapGPT and OpenAI calls are long-running paid requests. Do not use automatic
HTTP or queue retries for a delivered or ambiguous provider call. OpenAI
transport failures that prove HTTP delivery never occurred—DNS resolution,
TCP refusal, or an explicit connect-stage timeout (including libcurl's
`Failed to connect ... Timeout was reached` wording)—may create a bounded delayed
generation attempt with exponential backoff and jitter. Keep the HTTP timeout,
queued job timeout, Horizon worker timeout, and Redis `retry_after` aligned so
the worker waits for every delivered provider response instead of issuing a
duplicate request.

Independent generation uses `ModelQueueCoordinator` as a database-backed FIFO
and capacity gate. Model selection, attempt creation, and clinic-credit
reservation occur inside one short transaction. Only the oldest queued attempts
up to `ai_models.concurrency_limit` receive a slot and enter the Laravel queue.
Workers atomically claim transported attempts, so duplicate queue delivery cannot
start a second provider call. A definite `429` response creates a delayed attempt
and model cooldown; ambiguous failures retain their credit reservation for
review. This path does not require Redis when the database queue is selected.

## Image Queue Runtime

The cPanel-compatible baseline is Laravel Database Queue on MySQL plus the
existing one-minute Laravel Scheduler cron. HTTP creation performs only short
database transactions and returns `202 Accepted`; image bytes and provider
responses are never serialized into queue payloads. Both legacy and independent
jobs dispatch after commit and carry model identifiers only.

`hairvision:workers:ensure` is the scheduler watchdog. In `detached` mode it
owns a fixed set of numbered slots in `image_worker_processes`, verifies both a
recent heartbeat and PID command ownership, preserves healthy processes, and
replaces only missing/dead/stale processes. Workers recycle after configured
time, job, or memory limits and may be restarted safely with
`hairvision:workers:restart`. When cPanel forbids detached PHP processes, set
`IMAGE_WORKER_MODE=window`; the same scheduler creates non-overlapping
background lanes with a configured ceiling of 58 seconds. Each lane is bounded
to the time remaining in its current cron minute so a late scheduler start
cannot retain the overlap lock into the next tick and skip a full minute.

Worker count and provider capacity are separate controls. Every provider call
must first acquire an expiring row from the global `image_provider_leases`
semaphore. A full semaphore releases the transported job for a short jittered
delay instead of occupying a provider slot. Expired leases recover capacity
after crashes. Start with three workers and three provider slots only when the
provider and cPanel account both permit that concurrency.

There is no per-category, per-output, per-lead, or per-tenant processing mutex.
Every generation request and output owns independent status, attempt, credit,
error, and asset records. Fail/cancel/retry decisions may affect only that
request (apart from the explicit same-source replacement rule); they must never
propagate to another output or another user's request. Requests share only the
bounded worker/model/provider capacity, so saturation changes queue wait—not
another request's correctness or terminal state.

The timeout contract is strict:

```text
provider request timeout (1000s) < job timeout (1010s) < worker timeout (1020s) < queue retry_after (1200s)
```

The dashboard does not impose a fixed maximum on the model response timeout.
If an administrator selects a value above the deployment default, operations
must increase the worker timeout and queue `retry_after` in the same order; job
timeouts are derived ten seconds below the configured worker timeout.

An explicit, definite rejection such as `429` is retried using bounded
Retry-After/backoff and a delayed coordinator wake-up. OpenAI cURL errors `6`
and `7`, plus cURL `28` only when its message is explicitly `Connection timed
out`, create a separate delayed attempt because no HTTP delivery occurred. An
ambiguous response/processing timeout after `provider_call_started_at` is not
repeated automatically. Provider 4xx, 5xx, timeout, invalid-image, queue, and
review outcomes use safe error codes. Production logs correlate generation,
attempt, model, worker, queue wait, provider latency, endpoint host/path,
timeout settings, HTTP status/request id, cURL code, retry decision, and a
bounded redacted exception chain; they never contain secrets, prompts, private
paths, raw payloads/responses, or stack traces.

`hairvision:generations:reconcile` safely requeues work that never entered a
provider call, accepts a valid already-written asset, and flags ambiguous
post-call records for review. `hairvision:generations:cleanup` removes only old,
archived, non-current generated assets that have no active attempt; `--dry-run`
previews the selection. `hairvision:queue-health --json` reports queue depth,
oldest wait, worker/lease state, timing averages, failed/stuck records, private
storage, cache locks, process primitives, and the timeout contract.

The browser stores only generation UUID/resume-token pairs in `sessionStorage`.
The raw token is also scoped to the verified server session while only its
SHA-256 digest is persisted. Polling starts immediately after `202`, uses one
adaptive loop with jitter, has no global terminal timeout, slows while hidden,
and wakes immediately on focus, visibility, or online events. A generation is
terminal only when every requested output is terminal; one failure cannot hide
already-ready sibling assets. Status calls for multiple active requests run
concurrently with `allSettled`, so one user's/output's transient API failure does
not prevent successful siblings from updating in the same polling cycle.
An ambiguous browser-side creation failure, including a server `5xx` after
persistence, is retried once with the same idempotency key and then reconciled
against the lead's persisted generations.
The public UI reports a temporary synchronization state instead of a terminal
generation failure when neither response can be observed.

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

The dashboard invoice and payment routes remain available without an active
subscription so an inactive clinic can review and pay its invoices. Their
active-subscription summary is rendered only for clinic-scoped users; system-
scoped management users never receive that summary, regardless of the request
host or selected tenant context.

## SMS Gateways

The current seed state keeps the system `local` debug/test SMS gateway enabled,
default, and marked `is_protected`. Policy and management-service checks deny
updates and deletion even for a super administrator, and the dashboard exposes
that protected state without edit/delete actions. A second system-local gateway
cannot be created while this protected definition exists.

The seeded system `ippanel` gateway remains disabled and non-default until an
authorized admin explicitly enables it. No tenant-owned SMS gateway is seeded
for the demo clinic. The additive SMS protection migration removes only the
exact legacy demo-clinic row named `سیستم پیامک کلینیک دمو`; customized or
renamed clinic gateway records are preserved.

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
- `templates` (system-owned landing code, lifecycle, default and revision),
  `tenant_theme_settings` (one template assignment per tenant)
- `audit_logs`
- `roles`, `permissions`, `permission_role`
- `system_settings`
- `guide_pages`, `guide_sections`, `guide_steps`, `guide_faqs` (system-owned
  clinic help content with independent ordering and audit ownership)
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
- `Plan` stores `price` as a numeric string at the Eloquent write boundary so
  PDO does not bind large unsigned values as `PDO::PARAM_INT`; its accessor
  exposes the unchanged value as a PHP integer. Application writes to
  `plans.price` must continue to pass through this model boundary, while any
  future raw query-builder write must bind the price as a numeric string.
- Fresh databases receive those final monetary types directly from the
  system-management creator migration. Guarded additive migrations repair older
  schemas and verify the resulting column types before seeding can continue.
  The latest enforcement uses a new migration identity so servers that already
  recorded an earlier repair still recheck their live monetary definitions.
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

The lead page also builds one presentation-only image sequence from those
already authorized relationships. Upload rounds are ordered newest first, with
each source followed by its generated versions; legacy uploads follow the same
rule. Visible thumbnails open the matching item in an accessible dashboard
gallery with captions, keyboard controls, and touch swiping. Gallery URLs remain
the existing tenant- and lead-scoped private stream routes and never expose
storage paths.

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

Production Vite builds use content-addressed filenames. JavaScript, stylesheets,
fonts, images, and other build assets change their filename only when their own
content hash changes, so unchanged fonts and images keep long-lived cache keys
across releases. Laravel separately appends `?v={APP_VERSION}` to every URL
resolved through Vite, allowing a release-version change to invalidate rendered
entrypoint URLs without coupling the version number to immutable filenames. The
application Vite binding classifies the parsed URL path, rather than the full URL,
so dotted version query strings cannot cause stylesheets to be rendered as module
scripts or script preloads.

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
browser choice in `localStorage` under `hairvision.admin.theme`. The inline
theme bootstrap applies that value before the Vite stylesheet loads, and
`data-admin-theme` is the single styling authority; page-level Tailwind dark
variants must not compete with the persisted choice. The administrator login
and OTP screens expose the same theme control and render both logo variants.
Both dashboard themes derive their restrained brand accents from the logo's
violet `#8B5CF6` and teal `#14B8A6` endpoints. Dark dashboard surfaces stay
neutral charcoal rather than green, gray, or navy dominant; primary actions use
an accessible darker violet-to-teal gradient while semantic status colors
remain distinct. Shared tokens cover backgrounds, typography, borders, focus,
forms, tables, menus, modals, badges, and status colors. These tokens remain
scoped to `.admin-shell` and `.admin-auth-shell` so public landing pages and
managed landing templates are unchanged.

Permission-aware dashboard navigation is grouped into dashboard, CRM,
security, finance, reports, help, and settings. Platform management destinations
including clinics, templates, system notifications, AI applications, AI
models, SMS gateways, and runtime settings belong to the settings group; a
separate system-management menu group must not be rendered. Roles, permissions,
AI applications, AI models, and each report type use distinct semantic aliases
backed by the local UIcons font.

The clinic-only `مرکز راهنما` group uses a life-ring icon and has two children:
`راهنمای کاربری` with a reader icon and `پرسش‌های متداول` with a question
message icon. They lead every authenticated clinic role to separate shared help
pages. Guide access is exempt from active-plan blocking so billing, access, and
troubleshooting instructions remain available when a subscription is inactive.
Platform-management destinations and instructions are not included in these
clinic-user help surfaces.

`App\Support\AdminNavigation` is the single metadata source for sidebar groups,
menu items, semantic icons, active-route patterns, topbar context, and
breadcrumbs. Canonical dashboard web paths mirror that hierarchy:

```text
/admin/crm/leads
/admin/security/users
/admin/security/roles
/admin/security/permissions
/admin/finance/plans
/admin/finance/billing
/admin/finance/payment-gateways
/admin/reports/performance
/admin/reports/credit-usage
/admin/reports/management
/admin/help/guide
/admin/help/faq
/admin/settings/clinics
/admin/settings/templates
/admin/settings/notifications
/admin/settings/ai-applications
/admin/settings/ai-models
/admin/settings/sms-gateways
/admin/settings/general
/admin/settings/guide
/admin/settings/faqs
```

Stable route names and permission codes remain backward-compatible internal
identifiers. Legacy section-root URLs permanently redirect to their canonical
hierarchical path. Permission presentation is grouped by the same CRM,
security, finance, reports, and settings headings, with canonical Persian names
shown beside stable English codes.

### System-managed clinic guide

The clinic guide is system-owned relational content, not tenant content and not
public visitor content. `GuideContentService` reads and mutates four dedicated
models so one management action cannot overwrite unrelated help content:

```text
guide_pages (guide and faq page metadata)
  guide_sections (ordered guide categories)
    guide_steps (ordered actionable steps)
  guide_faqs (ordered questions on the faq page)
```

`GET /admin/help/guide` and `GET /admin/help/faq` are available to authenticated
dashboard users without a guide-specific view permission or active plan. The
first page renders a categorized anchor index and ordered clinic-user steps.
The second page renders a question index and native `details/summary`
accordions. Both pages link to each other.

`/admin/settings/guide` and `/admin/settings/faqs` are separate management
pages and both require `guide.manage`. Small Form Requests and thin controllers
delegate page, section, step, FAQ, and transactional adjacent-order operations
to `GuideContentService`. The master-detail UI renders compact lists and loads
the full editor only for the selected item; forms are never nested. A migration
imports any existing `guide.content` JSON into these records before deleting the
legacy setting, and rollback exports a compatible document.

Titles, audiences, step titles, and questions remain escaped plain text.
Introduction, section descriptions, step bodies, and FAQ answers use a reusable
visual/source WYSIWYG editor. It preserves the selected range across toolbar
actions, exposes pressed states, provides an inline safe-link control, sanitizes
paste and source transitions, converts browser-generated inline styles to
semantic allowlisted elements, and synchronizes one named submitted textarea.
That textarea stays visible as a progressive no-JavaScript fallback and is
hidden only after successful editor initialization. The
service passes rich text through `SimpleRichTextSanitizer` before persistence.
Only the small local formatting allowlist and safe `http(s)`, `mailto`, `tel`,
fragment, and root-relative links survive; executable markup, event attributes,
embedded content, and unsafe schemes do not.

Ordinary documents use CSS smooth scrolling and logical target offsets. The
fixed-height administrator app shell marks its root as `admin-root`, fixes the
body to the dynamic viewport, clips root document scrolling, and delegates
anchor movement exclusively to `[data-admin-scroll-container]`. An early head
guard temporarily suppresses native initial-hash scrolling; the shared fragment
controller then restores the URL and calculates the target offset inside the
content scroller. Root offsets are reset for direct links, reload, hash changes,
page restoration, and modal state changes, so fragment navigation cannot
displace the topbar or reveal layout below the shell. Triggerless global modals use a
`display: contents` wrapper and therefore add no phantom block after the shell.
The FAQ open/close panel and caret use short CSS transitions while native
details semantics remain intact. All scrolling and accordion motion is
effectively disabled when the operating system reports
`prefers-reduced-motion: reduce`.

Every dashboard page rendered through `admin.layout` includes the reusable
`x-admin.breadcrumbs` component at the top of the main content area. The
component builds a Persian RTL, accessible breadcrumb trail from the active
route so system and clinic pages share one navigation contract without
duplicating breadcrumb markup in individual pages. Breadcrumbs do not render a
bottom separator, animated link underlines, or internal block padding. Group and
item icons are resolved from the same navigation metadata as the sidebar.

The scrollable `.admin-content` area uses a consistent `20px` inset on every
side and breakpoint. Desktop table header cells own their semantic surface;
the first and last `th` receive logical top corner radii so RTL and LTR table
edges remain clipped to the rounded table container.

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

`TenantSeeder` derives the platform domain from the host portion of `APP_URL`.
The seeded clinic hosts prepend their tenant slug to that host, and reseeding
updates the same seeded domain mappings when `APP_URL` changes.

Run:

```bash
php artisan serve --host=127.0.0.1 --port=8000
npm run dev
php artisan horizon
```

Demo users after seeding:

- Super admin: `09124248164`
- Clinic admin: `09217359491`
- Editor: `09120000001`
- Viewer: `09120000002`
- Demo clinic admin (مهسا فرهمند): `09004666673`
- Demo clinic admin (کامران فضلی): `09127072327`

The seeded clinics use category-specific OpenAI model access records for every
application included in their plans. All seeded base-plan and add-on mappings
select OpenAI, and only OpenAI clinic assignments are active. The local sample
and GapGPT records remain seeded for explicit administrator use but are globally
inactive, paused at the executable-model layer, have no seeded clinic-assignment
pivot, and are absent from seeded plan mappings. Seeded provider credentials
remain non-production placeholders. `SubscriptionSeeder` re-applies the
invoice-time model snapshot even when a seeded subscription already exists, so
reseeded active clinic assignments exactly match the OpenAI models selected by
the current seeded plan.

Baseline configuration:

```env
APP_VERSION=2.1.0
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
  -> Laravel database queue + Scheduler watchdog
  -> detached PHP CLI workers (or bounded cPanel windows)
  -> storage/app/private
```

The shared-cPanel production baseline uses:

```env
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=1200
HAIRVISION_CPANEL_QUEUE_CRON_ENABLED=true
IMAGE_QUEUE_CONNECTION=database
IMAGE_QUEUE_NAME=ai-generation
IMAGE_WORKER_MODE=detached
IMAGE_WORKER_COUNT=2
IMAGE_WORKER_TIMEOUT=1020
IMAGE_PROVIDER_MAX_CONCURRENCY=2
IMAGE_PROVIDER_CONNECT_TIMEOUT=50
IMAGE_PROVIDER_REQUEST_TIMEOUT=1000
HAIRVISION_OPENAI_CONCURRENCY_LIMIT=2
HAIRVISION_OPENAI_CONNECTION_RETRIES=2
HAIRVISION_OPENAI_CONNECTION_RETRY_DELAY_SECONDS=2
HAIRVISION_OPENAI_CONNECTION_RETRY_MAX_DELAY_SECONDS=20
HAIRVISION_OPENAI_CONNECTION_RETRY_JITTER_SECONDS=2
```

Redis/Horizon remains a compatible upgrade path. Use it only when Supervisor,
systemd, or the hosting panel can keep Horizon alive; switch the queue/cache
drivers, preserve the timeout hierarchy and provider semaphore, disable the
cPanel worker fallback, and terminate the database worker pool before cutover.

### cPanel Public Webroot Deployment

The checked-in `.cpanel.yml` follows cPanel Git deployment conventions. It
copies `public/.` recursively and forcibly to `/home/hairvision/public_html/`,
including hidden public files such as `.htaccess`, so matching deployed files
are overwritten. It intentionally does not delete unrelated files that exist
only in `public_html`.

After the copy, deployment adjusts the copied `index.php` so its maintenance,
Composer autoloader, and Laravel bootstrap paths resolve through
`/home/hairvision/repositories/hairvision-ai/`. The repository version of
`public/index.php` keeps Laravel's standard relative paths for local and other
deployment environments.

### Shared cPanel Queue Fallback

Keep the existing scheduler cron. It runs the worker watchdog, model queue
coordinator, generation reconciler, cleanup schedule, and health snapshot. Do
not add one `queue:work --once` cron line per minute and do not run this worker
pool alongside Horizon.

`.env.cpanel.example` is the checked-in, secret-free production baseline for
this deployment shape. Copy it to the repository-root `.env` on the server,
replace its domain and cPanel-prefixed database placeholders when the target
account differs, fill blank credentials only on the server, and generate a new
`APP_KEY` only for a fresh installation. The local `.env` and
`.env.example` remain development baselines. Real `.env.cpanel` files are
ignored by Git.

For a database-backed cPanel queue:

```env
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=1200
HAIRVISION_CPANEL_QUEUE_CRON_ENABLED=true
IMAGE_QUEUE_CONNECTION=database
IMAGE_QUEUE_NAME=ai-generation
IMAGE_WORKER_MODE=detached
IMAGE_WORKER_COUNT=2
IMAGE_WORKER_TIMEOUT=1020
IMAGE_WORKER_TRIES=3
IMAGE_WORKER_MAX_JOBS=250
IMAGE_WORKER_MAX_TIME=3600
IMAGE_WORKER_MEMORY=256
IMAGE_WORKER_STALE_SECONDS=1080
IMAGE_PROVIDER_MAX_CONCURRENCY=2
IMAGE_PROVIDER_CONNECT_TIMEOUT=50
IMAGE_PROVIDER_REQUEST_TIMEOUT=1000
HAIRVISION_OPENAI_CONCURRENCY_LIMIT=2
HAIRVISION_OPENAI_CONNECTION_RETRIES=2
HAIRVISION_OPENAI_CONNECTION_RETRY_DELAY_SECONDS=2
HAIRVISION_OPENAI_CONNECTION_RETRY_MAX_DELAY_SECONDS=20
HAIRVISION_OPENAI_CONNECTION_RETRY_JITTER_SECONDS=2
IMAGE_PROVIDER_CAPACITY_DELAY_MIN_SECONDS=3
IMAGE_PROVIDER_CAPACITY_DELAY_MAX_SECONDS=8
```

After caching the deployment configuration, add one cPanel Cron Job that runs
Laravel's scheduler every minute:

```cron
* * * * * /usr/local/bin/php /home/hairvision/repositories/hairvision-ai/artisan schedule:run >> /home/hairvision/repositories/hairvision-ai/storage/logs/cron.log 2>&1
```

The cron path above is the current production path and must remain unchanged.
The watchdog attempts detached workers first. If `proc_open`, shell detachment,
or long-lived PHP CLI is unavailable, change only `IMAGE_WORKER_MODE=window`;
the scheduler then runs non-overlapping lanes up to 58 seconds while shortening
late starts to end inside the current cron minute.
The `jobs` / `failed_jobs` migrations must exist. `pcntl` is preferred for
graceful signal handling, while heartbeat expiry and PID ownership checks remain
the crash-recovery authority.

Deployment/restart sequence:

```bash
cd /home/hairvision/repositories/hairvision-ai
/usr/local/bin/php artisan migrate --force
/usr/local/bin/php artisan optimize:clear
/usr/local/bin/php artisan config:cache
/usr/local/bin/php artisan hairvision:workers:restart
/usr/local/bin/php artisan hairvision:workers:ensure
/usr/local/bin/php artisan hairvision:queue-health --json
```

Rollback code only after stopping/restarting the worker pool. The additive
runtime migration may remain during a code rollback; do not run `migrate:rollback`
while new jobs or workers exist. If schema rollback is explicitly required,
drain the queue, stop workers, back up MySQL and private storage, roll back the
single migration, deploy the compatible code, and restore workers.

Local macOS/Linux uses the same scheduler/watchdog architecture:

```bash
./scripts/hairvision-cron install
./scripts/hairvision-cron status
php artisan hairvision:queue-health --json
```

The installer discovers the real project directory and `php` binary, prevents
duplicate crontab entries, enables detached mode for that cron process, and logs
to `storage/logs/cron-local.log`. Use `./scripts/hairvision-cron uninstall` to
remove only its marked entry. `./scripts/cpanel-queue-local` remains an
interactive foreground/window diagnostic helper.

Operational checks and the opt-in fake-provider load test:

```bash
php artisan hairvision:generations:reconcile
php artisan hairvision:generations:cleanup --dry-run
php artisan hairvision:generation-load-test --batches=1,5,10,20 --min-delay-ms=20000 --max-delay-ms=30000
```

Scale `IMAGE_WORKER_COUNT` for queue throughput and
`IMAGE_PROVIDER_MAX_CONCURRENCY` only for permitted provider parallelism. With
mean provider latency `L` seconds and provider capacity `C`, the provider-bound
ceiling is approximately `C / L` generations per second; additional workers
reduce transport wait but cannot exceed the semaphore. Compare average/P95
queue wait, oldest queued age, error rate, memory and process count before and
after each one-step increase.

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
- Keep generation outside HTTP; use the database queue/watchdog baseline on
  shared cPanel and Redis/Horizon only where persistent process supervision is
  available.

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

## Release and Versioning Workflow

Every change starts in `CHANGELOG.md` under `Unreleased`. Work without an
assigned release stays there; it is not silently added to an already published
version. When a release is prepared, its entries move to a new SemVer section
and the application metadata plus canonical documentation versions are updated
together.

A release section must account for behavior changes, fixes, migrations, tests,
documentation, and relevant uncommitted work included in the final source
state. The release is ready only after migrations are evaluated, the full PHP
test suite and PHPDoc audit pass, and the frontend build passes whenever public
or dashboard assets changed.
