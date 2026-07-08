# Changelog

## 1.0.0

### Features

- Laravel 13 multi-tenant SaaS for hair transplant clinic lead generation.
- Host-based tenant resolution for tenant subdomains and custom domains.
- Persian right-to-left public visitor experience.
- Customer-aligned page structure with tenant header, application body, tenant footer, and back-to-site link fields.
- Full-width header, application body, and footer for customer website alignment.
- Embedded Vazirmatn font with no external frontend font dependency.
- API-first public flow under `/api/v1`.
- Tenant bootstrap endpoint for clinic identity, theme, and feature flags.
- OTP request and verification flow for visitor leads.
- Hashed OTP storage.
- OTP request rate limiting.
- Verified lead session for upload, generation, result, and asset access.
- Private face/scalp photo upload storage.
- Provider-agnostic AI adapter layer.
- Local `SampleImageProvider` for result previews.
- Immediate debug/local result display for completed sample provider jobs.
- AI-style progress cast messages before showing generated results.
- Result gallery with original upload plus three generated periods and click-to-zoom viewing.
- Three sample progress periods: `3_months`, `6_months`, and `12_months`.
- Private generated asset storage under tenant and lead paths.
- Scoped result and asset APIs that enforce tenant and lead isolation.
- Redis Queue and Laravel Horizon configuration for queued processing.
- Local sample result assets for the sample provider flow.
- Feature tests for tenant resolution, OTP, upload validation, sample generation, and tenant isolation.
- CRM-ready lead stage and tenant-owned admin note data structure.
- Tenant landing page visit tracking before lead, phone, or image submission.
- Human and AI agent documentation for implementation, testing, security, and release readiness.
