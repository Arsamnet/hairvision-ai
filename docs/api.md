# API Reference

Version: [`2.0.3`](../CHANGELOG.md#203)

All public product behavior goes through `/api/v1`, even when the UI is Blade.
Blade may render the tenant shell and client-side interactions, but OTP, upload,
generation, status, results, and asset loading must call the API.

## Base URL

```text
/api/v1
```

The current tenant is resolved from the request host for every public API
request. Clients never submit tenant identity as authority.

## Standard Headers

JSON:

```http
Accept: application/json
Content-Type: application/json
X-Requested-With: XMLHttpRequest
```

Upload:

```http
Accept: application/json
Content-Type: multipart/form-data
```

## Date and Digit Rules

User-facing API responses and dashboard-backed filters use Jalali/Shamsi dates.
Business date-time values use:

```text
1405/01/31 23:59:59
```

Date-only filters use:

```text
1405/01/31
```

Persian, Arabic, and Latin digits are accepted for supported numeric inputs and
normalized server-side.
Valid comma-grouped numeric strings are also accepted and are normalized to an
unformatted Latin representation before validation. Sensitive credential,
token, secret, merchant, and password values are never modified by numeric
normalization.

## Tenant Bootstrap

```http
GET /api/v1/bootstrap
```

Response:

```json
{
  "tenant": {
    "name": "Clinic Name",
    "slug": "clinic-name",
    "theme": {
      "primary_color": "#0f766e",
      "logo_url": null,
      "template": "classic"
    }
  },
  "features": {
    "upload_enabled": true
  }
}
```

In version `2.0.0`, category metadata is source-of-truth in
`ai_model_categories`. Public clients should use profile category endpoints
after verification for requestable categories and outputs instead of hard-coding
the historic three hair-transplant stages. Provider and model identifiers are
not public feature metadata; the server selects from active model access records
assigned to the current clinic by a system administrator.

In version `2.0.0`, `tenant.theme` contains the effective managed landing
presentation. The `template` field is the resolved template code and
`primary_color` follows the same fallback rules as the Blade landing shell.
Clients cannot submit tenant or template identity to override this response.

## OTP Request

```http
POST /api/v1/auth/otp/request
```

Body:

```json
{
  "phone": "۰۹۱۲۳۴۵۶۷۸۹"
}
```

`phone` must contain exactly 11 supported digits. Other characters are rejected.
The backend normalizes accepted digits before rate limiting and storage.

Response:

```json
{
  "success": true,
  "message": "کد تایید ارسال شد.",
  "expires_in": 120
}
```

OTP responses never include the plain OTP code. Rate limit failures return
`429`. IPPanel delivery makes at least three bounded attempts for connection,
timeout, and rejected-response failures. If every attempt fails, the response
contains only a localized generic message; provider identity, failure details,
credentials, OTP values, and raw responses are never exposed.

## OTP Verify

```http
POST /api/v1/auth/otp/verify
```

Body:

```json
{
  "phone": "۰۹۱۲۳۴۵۶۷۸۹",
  "code": "۱۲۳۴۵۶"
}
```

`code` must contain exactly 6 supported digits.

Response:

```json
{
  "success": true,
  "lead": {
    "id": "uuid",
    "phone": "09123456789"
  },
  "message": "شماره موبایل تایید شد."
}
```

The API stores the normalized phone and verified lead id in the visitor session.

## Upload Image

Requires a verified lead session.

```http
POST /api/v1/uploads
```

FormData:

```text
image: file
consent: true
```

Response:

```json
{
  "success": true,
  "upload": {
    "id": "uuid",
    "status": "validated"
  }
}
```

The file is stored privately and scoped to the current tenant and lead.
The original upload remains unchanged. For seeded/new models, the private copy
sent to the provider defaults to JPEG quality 85 with an exact 800-pixel longest
side; model-level settings may explicitly override or disable this preparation.

## Profile

Requires a verified lead session.

```http
GET /api/v1/profile
```

Returns the current tenant-scoped lead profile and current source image, if one
exists.

```http
POST /api/v1/profile/source-image
```

FormData:

```text
image: file
consent: true
```

Uploading a new source image archives the previous current source image for the
same `(tenant_id, lead_id)` and makes the new image current.

```http
GET /api/v1/profile/categories
GET /api/v1/profile/generations
```

Categories are the tenant-visible AI applications that pass the same runtime
availability gate as `/api/v1/bootstrap`: active category, active purchased
base plan, active entitlement, active system model access, active clinic model
assignment, and at least one active executable output config. Disabling a
category, a purchased plan, a global AI model access record, or the clinic's
assignment removes the affected application from public category responses.
Generation history is scoped to the current tenant and verified lead. Category
outputs include public metadata such as code, title, description, order and
credit cost; server-side prompts are never returned by public profile APIs.

## Create Generation

Requires a verified lead session. The upload must belong to the current tenant
and session lead.

Version `2.0.0` supports independent output generation from the current profile
source image:

```http
POST /api/v1/generations
POST /api/v1/generations/independent
Idempotency-Key: client-generated-uuid
```

`POST /api/v1/generations` accepts this payload for compatibility with the
public generation entrypoint; `/generations/independent` is the explicit v2
alias.

Body:

```json
{
  "source_image_id": "uuid",
  "category_id": "uuid",
  "output_id": "uuid",
  "idempotency_key": "client-generated-uuid"
}
```

The client must not submit `tenant_id`, `model_id`, provider, prompt,
credential, or credit cost. The response contains one persistent generation
request with a queued attempt. The backend snapshots and uses the prompt stored
on the selected category output. Repeating the same idempotency key in the same
tenant/lead scope returns the same generation request.

Every successful generated image is normalized before permanent private
storage to JPEG quality 85 with an exact 800-pixel longest side, without crop or
aspect-ratio distortion. Stream responses therefore use `image/jpeg` regardless
of the raw provider output format.

Creation also reserves the output credit atomically. An attempt remains in the
database FIFO as `queued` until its selected model has a free concurrency slot;
only then is it transported to the database queue. Explicit provider `429`
responses may produce a delayed follow-up attempt while the public generation
request remains `queued`. Ambiguous post-call failures are not retried
automatically.

```http
GET /api/v1/generations/independent/{generation}
POST /api/v1/generations/independent/{generation}/regenerate
GET /api/v1/generations/{generation}
POST /api/v1/generations/{generation}/regenerate
GET /api/v1/assets/{asset}/stream
```

The legacy `upload_id` payload remains supported for compatibility with the v1
staged group flow.

For independent v2 generation, the backend requires an active base subscription
whose purchased plan is still active, an active entitlement for the selected
category, an active category/application, an active system model access assigned
to the current clinic, enough remaining credit for the selected output's
`credit_cost`, a current tenant/lead-scoped source image, and an eligible active
model/output config.

For the legacy staged group flow, the backend requires an active base
subscription, active service entitlement for the selected usage category, enough
service credit for the selected stage count, and active assigned AI model
access. The client submits only the upload id and the allowed usage category
key, never tenant, provider, credential, or model authority.

```http
POST /api/v1/generations
```

Body:

```json
{
  "upload_id": "uuid",
  "usage_category_key": "hair_transplant"
}
```

`usage_category_key` is optional and defaults to `hair_transplant`. Allowed
public values are:

- `hair_transplant`: creates three staged jobs.
- `eyebrow_transplant`: creates one final-result job.

Response:

```json
{
  "success": true,
  "generation_group": {
    "id": "uuid",
    "status": "processing"
  },
  "current_stage": {
    "key": "two_weeks",
    "label": "یک روز بعد از کاشت مو",
    "status": "pending"
  },
  "stages": [
    {
      "key": "two_weeks",
      "order": 1,
      "label": "یک روز بعد از کاشت مو",
      "caption": "نمای طبیعی روز اول با گرافت‌های تازه، قرمزی ملایم و ریزدلمه‌های قابل‌انتظار"
    },
    {
      "key": "intermediate_regrowth",
      "order": 2,
      "label": "۶ ماه بعد از کاشت مو",
      "caption": "رشد میانی شش‌ماهه با خط رویش شکل‌گرفته، تراکم ناکامل و نمایان بودن طبیعی بخشی از پوست سر"
    },
    {
      "key": "final_result",
      "order": 3,
      "label": "یک سال بعد از کاشت مو",
      "caption": "نتیجه کامل یک‌ساله با خط رویش طبیعی، پوشش متناسب و تراکم باورپذیر"
    }
  ],
  "jobs": [
    {
      "id": "uuid",
      "period": "two_weeks",
      "key": "two_weeks",
      "label": "یک روز بعد از کاشت مو",
      "status": "pending",
      "asset_url": null
    }
  ]
}
```

Re-posting the same `upload_id` returns the existing group instead of creating
duplicate jobs.

## Generation Status

Requires a verified lead session.

```http
GET /api/v1/generations/{generation_group_id}/status
```

Response:

```json
{
  "status": "processing",
  "current_stage": {
    "key": "intermediate_regrowth",
    "label": "۶ ماه بعد از کاشت مو",
    "status": "processing"
  },
  "jobs": [
    {
      "period": "two_weeks",
      "status": "completed",
      "asset_url": "/api/v1/assets/uuid"
    },
    {
      "period": "intermediate_regrowth",
      "status": "processing",
      "asset_url": null
    },
    {
      "period": "final_result",
      "status": "pending",
      "asset_url": null
    }
  ]
}
```

Clients should reveal each job as soon as `asset_url` is present.

## Results

Requires a verified lead session.

```http
GET /api/v1/generations/{generation_group_id}/results
```

Response:

```json
{
  "success": true,
  "results": [
    {
      "period": "two_weeks",
      "label": "یک روز بعد از کاشت مو",
      "url": "/api/v1/assets/uuid"
    },
    {
      "period": "intermediate_regrowth",
      "label": "۶ ماه بعد از کاشت مو",
      "url": "/api/v1/assets/uuid"
    },
    {
      "period": "final_result",
      "label": "یک سال بعد از کاشت مو",
      "url": "/api/v1/assets/uuid"
    }
  ],
  "disclaimer": "These images are simulations and do not guarantee treatment results."
}
```

## Asset Stream

Requires a verified lead session.

```http
GET /api/v1/assets/{uuid}
GET /api/v1/assets/{uuid}/stream
```

Returns image bytes with an image content type. The asset must belong to the
current tenant and session lead. Storage paths are never returned to clients.

## Reusable Pagination

List-style dashboard/API responses should use the shared pagination helpers when
JSON pagination is needed.

Supported parameters:

```text
page
per_page
search
sort
direction
```

The backend enforces `HAIRVISION_API_MAX_PER_PAGE`.

Standard JSON shape:

```json
{
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 0,
    "last_page": 1,
    "from": null,
    "to": null
  }
}
```

Page-specific filters such as `clinic_id`, `status`, `role`, `owner_scope`, or
`provider_code` are accepted only by endpoints that own those concepts.

## Versioning Rules

- Do not make breaking changes inside `/api/v1`.
- Adding response fields is allowed.
- Removing fields or changing field meaning requires `/api/v2`.
- Dashboard web routes under `/admin` are session-authenticated routes, not
  public visitor API endpoints.
- Source changes without an assigned release remain under `Unreleased` in the
  changelog and move to a numbered version only during release preparation.

## Dashboard Landing Template Routes

Landing template management is intentionally dashboard-only and requires the
matching system permissions:

```text
GET    /admin/settings/templates
GET    /admin/settings/templates/create
POST   /admin/settings/templates
GET    /admin/settings/templates/clinics
PUT    /admin/settings/templates/clinics/{clinic}
GET    /admin/settings/templates/{landingTemplate}/edit
PUT    /admin/settings/templates/{landingTemplate}
PATCH  /admin/settings/templates/{landingTemplate}/default
DELETE /admin/settings/templates/{landingTemplate}
```

These routes edit trusted administrator-authored HTML and assignments. They are
not public API contracts and must never be exposed to visitor sessions.

## Dashboard Guide Routes

The employee guide is dashboard-only and has no public `/api/v1` endpoint:

```text
GET /admin/help/guide
GET /admin/help/faq
```

Every authenticated dashboard role may read these routes, even when clinic plan
access is inactive. It reads system-owned relational guide records and renders
clinic-user content on two linked pages: a categorized guide with ordered steps
and a separate indexed FAQ with accessible native accordions.

Guide management is restricted to `guide.manage`:

```text
GET /admin/settings/guide
PUT /admin/settings/guide
POST /admin/settings/guide/sections
PUT|PATCH|DELETE /admin/settings/guide/sections/{guideSection}[...]
POST /admin/settings/guide/sections/{guideSection}/steps
PUT|PATCH|DELETE /admin/settings/guide/steps/{guideStep}[...]
GET /admin/settings/faqs
PUT /admin/settings/faqs
POST /admin/settings/faqs/items
PUT|PATCH|DELETE /admin/settings/faqs/items/{guideFaq}[...]
```

These dashboard-only forms mutate one page or record at a time. Page payloads
contain `title` and `introduction`; section payloads contain `title`, optional
`audience`, and `description`; steps contain `title` and `body`; FAQs contain
`question` and `answer`; move operations accept `direction=up|down`. Labels
remain escaped plain text. Rich fields are edited visually or in a source view
and are sanitized on the server before storage and rendering. Only the
documented small formatting allowlist and safe link schemes survive.

## System AI Category APIs

Authenticated system APIs exist under `/api/v1/system` and require the matching
dashboard permissions:

```http
GET    /api/v1/system/ai-model-types
GET    /api/v1/system/ai-model-types/{type}
GET    /api/v1/system/ai-model-categories
POST   /api/v1/system/ai-model-categories
GET    /api/v1/system/ai-model-categories/{category}
PATCH  /api/v1/system/ai-model-categories/{category}
DELETE /api/v1/system/ai-model-categories/{category}
GET    /api/v1/system/ai-model-categories/{category}/outputs
POST   /api/v1/system/ai-model-categories/{category}/outputs
GET    /api/v1/system/ai-model-categories/{category}/outputs/{output}
PATCH  /api/v1/system/ai-model-categories/{category}/outputs/{output}
DELETE /api/v1/system/ai-model-categories/{category}/outputs/{output}
GET    /api/v1/system/ai-models
POST   /api/v1/system/ai-models
GET    /api/v1/system/ai-models/{model}
DELETE /api/v1/system/ai-models/{model}
POST   /api/v1/system/ai-models/{model}/activate
POST   /api/v1/system/ai-models/{model}/pause
```

The visible dashboard AI-application CRUD lives under
`/admin/settings/ai-applications`. The dashboard renders applications as table
rows with view/edit actions and one lifecycle action. Unused categories may be
deleted. Categories referenced by models, plans, subscriptions, credit ledger
rows, or generation requests must be archived instead of deleted so historical
records keep their stable category code.

Dashboard-only category pages:

```text
GET /admin/settings/ai-applications/{category}
```

The category view contains the outputs table and output create/edit modals;
there is no separate dashboard page for category outputs.

Plan management is also dashboard-only. A plan has one total `usage_limit` and
one or more selected `service_keys`/category codes. Public generation APIs still
receive only category/output identifiers; they never receive plan package,
credit bucket, provider, model, prompt, or credentials. Base-plan and
service-add-on forms require exactly one executable model per selected category;
that model must individually cover all active outputs of the category.

Model settings exposed by the system type registry include the optional shared
input-image processing fields:

```json
{
  "input_image_processing_enabled": true,
  "input_image_max_dimension": 800,
  "input_image_jpeg_quality": 85
}
```

When processing is enabled, dimension and quality are both required. Dimension
accepts `1..8192` and quality accepts `1..100`. These are system-admin model
settings; public generation clients cannot submit or override them. Values
shown above are the defaults used by new and seeded model accesses. Generated
result storage always uses the 800px/JPEG 85 product profile.

The `openai` type returned by `GET /api/v1/system/ai-model-types/openai`
publishes the following non-secret model defaults; `api_key` is a required
secret credential and is never returned:

```json
{
  "endpoint": "https://api.openai.com/v1/images/edits",
  "model": "gpt-image-1.5",
  "quality": "low",
  "size": "816x816",
  "output_format": "jpeg",
  "background": "opaque",
  "moderation": "auto",
  "output_compression": 70,
  "timeout_seconds": 900,
  "concurrency_limit": 1
}
```

`quality` accepts `low`, `medium`, or `high`; `output_format` accepts `jpeg`,
`png`, or `webp`; and output compression accepts `0..100`. Every OpenAI output
configuration has one required `prompt`, populated from the selected
application output by the seeded legacy-to-v2 bridge. Provider calls send the
private image as multipart `image[]`, fix `n` to `1`, and decode
`data[0].b64_json`. Public APIs never accept or expose these settings.

The product seed keeps `816x816` as its configurable default. The current
official OpenAI endpoint documents only `auto`, `1024x1024`, `1536x1024`, and
`1024x1536`, so direct `api.openai.com` deployments must change `size` to a
supported upstream value before enabling the model.
