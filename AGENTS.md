# AGENTS.md - HairVision Agent Guide

This is the first file AI agents should read in the HairVision repository.

Project: `HairVision`

Version: [`2.0.0`](CHANGELOG.md#200)

HairVision is a complete Laravel 13 multi-tenant SaaS for clinics offering
AI-assisted image previews. Public visitors use a tenant host, verify a phone
number by OTP, maintain a private source image, and independently request
eligible outputs from system-managed AI categories through the versioned API.

## Must Read First

Before editing code, read these files in order:

1. `docs/agent-instructions.md`
2. `docs/business-rules-fa.md`
3. `docs/architecture.md`
4. `docs/api.md`
5. `CHANGELOG.md`

Do not reopen deleted legacy docs or recreate them. New documentation belongs in
one of the files above unless a separate audience or lifecycle is unavoidable.

## Non-Negotiable Rules

1. Use Laravel 13 conventions and keep controllers thin.
2. Keep the product API-first: Blade shells call `/api/v1` for public behavior.
3. Public UI is Persian, RTL, and uses the embedded Vazirmatn font.
4. The visitor page contains tenant header, HairVision app body, and tenant
   footer.
5. No testing, setup, deployment, or debugging copy may appear in visitor-facing
   templates.
6. Public frontend assets must be local; tenant logos/theme images may only come
   from the primary HairVision domain.
7. Resolve tenant from request host only. Never trust `tenant_id`, slug, or
   domain from visitor input.
8. Every tenant-owned record must include `tenant_id`; visitor-owned resources
   must also be scoped by the verified lead session.
9. Store OTP codes only as hashes.
10. Store uploads and generated assets privately, never in the public webroot.
11. Never put AI provider logic in controllers.
12. The local `sample_image` adapter may run only through an active model access
    assigned to the clinic; it must not call external services, generate
    images, or use random external URLs.
13. Real providers stay behind assigned AI model access records, encrypted
    credentials, provider adapters, jobs, and service-credit checks.
14. Queue-heavy processing uses Redis Queue and Horizon where a persistent
    worker is available. Shared cPanel hosting may use the explicitly enabled,
    non-overlapping cron worker with Redis or the database queue.
15. Do not introduce React, Vue, Inertia, Livewire, Next.js, CDN CSS/JS/fonts, or
    external dashboard icon packs unless explicitly requested.
16. After every behavior change, add/update tests and run the full suite.
17. Landing templates are system-owned trusted HTML fragments. Only authorized
    platform users may edit or assign them; CSS and JavaScript must be inline,
    and external frontend assets remain forbidden.

## Development Priorities

1. Preserve host-based tenant resolution and tenant isolation.
2. Preserve OTP, verified-lead session, private upload, generation, result, and
   asset APIs.
3. Preserve admin dashboard authentication, roles, permissions, tenant context,
   active-plan gating, and policy-backed authorization.
4. Preserve AI model access, provider adapter, service entitlement, and credit
   ledger boundaries.
5. Preserve Persian localization, Jalali business dates, reusable dashboard
   components, and local frontend assets.

## Coding Style

- Prefer services, actions, jobs, policies, Form Requests, API Resources, and
  scoped queries over fat controllers.
- Reuse shared utilities for digit normalization, Jalali dates, pagination,
  search/filtering, tenant filters, billing, credits, and provider execution.
- Add useful PHPDoc to all project-owned named classes, interfaces, traits,
  enums, functions, and methods. Document input/output types and use the actual
  introduction version: `@since 1.0.0` for original declarations and
  `@since 2.0.0` for declarations introduced in version 2.
- Keep comments concise and explain non-obvious intent, side effects, security
  decisions, or integration constraints.
- Never expose private storage paths, OTP codes, credentials, bearer tokens,
  raw provider/gateway payloads, stack traces, or sensitive user data in logs,
  JSON, or Blade.

## Quality Gate

Run after behavior changes:

```bash
php artisan test
composer phpdoc:audit
```

Also run after frontend asset changes:

```bash
npm run build
```

Report changed files, commands run, and test results at the end of the task.

## Versioning Workflow

- Record every user-visible, behavioral, schema, security, or operational change
  under `Unreleased` in the root `CHANGELOG.md` while it has no assigned release.
- When a release is explicitly prepared, move its entries from `Unreleased` to
  one immutable SemVer heading with the release date.
- Update `APP_VERSION`, the runtime default, documentation version headers, and
  version metadata tests in the same change.
- Never leave completed unversioned work outside `Unreleased`; the next release
  consumes those entries.
