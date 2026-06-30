# Release Notes

## Unreleased

### Added

- Grouped, icon-enhanced Persian admin sidebar with active group/item states.
- Persistent default-light dashboard theme with a per-browser light/dark toggle.
- Role-aware admin overview so clinic users see the clinic dashboard and system
  managers see the management overview from the default dashboard entry.
- Full user management CRUD routes, Form Requests, policy checks, scoped search,
  clinic filtering, Persian role/status labels, and permission-aware actions.
- Complete system and private SMS gateway CRUD with list/search/view/create/edit
  and delete flows, masked credential display, scoped private ownership checks,
  and credential-preserving updates.
- Plan-aware locked menu state for normal users without active subscription
  access.
- `active_plan` middleware and `PlanAccessService` for inactive, expired, and
  credit-finished plan states.
- Persian localization files under `lang/fa` for dashboard, menu, auth, plans,
  payments, leads, CRM, SMS, settings, permissions, reports, messages, and
  validation.
- Dedicated CRM follow-up routes, Form Requests, service, timeline UI, channels,
  next follow-up date, and completion state.
- Authorized admin upload streaming route for private lead images.
- Plan definition deletion route for authorized plan managers.
- Tests for locked menus, plan route blocking, plan management permissions,
  purchase/upgrade behavior, CRM timeline isolation, upload authorization,
  Super Admin clinic context, normal-user dashboard scoping, and language keys.
- `App\Support\NumberNormalizer` for shared Persian/Arabic/Latin digit
  normalization.
- `App\Rules\SupportedDigits` for reusable digit-only Form Request validation.
- `App\Support\Query\SearchFilter` for shared dashboard table search behavior.
- `App\Services\Admin\SystemOverviewService` for shared system dashboard and
  management report metrics.
- Shared admin Blade components for cards, badges, empty states, modal create
  forms, validation summaries, and table row action menus.
- Shared admin button component for dashboard button/link actions with a central
  pointer cursor style.
- Frontend Blade standards and testing documentation.
- Shared dashboard table search/filter component behavior with limited primary
  filters, advanced filter dialog, active filter chips, per-filter clearing,
  sortable heading links, active sort icons, and table pagination footers.
- Config-backed AI model definitions and usage categories, including the
  protected `sample_image` demo model for `کاشت مو`.
- Admin AI model access management under `/admin/ai-models` with encrypted
  credentials, prompts, active status, credit limits, protected/demo flags,
  backend filters, masked credential display, and usage logs.
- Clinic AI model assignment under `/admin/clinics/{clinic}/ai-models`.
- `AiModelResolver`, `AiModelManager`, `RunAiModelAction`,
  `AssignAiModelToClinicAction`, and `AiModelProviderInterface` for separated
  AI model access logic.
- SaaS plan product types for base subscriptions, service add-ons, and credit
  top-up packages.
- Plan service entitlement definitions, clinic subscription service
  entitlements, and credit transaction ledger rows.
- `PlanCatalogService`, `SubscriptionActivationService`,
  `ServiceEntitlementService`, `CreditLedgerService`, and `ServiceCatalog` for
  billing and service-access rules.
- Persian localization files for services, subscriptions, and credits.
- Tests for service-level entitlements, top-ups, add-ons, upgrade replacement,
  ledger consumption, and subscription/credit generation blocking.

### Changed

- Super Admin dashboard starts in global platform context instead of selecting a
  clinic by default.
- Dashboard header no longer renders a global clinic filter; clinic filters live
  inside related page filter areas and default lists show all allowed records
  newest first.
- Single sidebar items render as normal links, while multi-item groups render as
  collapsible submenus with active parent and child states.
- Admin sidebar groups are ordered by dashboard workflow importance, use matching
  parent/item styling, keep the logo area logo-only, and scroll independently.
- Normal users see only active purchasable plans; managers can manage plan
  definitions.
- Shared pagination now accepts standard `page`, `per_page`, `search`, `sort`,
  `direction`, and page-specific filter parameters only. DataTables request and
  response compatibility was removed from dashboard lists.
- Dashboard cards, plan notices, lists, tables, badges, empty states, lead
  detail, plans, and billing views were visually refined for RTL use.
- Dashboard list pages now use the shared filter/search surface instead of
  hand-written GET filter forms, and table pagination is rendered in a common
  footer component.
- Shared table controls now use the local UIcons icon-font pack for
  search/filter/check/close icons and RTL-aware previous/next pagination icons.
- Seeded roles, permissions, system setting labels, provider names, gateway
  names, and default display text are Persian where safe.
