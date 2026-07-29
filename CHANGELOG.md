# Release Notes

## Unreleased

Changes without an assigned release remain in this section and are moved into
the next dated SemVer section when that release is prepared.

### Deployment

- Added a checked-in cPanel Git deployment configuration that overwrites the
  matching contents of `public_html` from `public` and adjusts the deployed
  Laravel front controller to load the application from the cPanel repository.

### Clinic management fixes

- Added invoice view actions and row-click detail modals to the clinic detail
  page so invoice and latest payment information is directly accessible.
- Added permission-protected deletion of a clinic's dedicated SMS gateway from
  the clinic detail page.
- Moved clinic AI-model assignment activation/deactivation into a dedicated
  per-row quick action with status-appropriate green and red styling.

## 2.0.2 - 2026-07-28

### OpenAI image editing

- Added the code-owned `openai` AI model type and dashboard model definition
  alongside the existing local demo and GapGPT adapters.
- Added a dedicated OpenAI Images Edit adapter that sends encrypted API-key
  credentials as a Bearer token, uploads the private source as multipart
  `image[]`, uses the selected category output prompt, and decodes the returned
  `data[0].b64_json` image without exposing provider payloads.
- Added configurable API URL, model, `low`/`medium`/`high` quality, output size,
  output format, background, moderation, output compression, and provider
  timeouts. Defaults are `gpt-image-1.5`, `low`, `816x816`, `jpeg`, `opaque`,
  `auto`, one image, and compression `70`.
- Added category-specific seeded OpenAI model accesses for hair and eyebrow
  transplant applications. Seeded credentials remain non-production
  placeholders, clinic assignments remain inactive by default, and executable
  output configurations copy their prompt from the application output.
- Added Persian dashboard labels, select controls for constrained provider
  settings, server-side setting validation, adapter/registry/seed coverage, and
  OpenAI provider documentation.
- Updated runtime, environment-example, canonical documentation, and version
  metadata coverage to `2.0.2`.

## 2.0.1

HairVision `2.0.1` updates the administrator experience and includes all
changes completed through 2026-07-26.

### Admin theme and navigation

- Rebuilt both dashboard palettes around the logo's violet and teal endpoints.
  Light mode uses restrained brand-tinted neutrals, while dark mode keeps
  neutral charcoal surfaces with balanced violet/teal influence instead of a
  green- or navy-dominant cast.
- Applied an accessible darker violet-to-teal brand gradient to primary actions
  while retaining distinct semantic success, warning, danger, and information
  colors.
- Fixed persisted dark-mode rendering so the stored administrator preference is
  applied before dashboard styles load and every card, table, form, modal,
  filter, badge, menu, disabled state, and responsive surface follows the same
  theme source.
- Removed page-level Tailwind dark variants from the dashboard where they could
  follow the operating-system preference instead of the administrator's stored
  selection.
- Added light/dark logo switching and an accessible theme control to both
  dashboard authentication screens while retaining the existing controls in
  desktop and mobile dashboard navigation.
- Added cross-tab theme synchronization for an administrator changing the
  preference in another browser tab.
- Renamed the main navigation groups to «امنیت» and «مالی», moved every former
  «مدیریت سیستم» item under «تنظیمات», removed that redundant menu group, and
  shortened «قالب‌های لندینگ» to «قالب‌ها».
- Added distinct local icons for roles, permissions, AI applications, AI models,
  templates, finance, and each report type.
- Removed the separator and animated underlines from the administrator
  breadcrumb path.
- Introduced one canonical navigation catalog for sidebar groups, topbar
  context, and breadcrumb hierarchy so every route uses the same label and
  semantic icon.
- Aligned administrator URLs with the visible CRM, security, finance, reports,
  and settings hierarchy, while retaining permanent redirects for legacy
  section-root bookmarks and stable internal permission codes.
- Grouped permissions under the Persian menu headings, aligned their displayed
  names with menu terminology, renamed access levels to «نقش‌های مدیریتی», and
  displayed each role's Persian name beside its English key in user forms.
- Removed all internal breadcrumb block padding, standardized the main content
  inset to 20 pixels on every side and breakpoint, and corrected logical top
  corner rounding for first/last table header cells.
- Kept the visitor landing page, public templates, and tenant presentation
  unchanged.

