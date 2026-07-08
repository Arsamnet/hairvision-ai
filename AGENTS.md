# AGENTS.md - HairVision Agent Guide

This is the first file AI agents should read in the HairVision repository.

Project: `HairVision`

Version: `1.0.0`

HairVision is a complete Laravel 13 multi-tenant SaaS for hair-transplant
clinics. Public visitors use a tenant host, verify a phone number by OTP, upload
a private face/scalp image, and receive three staged preview results through the
versioned API.

## Must Read First

Before editing code, read these files in order:

1. `docs/agent-instructions.md`
2. `docs/business-rules-fa.md`
3. `docs/architecture.md`
4. `docs/api.md`
5. `docs/release-notes.md`

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
12. Use `SampleImageProvider` as the default provider for `1.0.0`; it must not
    call external services, generate images, or use random external URLs.
13. Real providers stay behind assigned AI model access records, encrypted
    credentials, provider adapters, jobs, and service-credit checks.
14. Queue-heavy processing uses Redis Queue and Horizon outside tests.
15. Do not introduce React, Vue, Inertia, Livewire, Next.js, CDN CSS/JS/fonts, or
    external dashboard icon packs unless explicitly requested.
16. After every behavior change, add/update tests and run the full suite.

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
- Add useful PHPDoc to public classes/methods and complex protected/private
  methods. Use native types where safe and `@since 1.0.0` for version history.
- Keep comments concise and explain non-obvious intent, side effects, security
  decisions, or integration constraints.
- Never expose private storage paths, OTP codes, credentials, bearer tokens,
  raw provider/gateway payloads, stack traces, or sensitive user data in logs,
  JSON, or Blade.

## Quality Gate

Run after behavior changes:

```bash
php artisan test
```

Also run after frontend asset changes:

```bash
npm run build
```

Report changed files, commands run, and test results at the end of the task.
