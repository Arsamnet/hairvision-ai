# Product Scope MVP

## Definition

HairVision is a lead-generation and visual-simulation service for hair transplant
clinics. Each tenant gets a visitor-facing landing page on a tenant subdomain or
custom domain.

Visitors verify their phone number, upload a face/scalp photo, and see staged
sample progress images. Clinic admins will later review leads, uploads, jobs, and
generated assets.

## Version 1.0.0 Scope

Version `1.0.0` focuses on the public visitor flow and the local sample provider.

Included:

- Host-based tenant resolution.
- Landing page visit tracking per tenant before any lead is created.
- Tenant bootstrap API.
- OTP request and verify APIs.
- Hashed OTP storage.
- OTP request rate limiting.
- Verified lead session.
- Private image upload.
- Generation group creation.
- Three generation periods: `3_months`, `6_months`, `12_months`.
- `SampleImageProvider` that copies local sample assets.
- Private generated asset serving through tenant- and lead-scoped API routes.
- Persian RTL Blade page that calls `/api/v1`.
- Customer-aligned page layout with database-backed header, application body,
  footer, and optional "back to customer site" link.
- Full-width header, application body, and footer sections for better alignment
  with customer websites.
- AI-style progress cast messages before results are shown.
- Result gallery with the original image plus the three generated periods, and
  click-to-zoom image viewing.
- CRM-ready lead stage and admin note data structure.
- Feature tests for the public MVP flow.

## Public Visitor Flow

1. Visitor opens a tenant host.
2. Laravel resolves the tenant from the request host.
3. Laravel records a tenant landing-page visit.
4. Blade renders the Persian RTL landing page.
5. Blade requests OTP through `/api/v1/auth/otp/request`.
6. Visitor verifies OTP through `/api/v1/auth/otp/verify`.
7. API creates or updates a verified lead for the current tenant.
8. Visitor uploads an image through `/api/v1/uploads`.
9. API stores the file in private storage.
10. Visitor creates generation through `/api/v1/generations`.
11. Jobs create sample outputs for 3, 6, and 12 months.
12. The UI shows one AI-style progress cast message at a time.
13. Visitor polls status when needed and loads results from `/api/v1`.

## Later MVP Admin Scope

These items are documented but are not required before the public test-provider
flow is stable:

- Tenant admin login.
- Tenant dashboard.
- Lead list and lead detail views.
- Lead stage editing.
- Admin lead notes UI.
- Upload and generated asset review.
- CSV export.
- Theme settings.
- Tenant AI provider settings.
- Platform admin tenant/domain/provider management.

## Explicitly Out of Scope for 1.0.0

- Real AI providers.
- Next.js frontend.
- CRM automation.
- Online payments.
- Advanced form builder.
- Drag-and-drop template builder.
- Automated Cloudflare for SaaS.
- AI model training.
- WordPress plugin integration.
- User-side photo editor.
- WebSocket live progress.

## Success Criteria

- Tenant isolation is enforced in every public query.
- OTP codes are never stored in plain text.
- Uploads never enter public webroot.
- `SampleImageProvider` makes no external calls.
- Three generated assets are created for each successful generation group.
- The Persian RTL UI works without external frontend resources.
- Landing page visits are counted even when no phone number or image is submitted.
- The default clinic template is ready for end visitors and does not expose
  testing, deployment, or technical setup language.
- Tests cover the behavior and pass before commit.