### Clinic dashboard guide

- Added a clinic-only «مرکز راهنما» navigation group with distinct local icons
  and separate «راهنمای کاربری» and «پرسش‌های متداول» pages for clinic
  administrators, editors, and viewers.
- Added categorized top indexes, step-by-step clinic operational sections for
  CRM, visitor image generation, notifications, access control, billing,
  reporting, privacy, and troubleshooting, plus accessible native FAQ
  accordions with smooth open/close transitions.
- Kept the delivered Persian content focused on clinic users and excluded
  platform-management dashboard instructions.
- Kept the guide available when a clinic plan is inactive so employees can
  still reach billing, access, and troubleshooting instructions.
- Added dedicated `guide_pages`, `guide_sections`, `guide_steps`, and
  `guide_faqs` models/tables, with audited ownership and stable ordering; the
  migration preserves existing `guide.content` data before removing that
  legacy setting and the seeder never overwrites managed content.
- Split platform management into distinct guide and FAQ menu items/pages with
  different semantic icons, compact master-detail lists, single-record forms,
  easy per-section step creation, transactional ordering, and confirmed deletes.
- Made the reusable WYSIWYG editor functional by preserving selection across
  toolbar actions, adding pressed states and an inline safe-link form,
  sanitizing pasted/source HTML, synchronizing submission state, and validating
  visible rich-text content on both client and server.
- Fixed browser-generated Bold/Italic/Underline markup being discarded during
  synchronization, removed inherited Bold styling from editor content, reduced
  submission to one named textarea, and added a visible HTML fallback when the
  JavaScript enhancement cannot initialize.
- Added site-wide smooth anchor scrolling with target offsets and
  reduced-motion fallbacks.
- Fixed dashboard fragment navigation so anchor links scroll only the main
  content container, keep the topbar in place, and cannot reveal a phantom
  block below the fixed-height application shell.
- Hardened that fix globally for initial hashes, full reloads, back/forward
  restoration, modal toggles, and same-page links by fixing the admin body to
  the dynamic viewport and routing all fragment offsets through the main
  dashboard scroller.
- Added the `guide.manage` permission while keeping plain labels escaped and
  stripping executable HTML from every formatted field before storage/render.

### Quality

- Added regression coverage for authentication theme controls, both logo
  variants, persisted-theme boot behavior, logo-derived palettes, semantic
  utility coverage, menu grouping, distinct local icons, and line-free
  breadcrumbs.
- Audited every Blade view with the Tailwind CSS language service and replaced
  all 24 non-canonical utilities reported by VS Code; the final audit reports
  zero diagnostics.
- Added guide coverage for both clinic help pages, every clinic role, no-plan
  access, separate management authorization, relational idempotency,
  single-record CRUD and ordering, WYSIWYG behavior contracts, motion fallbacks,
  allowlisted HTML rendering, and unsafe markup removal.
- Rebuilt the versioned frontend assets and updated runtime, environment,
  documentation, and release metadata to `2.0.1`.

## 2.0.0

HairVision `2.0.0` starts the category-based AI model architecture and includes
all changes completed through 2026-07-24.

### Managed landing templates

#### Product

- Added system-level landing template management with separate «قالب‌ها» and
  «کلینیک‌ها» sections.
- Added create/edit flows for trusted header, supplemental body, and footer HTML
  fragments. Template CSS and JavaScript must be inline in those fragments;
  external scripts, stylesheets, and non-primary assets are rejected.
- Added one active default landing template and automatic assignment of that
  template to every clinic created through system management.
- Added explicit active-template assignment for every clinic while preserving
  legacy per-clinic theme data until an administrator activates a central
  template.
- Added template status, revision, assignment count, stable code, primary color,
  favicon, consent copy, and optional back-link management.

#### Architecture and Security

- Added additive template schema fields, a unique tenant-theme invariant,
  restrictive template deletion, editor attribution, revision tracking, and a
  central resolver that falls back safely when an assigned template is inactive.
- Kept existing seeded clinics, users, and legacy theme values unchanged.
- Limited template lifecycle and assignment actions to dedicated platform
  permissions registered additively for existing installations and included in
  the super-admin permission catalog.
- Kept tenant landing assets local or on the primary HairVision host while
  intentionally allowing trusted inline style and script blocks.

