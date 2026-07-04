# Architecture

HairVision `1.0.0` is a Laravel 13 multi-tenant SaaS MVP for hair transplant
clinics. The current architecture proves the public visitor flow with a local
sample-image provider before any real AI provider is introduced.

## Runtime Shape

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

Blade renders the public tenant shell and calls `/api/v1` for all product
behavior. Controllers must validate requests, call services or jobs, and return
JSON or a view shell. Provider logic must stay behind services, jobs, and
provider adapters.

The dashboard uses Laravel session authentication under `/admin`:

```text
Browser
  -> RTL Blade admin dashboard
  -> Laravel session auth
  -> Admin Controller
  -> Policy / Tenant Context
  -> Service
  -> Tenant-scoped Eloquent query
```

Dashboard OTP login is separate from the public verified-lead session flow.
The three authentication surfaces use separate session cookies: public lead
generation APIs use the lead cookie, clinic dashboards use the clinic admin
cookie on tenant hosts, and the platform dashboard uses the platform admin
cookie on the primary host. This prevents `/admin` login, logout, or session
regeneration from invalidating a visitor lead session in the same browser.

## Multi-Tenancy

Tenant identity is resolved only from the request host. Never accept `tenant_id`,
tenant slug, or tenant domain from visitor-controlled request bodies as authority
for data access.

Tenant domain lookup:

1. Read `$request->getHost()`.
2. Lowercase the host.
3. Find an active `tenant_domains` row.
4. Ensure the related tenant is active.
5. Store the tenant in the service container as `currentTenant`.
6. Store the tenant on request attributes.
7. Abort with `404` when host or tenant is not active.

Local development may fall back from `localhost` or `127.0.0.1` to the first
active tenant, but tests and production should use explicit tenant hosts.

Every tenant-owned public query must include:

```php
where('tenant_id', $tenant->id)
```

Visitor-specific resources after OTP verification must also include:

```php
where('lead_id', $sessionLeadId)
```

This applies to uploads, generation groups, generation jobs, generated assets,
tenant AI settings, tenant theme settings, CRM notes, and audit logs.

## AI Provider Adapter Layer

HairVision must not be tightly coupled to one AI model or vendor. Tenant and job
configuration select a provider through an adapter layer.

Required provider flow:

```text
Controller
  -> GenerationService
  -> ServiceEntitlementService active subscription/service credit check
  -> AiModelManager active clinic assignment lookup
  -> Dispatch Job
  -> Job
  -> RunAiModelAction credit/log guard
  -> ProviderResolver
  -> ImageGenerationProvider interface
  -> CreditLedgerService service credit consumption after success
  -> Provider implementation
```

Controllers must never call provider classes directly.

Potential future provider codes:

```text
sample_image
gapgpt
nano_banana
openai
replicate
custom_http
```

`sample_image` remains the protected default provider for version `1.0.0`.
GapGPT image edits are available only when a system-managed `gapgpt` AI model
access record is active, assigned to the clinic, and configured with encrypted
credentials.

GapGPT image edits call `POST https://api.gapgpt.app/v1/images/edits` as
multipart form data through Laravel's HTTP client. The request uses
`Authorization: Bearer <GAPGPT_API_KEY>`, `Accept: application/json`, file field
`image`, and text fields `model = gpt-image-2`, `prompt`, `n = 1`,
`response_format = url`, and `size = 1024x1024`. Do not send this request as
JSON and do not manually force `Content-Type: application/json`.

Provider selection target:

```text
job override
  -> lead override
  -> tenant default provider
  -> system default provider
  -> sample_image
```

Current `1.0.0` behavior resolves the tenant default when available and falls
back to `sample_image`.

AI model access management adds a database-backed access layer before generation
jobs are created. Public generation resolves the current clinic from the host and
then selects an active assigned access record for `hair_transplant`. Unassigned,
inactive, or credit-finished records are rejected before provider execution.

SaaS plan access is checked before AI model access. Public generation requires an
active clinic base subscription, an active service entitlement for
`hair_transplant`, and enough remaining service credit for the three period jobs.
Service credit is consumed per completed job through `CreditLedgerService`; a
failed provider response does not consume service credit.

Each generation stage is a separate job:

- `two_weeks`
- `intermediate_regrowth`
- `final_result`

