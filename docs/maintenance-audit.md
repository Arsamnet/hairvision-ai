# Maintenance Audit

Last updated: 2026-06-26

This note records the current cleanup posture for HairVision. Keep the public
visitor MVP stable, preserve implemented dashboard/SMS behavior, and document
uncertain cleanup candidates before deleting them.

## Problems Found

- Tenant host lookup was duplicated between the root web route and API
  middleware.
- SMS delivery log filter options exposed all SMS provider account names to
  non-super-admin users, including other users' private provider names.
- SMS provider creation relied on request validation for the system-provider
  invariant; the action layer did not enforce it independently.
- Documentation still emphasized the original public MVP while the codebase also
  contains admin dashboard and SMS provider modules.
- Dashboard lead lists were documented as paginated but still loaded the full
  filtered result set.
- Lead detail eager loading missed generation jobs used by the Blade view.
- The default public footer referenced a missing `/theme/hairvision-icon.svg`
  asset.
- SMS documentation was split across four short overlapping files.
- `alpinejs` was present in frontend dependencies but was not imported or used.
- Local runtime artifacts may appear in the workspace, such as
  `.phpunit.result.cache` and `storage/logs/laravel.log`.

## Cleanup Completed

- Centralized host-domain lookup and current-tenant binding in
  `App\Services\TenantService`.
- Updated API tenant middleware and root web route to use the shared tenant
  service.
- Scoped SMS log provider filters to system providers and the authenticated
  user's own private providers.
- Made SMS provider creation authorize through `SmsProviderAccountPolicy`.
- Added an action-level guard so only super admins can create system SMS
  provider accounts.
- Added regression coverage for platform-domain visit tracking and SMS provider
  filter isolation.
- Updated README and security/tenancy/SMS docs to match the implemented modules.
- Paginated dashboard lead listings server-side and removed client-side
  DataTables enhancement from that table.
- Eager loaded generation jobs on the lead detail page to avoid extra queries.
- Replaced the missing public footer icon path with the Vite-managed local logo
  asset.
- Consolidated SMS provider, architecture, security, and testing documentation
  into `docs/sms.md`.
- Removed the unused Alpine frontend dependency.
- Added regression coverage for admin lead pagination and the visitor footer
  asset path.

## Uncertain Unused Files

- `public/theme/logo.svg` may still be a demo tenant theme asset.
- `resources/img/logo.svg` and `resources/img/favicon.png` are app brand assets
  used by Vite/Blade and should stay.
- `app/Services/TenantService.php` is now an active shared tenant lookup service.
- `storage/app/private/sample-results/*.jpg` are required by
  `SampleImageProvider`.
- Generated Vite build output should stay ignored and can be regenerated.

## Recommended Next Steps

- Add pagination to SMS log listings before real clinic data volume grows.
- Consider policy classes for SMS delivery logs and dashboard users if those
  modules gain write actions.
- Inject `ProviderResolver` into `ProcessGenerationJob` through the container
  when queue/provider complexity grows.
- Add a project-level static analysis or Pint command to the release checklist.
- Review whether release target labels should remain `1.0.0` or be updated to
  reflect the implemented dashboard/SMS modules.