#### Fixes

- Removed conflicting static `hidden` plus `flex`/`grid` utility combinations
  from the public landing and now applies display utilities only when JavaScript
  reveals the affected elements.
- Corrected the landing-template sidebar translation key so the Persian
  «قالب‌های لندینگ» title is rendered instead of the untranslated key.
- Replaced the forced dark landing-template code fields with readable,
  theme-aware editors that explicitly control text, caret, selection, border,
  and focus contrast.
- Updated AI category and output deletion paths to use concrete paginator types
  and Eloquent static destroy operations, preserving the pending management fix
  that existed before this release work began.

#### Documentation and Quality

- Defined the canonical `Unreleased` to dated SemVer workflow across agent,
  architecture, business-rule, API, and release documentation.
- Updated runtime and environment metadata to `2.0.0`.
- Added feature coverage for template code validation, legacy preservation,
  default clinic activation, clinic assignment, authorization, effective public
  rendering, Bootstrap metadata, landing display-utility regression, menu
  translation, and theme-aware code-editor contrast.

### Release completion

#### Large monetary amounts

- Added an additive schema repair for existing installations so plan prices,
  invoice amounts, and payment amounts use unsigned BIGINT columns.
- Added regression coverage for Persian-grouped amounts above the 32-bit integer
  range across plan, invoice, and payment persistence.

#### Transplant output timelines and prompts

- Updated seeded hair-transplant outputs to represent one day, six months, and
  one year after treatment while preserving their stable technical keys.
- Updated the eyebrow output to represent the fully healed one-year result.
- Replaced all four seeded image-edit prompts and aligned their Persian titles,
  descriptions, captions, and fallback generation prompts.

#### AI image normalization defaults

- Changed seeded and new model input processing defaults to enabled, an exact
  800-pixel longest side, and JPEG quality 85 while preserving original uploads.
- Normalized generated images from both staged and independent generation paths
  to the same 800px/JPEG 85 profile before private storage.
- Added coverage for provider input dimensions, seeded settings, generated JPEG
  dimensions, MIME/path metadata, checksums, and quality encoding.

#### Local cPanel queue runner

- Added a macOS/VS Code runner for the same database-backed scheduler,
  coordinator, and worker lanes used by the cPanel cron deployment.
- Added watch, single-tick, and schedule-inspection modes without modifying the
  local `.env` file.

#### Lead-aware model usage reporting

- Linked new AI model usage logs directly to their verified lead.
- Added tenant-scoped model usage history to the clinic credit report and clinic
  detail.
- Added usage type and policy-protected lead links to system model usage
  history.
- Covered both legacy staged generation and independent generation logging with
  tests.

### Product

- AI model forms now provide optional input-image processing controls. When
  enabled, admins must define the longest-side dimension and JPEG quality.
- Dashboard create/edit forms now accept Persian, Arabic, and Latin digits in
  numeric fields, add thousands separators while typing, and persist normalized
  Latin values.
- System admins can manage AI model usage categories from the dashboard.
- System admins can activate or deactivate each AI application/category from
  the category list; inactive applications are hidden from every clinic landing
  page.
- The category dashboard now uses the standard table layout, a dedicated
  category icon, row-click view pages, inline output tables with add/edit
  modals, and lifecycle-safe archive/delete actions.
- Category outputs now require their own prompt; generation attempts snapshot
  and use the selected output prompt while public APIs keep prompts hidden.
- Active categories are available in plan service-credit forms.
- Plan create/edit forms present category assignment as clearer multi-select
  application options.
- Plan create/edit forms now use a focused single-select card for each
  application, require exactly one independently executable model per
  application, label each option as `display name (model key)`, order options by
  model priority, and show the persisted selection on plan details.
- Active categories are available in AI model access forms.
- Clinic model assignment dropdowns show every active model access and disable
  records that are already assigned to the clinic.
- Global hard-coded AI-provider configuration has been removed. Paid plan
  activation now assigns the exact executable model selected for each
  application by a system administrator when the plan was defined.
- Clinic dashboards now show landing application availability and explain when
  an application is hidden because a system admin disabled the category, the
  purchased plan, or the AI model used by that clinic.
