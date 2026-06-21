# Cleanup Report

Last updated: 2026-06-21.

## Cleanup Performed

- Reorganized documentation into product, architecture, development, roadmap,
  and audit sections.
- Made the root `README.md` the central entry point and added `docs/README.md`
  as the detailed documentation map.
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

## Removed Files

- `.DS_Store`
- `.phpunit.result.cache`
- `storage/.DS_Store`
- `storage/app/.DS_Store`
- `storage/app/private/.DS_Store`
- `storage/app/private/uploads/.DS_Store`
- `tests/Unit/ExampleTest.php`

## Uncertain Unused Files

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

## Risks

- Future dashboard work must not mix Laravel authenticated users with the public
  verified-lead session flow without a clear guard and policy design.
- Provider settings and tenant theme snippets will need stronger validation
  before admins can edit them.
- Any real AI provider must remain behind the provider adapter layer and must
  not be called directly from HTTP controllers.

## Recommended Next Steps

1. Add meaningful unit tests when service behavior grows beyond the current
   feature-test coverage.
2. Create dashboard policies before writing dashboard controllers.
3. Decide whether generated `public/build/**` artifacts should remain ignored
   only or be produced by deployment.
4. Add a formal permissions table only when static roles are no longer enough.
