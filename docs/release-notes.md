# Release Notes

## 2.0.0

HairVision `2.0.0` starts the category-based AI model architecture.

### Product

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
- Active categories are available in AI model access forms.
- Clinic model assignment dropdowns show every active model access and disable
  records that are already assigned to the clinic.
- Clinic dashboards now show landing application availability and explain when
  an application is hidden because a system admin disabled the category, the
  purchased plan, or the AI model used by that clinic.
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
- Plans now use one total credit amount with one or more assigned categories.
  Base plans, add-ons, and top-ups contribute to the active clinic subscription's
  global credit balance; output `credit_cost` is deducted from that total.
- The clinic credit timeline moved to the standalone `گزارش مصرف` page under
  `گزارش‌های من`, with individual grants/top-ups and daily aggregated usage
  deductions.
- System settings now use practical controls and affect invoice due days and
  pagination limits at runtime.

### API

- Added public profile endpoints for current source image, categories, and
  generation history.
- Added independent output generation on `POST /api/v1/generations` while
  preserving the legacy staged upload flow.
- Added scoped asset stream alias: `GET /api/v1/assets/{uuid}/stream`.
- Added authenticated system APIs for type registry, categories, outputs, and
  v2 model instances.

### Quality

- Tests cover dynamic category creation, category visibility in plans, plan
  service-credit persistence, category visibility in AI model forms, and
  creating a sample-image model access for a new category.
- Tests cover category archive/delete lifecycle rules and multiple active clinic
  model assignments routed by priority and queue capacity.
- Tests cover system category deactivation, global model deactivation, and
  purchased plan deactivation hiding public applications and surfacing the
  reason in the clinic dashboard.
- Tests cover system-created clinic notifications, unread header counts,
  tenant-scoped read tracking, mark-all-read, selected-clinic delivery,
  inactive-plan targeting, and conditional recipient filters.

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
  - `docs/agent-instructions.md`
  - `docs/business-rules-fa.md`
  - `docs/architecture.md`
  - `docs/api.md`
  - `docs/release-notes.md`

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