- Clinic and system lead details now display version 2 source images, independent
  generation statuses, and generated results through tenant-scoped private
  image routes while retaining the legacy generation history.
- Clinic and system lead details now group processing history by upload: every
  source image appears beside all of its result versions, the newest upload
  remains the primary lead image, and legacy grouped generations include their
  original input image.
- System admins can send typed, importance-labeled rich-text notifications to
  all clinics, one clinic, or selected clinics, then narrow those recipients by
  active/inactive plan state, AI application, or assigned-model filters.
- The notification composer shows a live count of clinics matching the current
  selection and all active recipient filters.
- Notifications can be sent immediately without a display window or scheduled
  with explicit visibility dates.
- Clinic dashboards now include a persistent unread notification badge in the
  header and a tenant-scoped messages page with read tracking and a mark-all-read
  action. Message bodies stay hidden in the list until the clinic opens them.
- Newly created categories such as `filler_under_the_eyes` / `فیلر زیر چشم`
  can be used without a code deploy.
- `sample_image` and `gapgpt` can be selected for dynamically created
  categories, and seeded demo records are unprotected and editable by permitted
  system admins.

### Architecture

- The shared AI execution action can now create a proportional, private JPEG
  input for every provider, apply the model-specific size and quality, preserve
  the original upload, and remove the temporary file after provider execution.
- Added `ai_model_categories` and `ai_model_category_outputs`.
- Added executable v2 model tables: `ai_models` and
  `ai_model_output_configs`.
- Added public profile/source image tables and independent generation request
  tables.
- Added clinic notification and tenant delivery/read-state tables.
- Added schema-driven model type registry in `config/ai-models.php`.
- `ServiceCatalog` now reads active database categories and falls back to config
  before migrations are available.
- `AiModelResolver` now accepts database categories and wildcard model support.
- Clinics can keep multiple active AI model assignments for one usage category.
  Routing chooses the lowest numeric priority model while it has capacity, then
  falls back to the least-loaded model when all assigned models are busy.
- Public landing/profile category visibility is resolved by one shared
  availability service that checks active category, active purchased plan,
  active entitlement, active global model access, active clinic assignment, and
  executable output configuration.
- Added `plan_ai_model_access` for explicit plan-to-model mappings. New invoice
  metadata snapshots that mapping so later plan edits cannot alter an in-flight
  purchase. Base-plan and add-on checkout reject invalid or unavailable selected
  models before gateway handoff, and successful payment atomically provisions
  entitlements, credit, and those exact assignments.
- Plans now use one total credit amount with one or more assigned categories.
  Base plans, add-ons, and top-ups contribute to the active clinic subscription's
  global credit balance; output `credit_cost` is deducted from that total.
- The clinic credit timeline moved to the standalone `گزارش مصرف` page under
  `گزارش‌های من`, with individual grants/top-ups and daily aggregated usage
  deductions.
- System settings now use practical controls and affect invoice due days and
  pagination limits at runtime.
- Removed the editable database-backed status catalog from system settings.
  Stable state codes now live globally in `config/state-catalog.php`, localized
  through `StateCatalog`, while dynamic AI categories and model types retain
  their own database and `config/ai-models.php` sources.
- Zarinpal credentials can be updated securely from the payment-gateway panel;
  seeded placeholders are rejected before network requests, failed validation
  responses log only safe diagnostics, and reseeding preserves real credentials.
- Gateway handoff now uses `303 See Other`, guaranteeing that a payment started
  by POST opens Zarinpal's `StartPay` endpoint with a fresh GET request.
- Pending Zarinpal authorities are reused only for a short safe window; stale
  attempts are closed and replaced before redirecting to `StartPay`.
- Gateway callbacks now use a refresh-safe payment-result page with persisted
  success, failure, and pending states, masked authority values, transaction
  details, and an explicit retry action only for failed attempts.
- The payment-result UI now uses a compact receipt layout with a restrained
  status row, prominent amount/reference details, and at most two contextual
  actions instead of a large centered status panel.
- Zarinpal callback URLs are now generated per payment on the exact host where
  checkout started; legacy fixed callback values are ignored and removed on
  gateway updates to prevent verification bypass and cross-domain session loss.
- Empty `LOG_CHANNEL` and `LOG_STACK` values now fall back to the standard stack
  and single channels so logging failures do not hide billing gateway errors.
