# API Documentation

All core behavior must go through the API, even when the current UI is Blade.
Blade may render a view shell, tenant content, and client-side JavaScript, but it
must call `/api/v1` for OTP, upload, generation, status, results, and asset
loading.

## Base URL

```text
/api/v1
```

The tenant is resolved from the request host for every public API request.
Admin dashboard routes under `/admin` are session-authenticated web routes, not
public API endpoints.

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

## Date Format

User-facing API responses and dashboard-backed filters use Jalali/Shamsi dates.
Business date-time values use:

```text
1405/01/31 23:59:59
```

Date-only filters use:

```text
1405/01/31
```

Persian, Arabic, and Latin digits are accepted and normalized server-side.
Technical/internal timestamps may remain Gregorian internally, but must be
converted before being displayed to users.

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
    "ai_provider": "sample_image",
    "upload_enabled": true
  }
}
```

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

`phone` must contain exactly 11 digits. Persian, Arabic, and English digit
scripts are accepted; all other characters are rejected. The backend normalizes
accepted digits before rate limiting and storage.

Response:

```json
{
  "success": true,
  "message": "کد تایید ارسال شد.",
  "expires_in": 120
}
```

OTP responses never include the plain OTP code. Codes are sent through the
configured system IPPanel gateway. Rate limit failures return `429`.

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

`code` must contain exactly 6 digits. Persian, Arabic, and English digit scripts
are accepted; all other characters are rejected before verification.

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

The API stores normalized phone digits and the verified lead id in the visitor
session. Verified leads also have CRM-ready fields such as `crm_stage` and
`crm_last_note_at`.

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

## Create Generation

Requires a verified lead session. The upload must belong to the current tenant
and the current session lead.

The backend requires an active base subscription for the current clinic, an
active `hair_transplant` service entitlement, and enough remaining service credit
for the three generated periods. Credit is consumed per completed period after
provider execution succeeds.

The backend also requires an active AI model access record assigned to the
current clinic for `hair_transplant`. The client does not submit `tenant_id` or
provider credentials.

```http
POST /api/v1/generations
```

Body:

```json
{
  "upload_id": "uuid"
}
```

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
    "label": "دو هفته بعد از کاشت",
    "status": "pending"
  },
  "stages": [
    {"key": "two_weeks", "order": 1, "label": "دو هفته بعد از کاشت", "caption": "آثار ترمیم اولیه و خط رویش تازه"},
    {"key": "intermediate_regrowth", "order": 2, "label": "مرحله رشد میانی", "caption": "رشد تدریجی و تراکم در حال شکل‌گیری"},
    {"key": "final_result", "order": 3, "label": "نتیجه نهایی", "caption": "پوشش کامل‌تر و ظاهر طبیعی‌تر"}
  ],
  "jobs": [
    {"id": "uuid", "period": "two_weeks", "key": "two_weeks", "label": "دو هفته بعد از کاشت", "status": "pending", "asset_url": null},
    {"id": "uuid", "period": "intermediate_regrowth", "key": "intermediate_regrowth", "label": "مرحله رشد میانی", "status": "pending", "asset_url": null},
    {"id": "uuid", "period": "final_result", "key": "final_result", "label": "نتیجه نهایی", "status": "pending", "asset_url": null}
  ]
}
```

The backend queues only the first pending stage. Each completed stage stores a
private asset and queues the next stage. Clients should poll the status endpoint
and reveal each job as soon as `asset_url` is present. Re-posting the same
`upload_id` returns the existing group instead of creating duplicate jobs.

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
    "label": "مرحله رشد میانی",
    "status": "processing"
  },
  "stages": [
    {"key": "two_weeks", "order": 1, "label": "دو هفته بعد از کاشت", "caption": "آثار ترمیم اولیه و خط رویش تازه"}
  ],
  "jobs": [
    {"period": "two_weeks", "status": "completed", "asset_url": "/api/v1/assets/uuid"},
    {"period": "intermediate_regrowth", "status": "processing", "asset_url": null},
    {"period": "final_result", "status": "pending", "asset_url": null}
  ]
}
```

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
    {"period": "two_weeks", "label": "دو هفته بعد از کاشت", "url": "/api/v1/assets/uuid"},
    {"period": "intermediate_regrowth", "label": "مرحله رشد میانی", "url": "/api/v1/assets/uuid"},
    {"period": "final_result", "label": "نتیجه نهایی", "url": "/api/v1/assets/uuid"}
  ],
  "disclaimer": "These images are simulations and do not guarantee treatment results."
}
```

## Asset

Requires a verified lead session. The asset must belong to the current tenant
and current session lead.

```http
GET /api/v1/assets/{uuid}
```

Returns the image bytes with an image content type. Storage paths are never
returned to the client.

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

Standard JSON pagination shape:

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

Page-specific filters such as `clinic_id`, `status`, `role`,
`owner_scope`, or `provider_code` are accepted only by endpoints that own those
concepts. Clinic filters are checked against the authenticated user's allowed
clinics before they affect a query.

## Versioning Rules

- Do not make breaking changes inside `/api/v1`.
- Add `/api/v2` for breaking response or behavior changes.
- Adding fields is allowed.
- Removing fields or changing field meaning requires a new API version.
