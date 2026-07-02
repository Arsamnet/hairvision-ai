# AGENTS.md - HairVision Agent Instructions

This file is the primary operating guide for Codex, ChatGPT Agent, Copilot Agent,
Cursor Agent, or any other AI agent working inside the HairVision repository.

## Project Identity

Project name: `HairVision`

Current release target: `1.0.0`

HairVision is a Laravel 13 multi-tenant SaaS MVP for hair transplant clinics.
Visitors enter through a tenant-specific subdomain or custom domain, verify their
phone number with OTP, become a lead for that tenant, upload a face/scalp photo,
and receive staged preview images for two weeks after transplant, intermediate
regrowth, and the final result.

## Must Read First

Before editing code, read these files in order:

- `docs/agent-instructions.md`
- `docs/architecture.md`
- `docs/api.md`
- `docs/database.md`
- `docs/setup.md`
- `docs/development-guide.md`
- `docs/coding-standards.md`
- `docs/auth-roadmap.md`
- `docs/release-notes.md`

## Non-Negotiable Rules

1. Use Laravel 13 conventions.
2. Use API-first architecture.
3. Blade pages must call `/api/v1` endpoints instead of duplicating business logic.
4. The public product UI is Persian, right-to-left, and uses the embedded Vazirmatn font.
5. The visitor page has three sections: tenant header, HairVision app body, and tenant footer.
6. Do not show testing, deployment, or setup copy in the visitor-facing template.
7. The app must not depend on external frontend resources; only tenant logos or theme images may be loaded from the primary HairVision domain.
8. Never put AI provider logic inside controllers.
9. Always resolve tenant from the request host, never from user input.
10. Every tenant-owned record must include `tenant_id`.
11. Never store OTP codes in plain text; store only hashes.
12. Never store uploaded user photos in the public webroot.
13. In MVP, use `SampleImageProvider` as the default AI provider.
14. `SampleImageProvider` must not call external AI services and must not generate images.
15. Queue-heavy processing must use Redis Queue and Horizon outside tests.
16. Do not introduce React, Vue, Inertia, Livewire, or Next.js into the MVP unless explicitly requested.
17. After every behavior change, run the full test suite and add or update tests for the changed behavior.

## Preferred Implementation Order

1. Keep dependencies and configuration aligned with Laravel 13, Sanctum, Horizon, and Redis.
2. Maintain migrations and models for the Phase 1 data model.
3. Maintain host-based tenant resolution middleware.
4. Maintain OTP APIs and lead session flow.
5. Maintain private upload APIs.
6. Maintain the AI provider interface and `SampleImageProvider`.
7. Maintain generation jobs and result APIs.
8. Maintain Persian RTL Blade screens that call the APIs.
9. Add focused tests before or with every behavior change.
10. Prepare changes for a clean GitHub commit.

## Coding Style

- Prefer service classes over fat controllers.
- Controllers should validate request data, call a service/action, and return JSON or a view shell.
- Use FormRequest and API Resources when the endpoint grows beyond simple MVP validation/serialization.
- Use enums for statuses when practical.
- Use policies or scoped queries for tenant admin access.
- Add tests for every public API endpoint and every tenant-isolation rule.

## Do Not Do

- Do not create a separate frontend app yet.
- Do not hard-code provider behavior in controllers.
- Do not build provider-specific database columns.
- Do not expose storage paths directly.
- Do not trust `tenant_id` from request bodies.
- Do not make synchronous real AI calls from the HTTP lifecycle.
- Do not use fake generated images from random external URLs.
- Do not add untested behavior changes.

## Expected Current Task Shape

For version `1.0.0`, keep the project focused on Phase 1:

- One test tenant can be resolved by host.
- OTP request/verify creates a verified lead.
- Upload requires a verified lead session and stores files privately.
- Generation creates the three staged jobs and uses `SampleImageProvider` by
  default.
- Generated assets are tenant- and lead-scoped.
- The Persian RTL Blade page drives the flow through `/api/v1`.
- Tests pass before the work is considered complete.