Only the first pending stage is queued when the group is created. A completed
stage saves its private asset and then dispatches the next stage, so the public
UI can reveal each image as soon as it is ready while later stages continue.
This keeps retries, failures, timing, and provider costs isolated per stage.

## Sample Image Provider

The MVP provider is:

```text
App\Services\AI\Providers\SampleImageProvider
```

`SampleImageProvider` must:

1. Receive a generation request.
2. Resolve the configured sample image for the requested period.
3. Return a completed local-path response when the sample exists.
4. Return a failed response when no sample is available.
5. Never call the internet.
6. Never call external AI APIs.
7. Never generate or modify images.
8. Never use random external image URLs.

Configured default sample paths:

```text
storage/app/private/sample-results/two-weeks.jpg -> two_weeks
storage/app/private/sample-results/intermediate_regrowth.jpg -> intermediate_regrowth
storage/app/private/sample-results/final_result.jpg -> final_result
```

Legacy environment variables remain supported for the sample files:

```text
storage/app/private/sample-results/two-weeks.jpg
storage/app/private/sample-results/intermediate_regrowth.jpg
storage/app/private/sample-results/final_result.jpg
```

Environment variables:

```env
HAIRVISION_DEFAULT_AI_PROVIDER=sample_image
HAIRVISION_SAMPLE_IMAGE_3_MONTHS=sample-results/two-weeks.jpg
HAIRVISION_SAMPLE_IMAGE_6_MONTHS=sample-results/intermediate_regrowth.jpg
HAIRVISION_SAMPLE_IMAGE_12_MONTHS=sample-results/final_result.jpg
HAIRVISION_SAMPLE_IMAGE_FALLBACK=sample-results/hairvision-sample-result.jpg
```

Generated output path:

```text
storage/app/private/tenants/{tenant_uuid}/leads/{lead_uuid}/results/{generation_group_uuid}/{period}.jpg
```

The generated asset row must include `tenant_id`, `lead_id`,
`generation_group_id`, `generation_job_id`, `period`, and private disk/path
metadata.

## Security Controls

Primary risks:

1. Tenant data leakage.
2. OTP brute force or OTP disclosure.
3. Public exposure of uploaded visitor photos.
4. Access to another lead's generated assets by UUID guessing.
5. External resource injection through tenant theme content.
6. Accidental calls to real or external AI services during MVP.
7. Admin CRM notes leaking across tenants.
8. Landing page visit analytics leaking visitor-identifying data.

Controls:

- Resolve tenant from request host only.
- Add `tenant_id` to every tenant-owned record.
- Scope public queries by current tenant.
- Scope visitor resources by current session lead after OTP verification.
- Store only OTP hashes.
- Rate-limit OTP requests and track failed attempts.
- Store uploads and generated assets under `storage/app/private`.
- Stream private assets only through scoped API routes.
- Do not expose storage paths to API clients.
- Use local frontend assets only; no CDN dependencies.
- Keep `SampleImageProvider` local and network-free.
- Store provider errors internally without exposing raw details to visitors.

Dashboard controls:

- Authenticate dashboard users with mobile OTP and Laravel sessions.
- Store admin OTP rows with `purpose = admin_login`.
- Keep OTP codes hashed, expiring, single-use, and attempt-limited.
- Store active dashboard tenant context in session only after membership checks.
- Enforce lead access through `LeadPolicy` and tenant-scoped queries.
- Allow super admin cross-tenant access only through explicit role checks.

## WHM/cPanel Production Shape

Recommended production deployment:

```text
Nginx/Apache vhost
  -> Laravel public/index.php
  -> Laravel app
  -> MySQL/MariaDB
  -> Redis
  -> Horizon worker process
  -> storage/app/private
```

Each tenant domain or subdomain points to the same Laravel app. Laravel resolves
the tenant from `tenant_domains.domain`.

Production queue-heavy work must use:

```env
QUEUE_CONNECTION=redis
```

Run Horizon as a managed process through Supervisor, systemd, or the hosting
panel process manager.

## Required Architecture Tests

- Known tenant host resolves successfully.
- Unknown host returns `404`.
- Tenant B cannot access Tenant A uploads, generation groups, assets, or notes.
- A visitor session for one lead cannot access another lead's assets.
- Upload requires a verified lead session.
- Generation requires current tenant and current lead.
- Asset route requires current tenant and current lead.
- OTP request stores only a hash.
- Sample generation sends no external HTTP requests.
