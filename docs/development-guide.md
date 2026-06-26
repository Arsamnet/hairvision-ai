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

## Phase 1 Implementation Checklist

1. Migrations and models exist for the Phase 1 data model.
2. Tenant resolution middleware uses request host.
3. Seeder creates one test tenant, one tenant domain, and `sample_image`.
4. `/api/v1` routes exist for bootstrap, OTP, upload, generation, status,
   results, and assets.
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
15. Feature tests cover public flow, theme fields, CRM structure, visit
    tracking, and tenant isolation.

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
- Sample generation creates three jobs.
- Sample generation creates three generated assets.
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
- Standard and DataTables-compatible pagination.
- System SMS gateway and user-private SMS gateway separation.
- Default payment gateway selection.
- Invoice visibility and payment flow.
- Plan activation, upgrade replacement, and subscription expiry by time or
  usage.
- Sidebar icons, grouped menu visibility, and locked menu state for users
  without active plans.
- Backend route blocking for inactive-plan users while plans, billing, and
  settings remain accessible.
- Super Admin plan-definition management and normal-user denial.
- Route-level plan purchase, payment, and upgrade without carrying old credit.
- CRM follow-up creation, timeline visibility, and tenant isolation.
- Authorized lead upload image display and streaming.
- Super Admin dashboard starting without a selected clinic and manual clinic
  selection.
- Normal-user dashboard data scoping.
- Persian language-file presence and required keys.

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
4. The active tenant switcher validates membership server-side.
5. Lead lists are filtered, searchable, paginated, and tenant-scoped.
6. Viewer users cannot edit leads.
7. Editor users can update only limited operational fields.
8. Clinic admins can create and update clinic leads.
9. Super admins can view all tenants.