- Lead, user, SMS gateway, payment gateway, billing, role, and access tables now
  use compact columns, backend filters where applicable, and shared RTL row
  action menus.
- Plan definitions now render as a management table for authorized managers,
  while clinic users use the dashboard and purchase/upgrade cards without a
  plan table.
- Dashboard recent leads now expose the standard row action menu.
- Public OTP validation moved from controllers into Form Requests backed by the
  shared digit normalizer and validation rule.
- Create-capable dashboard lists now open create forms from modal triggers while
  preserving the standard Laravel form/redirect flow.
- Dashboard and admin OTP auth screens now default to light mode unless the
  user has explicitly persisted dark mode in that browser.
- Billing and plan purchase activation are clinic-aware and use service-level
  credit balances instead of expiring the whole base subscription when one
  service credit is exhausted.
- Public generation now checks active base subscription, service entitlement,
  remaining service credit, and AI model assignment before creating period jobs.

### Security

- Plan-restricted routes are blocked server-side for inactive, expired, or
  credit-finished normal users.
- Same active plan repurchase is blocked server-side until the active
  subscription expires by time, expires by usage, or is no longer active.
- SMS gateway credentials remain encrypted and are shown in Blade only as masked
  summaries.
- AI model credentials remain encrypted, hidden from serialization, masked in
  Blade, and excluded from usage logs.
- Protected demo AI model access records cannot be deleted.
- Clinics can use only active AI model access records assigned to them, and
  failed provider responses do not consume credit.
- Service credit consumption is recorded in `credit_transactions` and uses row
  locking to prevent negative balances and double spending.
- Lead upload previews are streamed through policy and tenant checks without
  exposing private storage paths.
- CRM follow-up writes are tenant- and lead-scoped and use policy/Form Request
  authorization.

### Documentation

- Added `docs/ai-models.md`, `docs/ai-model-security.md`, and
  `docs/permissions.md`.
- Added `docs/plans.md`, `docs/subscriptions.md`, `docs/credits.md`,
  `docs/billing.md`, and `docs/ai-service-access.md`.
- Updated README, architecture, API, database, dashboard, roles/permissions,
  testing, development guide, release notes, and agent instructions for AI model
  access management and SaaS service-credit billing.
- Documented the admin theme contract, including the default-light behavior,
  persisted user override, and shared dashboard/auth theme surfaces.

## 1.2.0

### Added

- Native Super Admin system-management area under `/admin/system`.
- Database-backed roles, permissions, role-permission assignments, and access
  assignment pages.
- Idempotent Super Admin seeding from safe `config('hairvision.super_admin.*')`
  values.
- Database-backed system settings with cache clearing on update.
- Reusable pagination helpers for standard JSON and DataTables-style requests.
- System SMS gateway management and user-private SMS gateway configuration.
- System payment gateway management with a local MVP payment adapter.
- Plan, invoice, payment, and subscription models and management flow.
- User financial section with own invoices, active subscription, and plan
  purchase/upgrade flow.
- Management reports separated from user reports.
- Focused system-management feature tests.

### Changed

- Admin menu visibility is now permission-aware for leads, users, plans,
  billing, reports, settings, and system management.
- Lead policy checks now use explicit permission codes while preserving the
  existing role behavior through the seeded access levels.
- Lead list JSON responses support shared pagination metadata and DataTables
  server-side parameters.
- Database seeding is idempotent for demo users, tenants, domains, providers,
  theme settings, roles, permissions, gateways, settings, plans, and demo leads.

### Security

- System-management routes use backend `permission:*` middleware.
- SMS and payment credentials use encrypted casts and are hidden from model
  serialization.
- User-private SMS gateways are scoped to the authenticated owner.
- Invoices are scoped to the authenticated user unless billing-management
  permission is present.
- Payment verification is idempotent before activating subscriptions.

### Documentation

- Added `docs/system-management.md`.
- Updated README, API, database, dashboard, roles/permissions, security,
  performance, setup, development, and agent instructions.

## 1.1.0

### Added

- SEO-friendly primary-domain landing page for HairVision AI.
- Admin dashboard entry point under `/admin`.
- Mobile OTP authentication for dashboard users.
- Local `OtpSenderInterface` abstraction for future SMS provider integration.
- Tenant-aware dashboard user memberships through `tenant_user`.
- Internal role model for super admin, clinic admin, editor, and viewer users.
- Active clinic switcher for dashboard users.
- Responsive RTL admin layout.
- Lead list with search, filters, pagination, and detail pages.
- Lead creation and editing for authorized roles.
- Limited editor mode for operational lead updates.
- Read-only viewer behavior.
- User list, reports, and settings pages.
- Demo seed users and clinic1 sample leads in multiple CRM states.
- Dashboard, authentication, tenancy, roles, security, and performance docs.