- Added an opt-in cPanel queue window that follows the configured Redis or
  database connection, waits only one second for new image jobs, aligns worker
  timeout with long-running providers, and prevents overlapping cron workers.
- Fixed the cPanel scheduler registration so the queue connection is passed as
  the command's positional argument instead of being misread as a connection
  named `connection=database`.
- Added a Redis-free database model coordinator with atomic per-model capacity,
  FIFO dispatch, stale reservation recovery, configurable parallel cPanel worker
  lanes, and a dedicated `ai-generation` queue.
- Independent requests now reserve clinic and provider-access credit before an
  external call, consume the reservation on success, and release it only for a
  definite failure or cancellation.
- Explicit provider `429` responses now respect bounded `Retry-After` cooldowns
  and create delayed attempts without automatically repeating ambiguous paid
  calls.
- Public landing startup now hydrates the current profile and generation history
  from the server and can keep polling while the visitor remains on the page.
- IPPanel SMS delivery now retries failed requests at least three times, logs
  one safe warning only after exhaustion, and returns a provider-agnostic
  generic error to visitor and dashboard callers.

### API

- Added public profile endpoints for current source image, categories, and
  generation history.
- Added independent output generation on `POST /api/v1/generations` while
  preserving the legacy staged upload flow.
- Added scoped asset stream alias: `GET /api/v1/assets/{uuid}/stream`.
- Added authenticated system APIs for type registry, categories, outputs, and
  v2 model instances.

### Quality

- Consolidated release history into this root `CHANGELOG.md`, removed the
  duplicate docs copy, and aligned runtime/configuration/documentation version
  references with `2.0.0`.
- Completed PHPDoc coverage for project-owned application, migration, factory,
  and seeder declarations, including descriptions, typed inputs/outputs, and
  declaration/change history through `@since`.
- Added a repeatable `composer phpdoc:audit` quality gate that rejects missing
  or duplicate declaration documentation.
- Tests cover conditional model-setting validation, proportional resizing,
  JPEG conversion, quality application, unchanged originals, provider payloads,
  and temporary-file cleanup.
- Tests cover grouped-number normalization, recursive request normalization,
  sensitive-field preservation, and the shared dashboard numeric-input
  contract.
- Tests cover config-backed state catalogs, translation completeness, enum
  alignment, and absence of legacy catalog rows from system settings.
- Tests cover dynamic category creation, category visibility in plans, plan
  service-credit persistence, category visibility in AI model forms, and
  creating a sample-image model access for a new category.
- Tests cover category archive/delete lifecycle rules and multiple active clinic
  model assignments routed by priority and queue capacity.
- Tests cover the cPanel scheduler command shape and prevent named syntax from
  being used for its positional queue-connection argument.
- Tests cover database FIFO capacity, slot handoff, atomic credit reservation,
  oversubscription prevention, configurable parallel cron lanes, and delayed
  rate-limit attempts.
- Tests cover IPPanel recovery on the third attempt, three-attempt exhaustion,
  safe Laravel logging, and generic public OTP errors.
- Tests cover system category deactivation, global model deactivation, and
  purchased plan deactivation hiding public applications and surfacing the
  reason in the clinic dashboard.
- Tests cover system-created clinic notifications, unread header counts,
  tenant-scoped read tracking, mark-all-read, selected-clinic delivery,
  inactive-plan targeting, and conditional recipient filters.
- Tests cover payment-gateway credential preservation, placeholder rejection,
  safe Zarinpal validation diagnostics, stale-authority replacement,
  access-controlled payment-result states, refresh safety, and empty log-channel
  fallback.
- Tests cover automatic multi-application model provisioning after successful
  payment, repeated-callback idempotency, add-on provisioning, and pre-payment
  rejection when no executable model is available.
- Tests cover plan-model create/update validation, exactly-one-model-per-category
  enforcement, category mismatch rejection, invoice snapshots, and assignment
  of the exact lower-priority model selected on a plan instead of an implicit
  global default.
- Tests cover multi-upload lead histories for clinic and system admins, including
  newest-primary ordering, per-source result grouping, and archived regeneration
  result versions.

## 1.0.0

HairVision `1.0.0` is the first complete release of the Laravel 13
multi-tenant SaaS for hair-transplant clinics.

