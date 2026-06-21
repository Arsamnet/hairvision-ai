# Testing and Release Checklist

## Rule

After every code, configuration, or behavior change:

1. Add or update tests for the changed behavior.
2. Run the full automated test suite.
3. Fix failures before continuing.
4. If frontend assets changed, run the production build.
5. Document any intentional behavior change.

No change is commit-ready until tests pass.

## Required Commands

Always run:

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

## Current Feature Test Coverage

`tests/Feature/HairVisionMvpTest.php` covers:

- Tenant resolution by host.
- Landing page visit tracking before lead creation.
- Unknown host 404.
- OTP hash storage.
- OTP rate limiting.
- OTP accepts Persian, Arabic, and English digits after normalization.
- OTP rejects every non-digit character in phone and code fields.
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

## Version 1.0.0 Release Checklist

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
