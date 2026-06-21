# Release Notes

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
