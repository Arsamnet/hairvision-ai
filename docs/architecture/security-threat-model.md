# Security Threat Model

## Primary Risks

1. Tenant data leakage.
2. OTP brute force or OTP disclosure.
3. Public exposure of uploaded visitor photos.
4. Access to another lead's generated assets by UUID guessing.
5. External resource injection through tenant theme content.
6. Accidental calls to real or external AI services during MVP.
7. Admin CRM notes leaking across tenants.
8. Landing page visit analytics leaking visitor-identifying data.

## Tenant Isolation

Controls:

- Resolve tenant from request host only.
- Add `tenant_id` to every tenant-owned record.
- Scope every public query by current tenant.
- Scope visitor resources by current session lead after OTP verification.
- Scope future admin CRM notes by tenant and lead.
- Unknown hosts return 404.
- Landing page visit tracking stores hashes for IP/user-agent data.

Tests must verify tenant isolation after every relevant change.

## OTP

Controls:

- Store only `code_hash`.
- Never store plain OTP codes.
- Rate-limit OTP requests.
- Track failed attempts.
- Accept only Persian, Arabic, or English digits for phone and OTP fields.
- Normalize accepted digits before hashing, comparison, rate limiting, and lead
  storage.
- Do not expose debug OTP codes outside debug/development mode.

## Storage

Controls:

- Store uploads under `storage/app/private`.
- Store generated assets under `storage/app/private/tenants/...`.
- Do not expose private paths to API clients.
- Stream assets through `/api/v1/assets/{uuid}` only after tenant and lead checks.

## External Resources

Controls:

- No frontend CDN for CSS, JS, icons, or fonts.
- Vazirmatn is embedded in the repository.
- Sample images are local private files.
- Tenant logos and theme images may be loaded from the primary HairVision domain.
- Do not allow arbitrary external script/style URLs in tenant theme settings.
- Tenant header/footer HTML is controlled content and must not include arbitrary
  scripts.

## AI Providers

Controls:

- Version `1.0.0` uses `SampleImageProvider` only.
- `SampleImageProvider` must not call HTTP, AI APIs, or image generation services.
- Future providers must live behind `ImageGenerationProvider`.
- Provider errors must be logged and stored without exposing raw details to visitors.

## Required Security Tests

- Unknown host returns 404.
- Tenant B cannot access Tenant A assets.
- Upload requires verified lead session.
- Generation requires current tenant and current lead.
- Asset route requires current tenant and current lead.
- Landing page visit tracking stores `tenant_id` and does not create a lead.
- OTP request stores only a hash.
- Sample generation sends no external HTTP requests.
