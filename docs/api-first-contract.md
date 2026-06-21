# API First Contract

## Principle

All core behavior must go through the API, even when the current UI is Blade.
Blade may render a view shell, tenant content, and client-side JavaScript, but it
must call `/api/v1` for OTP, upload, generation, status, results, and asset
loading.

## Base URL

```text
/api/v1
```

The tenant is resolved from the request host for every public API request.

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

## Public APIs

### Tenant Bootstrap

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

### OTP Request

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

Development/debug responses may include `debug_sms`; production responses must
not expose OTP codes.

Rate limit failures return `429`.

### OTP Verify

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

`code` must contain exactly 6 digits. Persian, Arabic, and English digit
scripts are accepted; all other characters are rejected before verification.

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
session. Visitor-facing UI should render Persian digits for Persian pages.

Verified leads also have CRM-ready fields such as `crm_stage` and
`crm_last_note_at`. Admin APIs for editing these fields are future work; the data
structure exists in version `1.0.0`.

### Upload Image

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

### Create Generation

Requires a verified lead session. The upload must belong to the current tenant
and the current session lead.

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
    "status": "completed"
  },
  "jobs": [
    {"id": "uuid", "period": "3_months", "status": "completed"},
    {"id": "uuid", "period": "6_months", "status": "completed"},
    {"id": "uuid", "period": "12_months", "status": "completed"}
  ]
}
```

In debug/local sample mode the group may be completed immediately. The UI should
still show a short progress cast before revealing results.

### Generation Status

Requires a verified lead session.

```http
GET /api/v1/generations/{generation_group_id}/status
```

Response:

```json
{
  "status": "completed",
  "jobs": [
    {"period": "3_months", "status": "completed"},
    {"period": "6_months", "status": "completed"},
    {"period": "12_months", "status": "completed"}
  ]
}
```

### Results

Requires a verified lead session.

```http
GET /api/v1/generations/{generation_group_id}/results
```

Response:

```json
{
  "success": true,
  "results": [
    {"period": "3_months", "url": "/api/v1/assets/uuid"},
    {"period": "6_months", "url": "/api/v1/assets/uuid"},
    {"period": "12_months", "url": "/api/v1/assets/uuid"}
  ],
  "disclaimer": "These images are simulations and do not guarantee treatment results."
}
```

### Asset

Requires a verified lead session. The asset must belong to the current tenant and
current session lead.

```http
GET /api/v1/assets/{uuid}
```

Returns the image bytes with an image content type. Storage paths are never
returned to the client.

## Versioning Rules

- Do not make breaking changes inside `/api/v1`.
- Add `/api/v2` for breaking response or behavior changes.
- Adding fields is allowed.
- Removing fields or changing field meaning requires a new API version.
