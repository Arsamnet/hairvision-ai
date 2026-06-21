# AI Agent Instructions

This document is for AI agents working on HairVision. The primary operating
guide remains `AGENTS.md`; this file mirrors the project-specific context inside
the flat `/docs` structure.

## Product Summary

HairVision is a Laravel 13 multi-tenant SaaS MVP for hair transplant clinics.
Visitors arrive through a tenant-specific host, verify a phone number by OTP,
upload a face/scalp image, and receive sample progress images for 3, 6, and 12
months.

## Current Release Target

Version: `1.0.0`

Release `1.0.0` is a Phase 1 MVP. It must prove the complete test-provider flow
before any real AI provider is added.

## Must Read First

Before editing code, read:

1. `AGENTS.md`
2. `README.md`
3. `docs/agent-instructions.md`
4. `docs/architecture.md`
5. `docs/api.md`
6. `docs/database.md`
7. `docs/setup.md`
8. `docs/development-guide.md`
9. `docs/coding-standards.md`
10. `docs/auth-roadmap.md`
11. `docs/release-notes.md`

## Core Constraints

- Use Laravel 13 conventions.
- Use API-first architecture.
- Blade pages must call `/api/v1` endpoints instead of duplicating business
  logic.
- Public product UI is Persian, right-to-left, and uses the embedded Vazirmatn
  font.
- The visitor page has three sections: tenant header, HairVision app body, and
  tenant footer.
- Do not show testing, deployment, or setup copy in the visitor-facing template.
- Do not depend on external frontend resources.
- Never put AI provider logic inside controllers.
- Always resolve tenant from the request host, never from user input.
- Every tenant-owned record must include `tenant_id`.
- Never store OTP codes in plain text; store only hashes.
- Never store uploaded user photos in the public webroot.
- In MVP, use `SampleImageProvider` as the default AI provider.
- `SampleImageProvider` must not call external AI services and must not generate
  images.
- Queue-heavy processing must use Redis Queue and Horizon outside tests.
- Do not introduce React, Vue, Inertia, Livewire, or Next.js into the MVP unless
  explicitly requested.
- After every behavior change, run the full test suite and add or update tests
  for the changed behavior.

## Phase 1 Done Means

- A test tenant can be created and resolved by host.
- Unknown hosts return `404`.
- Tenant landing page visits are tracked even without a lead.
- OTP request stores only a hash and is rate-limited.
- OTP verify creates or updates a verified lead.
- Upload requires a verified lead session.
- Uploaded files are stored privately.
- Generation creates jobs for `3_months`, `6_months`, and `12_months`.
- `SampleImageProvider` copies local sample images and makes no external HTTP
  calls.
- Results and assets are scoped to the current tenant and lead session.
- The Persian RTL Blade page drives the workflow through `/api/v1`.
- Results are revealed after short AI-style progress cast messages.
- The result gallery shows the original upload plus the three generated periods.
- Lead CRM stage and admin-note tables exist for later admin dashboard work.
- `php artisan test` passes.

## Required Agent Prompt

```text
You are working on the HairVision Laravel 13 project.

Before making code changes, read AGENTS.md and all files listed in its
"Must Read First" section.

Keep the project on version 1.0.0 / Phase 1 unless explicitly asked otherwise.

Core constraints:
- Laravel 13 conventions.
- API-first Blade.
- Persian RTL public UI with embedded Vazirmatn font.
- Tenant header, HairVision application body, tenant footer, and optional back-to-site link.
- Header, application body, and footer should be full-width.
- No testing/deployment/setup copy in the visitor-facing template.
- Short AI-style progress cast before showing results.
- The result gallery should show the original upload plus three result periods and support zoom.
- No arbitrary external frontend resources.
- Tenant is resolved from request host only.
- Do not trust tenant_id from requests.
- Store OTP hashes only.
- Store uploads and generated assets privately.
- Use SampleImageProvider only.
- SampleImageProvider must not call external services or generate images.
- Redis Queue and Horizon are required outside tests.
- Add or update tests for every behavior change.
- Run php artisan test after every change.

After implementation, report changed files, commands run, and test results.
```