### Product

- Tenant-hosted public visitor flow.
- Persian RTL Blade public UI with embedded Vazirmatn font.
- Tenant header, HairVision app body, tenant footer, and optional back-to-site
  link.
- Public OTP verification, private upload, staged generation, polling, result
  gallery, original upload preview, and zoomable assets.
- Landing page visit tracking before lead creation.

### Platform and Dashboard

- Admin dashboard under `/admin` with mobile OTP login.
- Separate public lead, clinic admin, and platform admin session cookies.
- Role-aware dashboards for clinic and platform contexts.
- Lead list/detail/create/edit, CRM follow-ups, private upload streaming, users,
  clinics, reports, settings, roles, permissions, gateways, billing, plans, and
  system management.
- Persian localization files under `lang/fa`.
- Default-light dashboard theme with persisted browser theme choice.
- Reusable Persian RTL dashboard breadcrumb component rendered above the main
  content for both system and clinic pages.
- Two-mode responsive strategy: mobile/tablet below `1024px`, desktop from
  `1024px`.
- Shared dashboard cards, badges, empty states, modals, list filters, sortable
  headings, pagination footers, action menus, and mobile list cards.
- Dashboard rows with permitted view actions open the view page on row click
  and use view wording for row action menus.
- Local UIcons regular-rounded dashboard icon surface through `x-admin.icon`.

### Tenancy and Security

- Host-based tenant resolution with unknown-host `404`.
- Tenant-scoped public and dashboard queries.
- Hashed, expiring, single-use, rate-limited OTP records.
- Private upload and generated asset storage.
- Scoped public asset streaming and authorized dashboard upload previews.
- Policy-backed dashboard authorization and `permission:*` route middleware.
- Active-plan middleware for normal-user operational routes.
- Encrypted SMS, payment, and AI model credentials with masked display.
- Seeded system SMS gateways use local SMS as the enabled default; IPPanel is
  seeded disabled and non-default until explicitly activated.
- Safe provider/gateway logging without OTPs, credentials, private paths, raw
  payloads, or stack traces.

### AI Generation

- Provider-agnostic AI access layer with config-backed model definitions.
- Protected local `sample_image` provider as the default provider.
- Local sample assets for `two_weeks`, `intermediate_regrowth`, and
  `final_result`.
- GapGPT image-edit provider behind encrypted AI model access records and clinic
  assignment.
- GapGPT multipart request shape with `image`, `model = gpt-image-2`, `prompt`,
  `n = 1`, `response_format = url`, and `size = 1024x1024`.
- Staged GapGPT generation runs one stage at a time with long provider
  timeouts and no automatic paid-request retry; failures stop later stages
  before they call the provider.
- AI model usage logs with safe metadata.
- Clinic AI model assignment enforcing one active access per usage category.

### Billing and Entitlements

- Plan types for base subscriptions, service add-ons, and credit top-ups.
- Service entitlement definitions on plans.
- Clinic subscriptions with service-level credit balances.
- Immutable credit ledger rows for grants, top-ups, use, refunds, and
  adjustments.
- Atomic service credit consumption after successful provider execution.
- Zarinpal sandbox payment adapter and deterministic local adapter for tests.
- Idempotent payment verification and one-time invoice activation.

### Engineering

- API-first `/api/v1` public endpoints.
- Redis Queue and Horizon production queue shape.
- Jalali/Shamsi business-date storage, validation, display, and filtering.
- Shared digit normalization for Persian, Arabic, and Latin digits.
- Shared pagination, search/filter, date range, dashboard overview, billing,
  entitlement, credit ledger, AI model, and debug logging utilities.
- Consolidated documentation set:
  - `CHANGELOG.md`
  - `docs/agent-instructions.md`
  - `docs/business-rules-fa.md`
  - `docs/architecture.md`
  - `docs/api.md`

### Quality

- Feature tests cover tenant resolution, unknown hosts, landing visits, OTP
  security, digit normalization, upload validation, generation, private assets,
  tenant isolation, dashboard auth, roles, permissions, billing, service
  entitlements, credit ledger, AI model access, GapGPT request shape, Jalali
  dates, localization keys, shared dashboard components, and responsive
  contracts.

Required release checks:

```bash
php artisan test
npm run build
```
