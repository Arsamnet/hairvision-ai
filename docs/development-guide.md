# Development Guide

HairVision `1.0.0` is a Phase 1 MVP. Keep development focused on the public
visitor flow and the local sample-image provider until the MVP is stable.

## Tech Stack

- PHP 8.3+
- Laravel 13
- Composer
- Node.js LTS
- Vite
- Tailwind CSS
- Blade
- Redis
- Laravel Horizon
- Laravel Sanctum
- Predis

## Frontend Rules

- Blade is the only MVP frontend.
- Public UI is Persian and right-to-left.
- The embedded Vazirmatn font is loaded from `resources/fonts/vazirmatn`.
- No CDN is allowed for CSS, JavaScript, fonts, or icons.
- Tenant logos and theme images may be served from the primary HairVision domain.
- Do not introduce React, Vue, Inertia, Livewire, or Next.js in the MVP.
- Dashboard Blade should use the shared components documented in
  `docs/frontend-blade-standards.md`.
- Dashboard theme defaults to light. Use the persisted per-browser admin theme
  choice only; do not auto-enable dark mode from system color-scheme
  preferences.
- Create-capable list pages should use modal create forms and table rows should
  use the shared three-dot action menu.
- Use two frontend layout modes. Mobile mode applies below `1024px`, including
  tablets; desktop mode starts at the Tailwind `lg` breakpoint.
- Keep `resources/css/app.css` as the Vite entry and place new focused
  responsive CSS in `resources/css/base`, `resources/css/layouts`, or
  `resources/css/components` when it is shared.
- Dashboard list pages should pair desktop tables with mobile/tablet
  `x-admin.mobile-list` cards for the important fields and existing row
  actions.

## Shared Backend Utilities

Use `App\Support\NumberNormalizer` for Persian, Arabic, and Latin digit
normalization. Do not add private `normalizeDigits` copies to controllers,
requests, tests, or services. Use `App\Rules\SupportedDigits` in Form Requests
when input must contain only supported digit scripts.

Use `App\Support\Query\SearchFilter` with
`App\Support\Pagination\PaginationParameters` for dashboard table searches
instead of repeating `LIKE` blocks in controllers or services. For tenant-safe
dashboard clinic filters, reuse a focused resolver/service instead of trusting
raw `clinic_id` input.

Use focused overview/statistics services, such as
`App\Services\Admin\SystemOverviewService`, when dashboard and report pages need
overlapping metrics.

Use `App\Support\JalaliDate`, `App\Rules\JalaliDate`, and
`App\Rules\JalaliDateTime` for all Jalali/Shamsi date conversion and validation.
Do not duplicate date conversion logic. User-facing business dates are stored as
zero-padded Jalali `char(19)` strings, while Laravel system timestamps remain
Gregorian in storage and are converted only for display.

## Phase 1 Implementation Checklist

1. Migrations and models exist for the Phase 1 data model.
2. Tenant resolution middleware uses request host.
3. Seeder creates one ready-to-use Clinic Aria tenant, active domains, an active
   purchased starter plan, service credits, `sample_image`, and active GapGPT
   model access.
4. `/api/v1` routes exist for bootstrap, OTP, upload, generation, status,
   results, and assets.
5. OTP request stores hashes and is rate-limited.
6. OTP verify creates a verified lead session.
7. Upload requires a verified lead session.
8. Generation requires the current tenant and current session lead.
9. `SampleImageProvider` returns local sample paths only.
10. Generation creates staged jobs for `two_weeks`, `intermediate_regrowth`,
    and `final_result`; only the next pending stage is queued.
11. Jobs copy samples or real provider output into private tenant/lead result
    paths.
12. Blade is Persian RTL and calls `/api/v1`.
13. Tenant theme settings support header, footer, and back-to-site link.
14. Landing page visits are tracked before lead creation.
15. Lead CRM stage and admin note structure exists.
16. Feature tests cover public flow, theme fields, CRM structure, visit
    tracking, and tenant isolation.
17. SaaS billing tests cover base plans, service add-ons, top-ups, service
    entitlements, ledger consumption, and AI service access blocking.

## Testing Rule

After every code, configuration, or behavior change:

1. Add or update tests for the changed behavior.
2. Run the full automated test suite.
3. Fix failures before continuing.
4. If frontend assets changed, run the production build.
5. Document any intentional behavior change.

Required command:

```bash
php artisan test
```

## Error Handling Rule

Every user-facing failure path must be wrapped at the nearest controller,
service, job, or adapter boundary where it can be handled safely. Log the real
exception through Laravel logs with non-sensitive context, and return only a
generic localized message to the user. Never expose raw exception text, gateway
or provider diagnostics, credentials, OTP codes, private storage paths, or stack
traces in Blade or JSON responses.

Additional testing expectations are documented in `docs/testing.md`.

Run when CSS, JavaScript, Blade, fonts, or Vite inputs change:

```bash
npm run build
```

Run when migrations or seeders change:

```bash
php artisan migrate:fresh --seed
php artisan test
```

## Current Feature Coverage

`tests/Feature/HairVisionMvpTest.php` covers:

- Tenant resolution by host.
- Landing page visit tracking before lead creation.
- Unknown host `404`.
- OTP hash storage.
- OTP rate limiting.
- OTP accepts Persian, Arabic, and English digits after normalization.
- OTP rejects non-digit characters in phone and code fields.
- OTP verify creates a verified lead.
- Upload requires verified lead session.
- Invalid uploads are rejected.
- Sample generation creates three staged jobs.
- Sample generation creates three generated assets.
- The public API starts only the first queued stage when the queue is faked.
- GapGPT generation sends three independent image edit requests with `n = 1`.
- Generation auth failures return JSON messages suitable for the Blade frontend.
- Generated assets are stored privately.
- `SampleImageProvider` sends no external HTTP requests.
- Debug/local sample generation can display completed results immediately.
- Tenant B cannot access Tenant A assets.
- Tenant theme back-link fields can be stored.
- CRM lead stage and lead notes are tenant-owned.

`tests/Feature/AdminDashboardTest.php` covers:

- Primary root product landing page.
- Admin OTP hash storage and login.
- Demo seeder users and clinic lead states.
- Tenant-scoped dashboard lead lists.
- Viewer edit denial.
- Editor limited lead updates.

`tests/Feature/SystemManagementTest.php` covers:

- Idempotent Super Admin, access-level, permission, gateway, setting, and plan
  seeding.
- System management route and menu protection.
- Permission assignment enforcement.
- System setting updates.
- Standard pagination, search, sorting, and page-specific filters.
- System SMS gateway and user-private SMS gateway separation.
- Default payment gateway selection.
- Invoice visibility and payment flow.
- Plan activation, upgrade replacement, and subscription expiry by time or
  usage.
- Sidebar icons, grouped menu visibility, and locked menu state for users
  without active plans.
- Shared dashboard list filter/search components, sortable table headings, and
  RTL pagination footer behavior.
- Mobile/tablet drawer navigation, mobile list cards, modal keyboard controls,
  responsive-control JavaScript, and localization keys for new UI labels.
- Backend route blocking for inactive-plan users while plans, billing, and
  settings remain accessible.
- Super Admin plan-definition management and normal-user denial.
- Plan definitions render as an admin management table, while clinic users use
  the dashboard and purchase/upgrade flow without a plan table.
- Route-level plan purchase, payment, and upgrade without carrying old credit.
- CRM follow-up creation, timeline visibility, and tenant isolation.
- Authorized lead upload image display and streaming.
- Super Admin dashboard starting without a selected clinic and page-level clinic
  filters for clinic-owned data.
- Normal-user dashboard data scoping.
- Persian language-file presence and required keys.
- Jalali CRM follow-up input storage and Jalali date localization keys.

`tests/Feature/AiModelAccessManagementTest.php` covers:

- AI model config and Persian usage category loading.
- Protected demo model seeding and Super Admin permissions.
- Seeded GapGPT access stores three stage prompts and an encrypted default
  credential and is active for Clinic Aria after seeding.
- AI model access CRUD, credential encryption, masking, toggling, and protected
  delete denial.
- Clinic assignment and generation credit/log creation.
- Unassigned, inactive, and credit-finished access blocking.
- Failed provider responses not consuming credit.
- GapGPT HTTP gateway failures being written to Laravel logs without API keys.
- Clinic manager user creation scoped to the actor's clinic and clinic-level
  role assignment.

`tests/Feature/SaasBillingEntitlementTest.php` covers:

- Base plans with one or multiple service credits.
- One active base subscription per clinic.
- Service add-ons requiring an active base plan and preventing duplicate active
  services.
- Credit top-ups adding service credit without extending subscription time.
- Upgrade replacement without carrying remaining service credit.
- Atomic credit consumption and ledger rows.
- Public generation blocking when subscription or service credit is unavailable.

## Release Checklist

Before tagging or committing release-ready work:

1. `APP_VERSION` is `1.0.0`.
2. `.env.example` uses Redis cache/session/queue.
3. `config/horizon.php` exists.
4. Public UI is Persian RTL.
5. Vazirmatn font files exist under `resources/fonts/vazirmatn`.
6. No arbitrary external frontend resources are required.
7. The public template has tenant header, application body, tenant footer, and no
   testing/deployment/setup copy.
8. Header, application body, and footer are full-width.
9. Result images support click-to-zoom in the visitor UI.
10. Landing page visits are tracked before lead creation.
11. Sample images exist under `storage/app/private/sample-results`.
12. `php artisan test` passes.
13. `npm run build` passes.
14. `git status --short` has only intentional changes.

## Admin Dashboard Checklist

1. `/` on `hairvision-ai.test` renders the product landing page.
2. `/admin` starts mobile OTP login.
3. Authenticated users reach `/admin/dashboard`.
4. Page-level clinic filters validate membership server-side.
5. Lead lists are filtered, searchable, paginated, newest-first, and
   tenant-scoped.
6. Viewer users cannot edit leads.
7. Editor users can update only limited operational fields.
8. Clinic admins can create and update clinic leads.
9. Super admins can view all tenants.