### Changed

- Primary `/` on `hairvision-ai.test` now renders the HairVision AI product
  landing page; tenant hosts still render the visitor flow.
- Visitor OTP verification now rejects already-used OTP rows.
- README documentation index now links the dashboard documentation set.

### Security

- Admin OTP values are hashed, expiring, single-use, attempt-limited, and
  rate-limited.
- Dashboard lead access is enforced with policies and tenant-scoped queries.
- Active clinic selection validates authenticated user membership.
- OTP security events are logged without plain OTP values.

### Documentation

- Added `docs/authentication.md`, `docs/tenancy.md`,
  `docs/roles-permissions.md`, `docs/dashboard.md`, `docs/security.md`, and
  `docs/performance.md`.
- Updated architecture, database, setup, API, development, coding standards,
  auth roadmap, and agent instructions.

## 1.0.0

### Features

- Laravel 13 multi-tenant MVP for hair transplant clinic lead generation.
- Host-based tenant resolution for tenant subdomains and custom domains.
- Persian right-to-left public visitor experience.
- Customer-aligned page structure with tenant header, application body, tenant
  footer, and back-to-site link fields.
- Full-width header, application body, and footer for customer website alignment.
- Embedded Vazirmatn font with no external frontend font dependency.
- API-first public flow under `/api/v1`.
- Tenant bootstrap endpoint for clinic identity, theme, and feature flags.
- OTP request and verification flow for visitor leads.
- Hashed OTP storage.
- OTP request rate limiting.
- Verified lead session for upload, generation, result, and asset access.
- Private face/scalp photo upload storage.
- Provider-agnostic AI adapter layer.
- Local `SampleImageProvider` for MVP result previews.
- Immediate debug/local result display for completed sample provider jobs.
- AI-style progress cast messages before showing generated results.
- Result gallery with original upload plus three generated periods and
  click-to-zoom viewing.
- Three sample progress periods: `3_months`, `6_months`, and `12_months`.
- Private generated asset storage under tenant and lead paths.
- Scoped result and asset APIs that enforce tenant and lead isolation.
- Redis Queue and Laravel Horizon configuration for queued processing.
- Local sample result assets for the MVP provider flow.
- Feature tests for tenant resolution, OTP, upload validation, sample
  generation, and tenant isolation.
- CRM-ready lead stage and tenant-owned admin note data structure.
- Tenant landing page visit tracking before lead, phone, or image submission.
- Human and AI agent documentation for implementation, testing, security, and
  release readiness.

### Documentation and Cleanup

- Flattened all project documentation directly under `/docs`.
- Made the root `README.md` the central documentation entry point.
- Added an admin/client dashboard authentication roadmap without implementing
  authentication or dashboard features.
- Removed operating-system and PHPUnit cache artifacts from the workspace.
- Removed the default scaffold unit test because it did not validate HairVision
  behavior.
- Removed the empty PHPUnit unit testsuite entry after deleting the scaffold
  unit test.
- Removed obsolete scaffold comments and consolidated duplicate model UUID boot
  logic into a shared trait.
- Moved repeated verified-lead session lookup into a service.
- Moved API validation from controllers into Form Requests where safe.

### Removed Files

- `.DS_Store`
- `.phpunit.result.cache`
- `storage/.DS_Store`
- `storage/app/.DS_Store`
- `storage/app/private/.DS_Store`
- `storage/app/private/uploads/.DS_Store`
- `tests/Unit/ExampleTest.php`

### Uncertain Unused Files

These files may be safe to remove later, but they need product or deployment
confirmation first:

- `database/database.sqlite` is ignored and likely local-only, but may be useful
  for developer setup.
- `public/build/**` is ignored Vite output. It should not be committed, but it
  can be useful for local smoke testing without running a fresh build.
- `public/theme/logo.svg` is tracked and may be a placeholder tenant logo; keep
  until theme asset ownership is clarified.
- `app/Services/TenantService.php` is a thin wrapper around `currentTenant`.
  Keep for now because it may become the stable access point for dashboard code.

### Known Risks

- Future dashboard work must not mix Laravel authenticated users with the public
  verified-lead session flow without a clear guard and policy design.
- Provider settings and tenant theme snippets will need stronger validation
  before admins can edit them.
- Any real AI provider must remain behind the provider adapter layer and must not
  be called directly from HTTP controllers.
