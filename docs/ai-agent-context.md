# AI Agent Context

This document is for AI agents working on HairVision.

## Product Summary

HairVision is a Laravel 13 multi-tenant SaaS MVP for hair transplant clinics.
Visitors arrive through a tenant-specific host, verify a phone number by OTP,
upload a face/scalp image, and receive sample progress images for 3, 6, and 12
months.

## Current Release Target

Version: `1.0.0`

Release 1.0.0 is a Phase 1 MVP. It must prove the complete test-provider flow
before any real AI provider is added.

## Architecture Shape

```text
Browser
  -> Persian RTL Blade page
  -> fetch('/api/v1/...')
  -> Laravel API Controller
  -> Service / Action
  -> Redis Queue Job
  -> AI Provider Adapter
  -> Private Storage
  -> JSON response
```

In tests, the sample provider flow may run synchronously through
`HAIRVISION_DEBUG_FAST_SAMPLES=true` or test configuration. Production and normal
development queue-heavy work must use Redis Queue and Horizon.

## UI Rules

- Public visitor UI is Persian and right-to-left.
- The embedded Vazirmatn font is used from `resources/fonts/vazirmatn`.
- CSS, JavaScript, fonts, and sample result images must be local project assets.
- Tenant logos and theme images may be requested from the primary HairVision
  domain only.
- The public page has three sections: tenant header, HairVision application body,
  and tenant footer.
- Header, body, and footer sections should be full-width so clinics can align the
  page with their main website.
- The initial tenant template must be end-user ready and must not show testing,
  deployment, or technical setup copy.
- Do not introduce React, Vue, Inertia, Livewire, or Next.js in the MVP.

## Agent Rules

1. Read `AGENTS.md` and the docs listed there before editing.
2. Keep changes small and testable.
3. Keep Blade API-first.
4. Never place provider logic in controllers.
5. Resolve tenant only from request host.
6. Never trust `tenant_id` from user input.
7. Store OTP codes only as hashes.
8. Store uploads outside the public webroot.
9. Use `SampleImageProvider` until the MVP flow is complete.
10. Add or update tests for every behavior change.
11. Run the full test suite after each change.

## Phase 1 Done Means

- A test tenant can be created and resolved by host.
- Unknown hosts return 404.
- Tenant landing page visits are tracked even without a lead.
- OTP request stores only a hash and is rate-limited.
- OTP verify creates or updates a verified lead.
- Upload requires a verified lead session.
- Uploaded files are stored privately.
- Generation creates jobs for `3_months`, `6_months`, and `12_months`.
- `SampleImageProvider` copies local sample images and makes no external HTTP calls.
- Results and assets are scoped to the current tenant and lead session.
- The Persian RTL Blade page drives the workflow through `/api/v1`.
- Results are revealed after short AI-style progress cast messages.
- The result gallery shows the original upload plus the three generated periods.
- Lead CRM stage and admin-note tables exist for later admin dashboard work.
- `php artisan test` passes.
