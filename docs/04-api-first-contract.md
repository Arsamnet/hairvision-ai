# 04 - API First Contract

## اصل معماری

همه عملیات اصلی باید از API انجام شود، حتی اگر UI فعلی Blade باشد.

Blade نباید منطق تجاری را مستقیم اجرا کند. Blade باید فقط view shell باشد و با fetch یا axios به API وصل شود.

## Base URL

```text
/api/v1
```

## Headerهای استاندارد

برای همه درخواست‌های JSON:

```http
Accept: application/json
Content-Type: application/json
X-Requested-With: XMLHttpRequest
```

برای upload:

```http
Accept: application/json
Content-Type: multipart/form-data
```

## Public APIs

### Tenant bootstrap

```http
GET /api/v1/bootstrap
```

خروجی:

```json
{
  "tenant": {
    "name": "Clinic Name",
    "slug": "clinic-name",
    "theme": {
      "primary_color": "#16a34a",
      "logo_url": "...",
      "template": "classic"
    }
  },
  "features": {
    "ai_provider": "sample_image",
    "upload_enabled": true
  }
}
```

### OTP request

```http
POST /api/v1/auth/otp/request
```

Body:

```json
{
  "phone": "09123456789"
}
```

Response:

```json
{
  "message": "OTP sent",
  "expires_in": 120
}
```

### OTP verify

```http
POST /api/v1/auth/otp/verify
```

Body:

```json
{
  "phone": "09123456789",
  "code": "123456"
}
```

Response:

```json
{
  "lead": {
    "id": "uuid",
    "phone": "09123456789"
  },
  "message": "Phone verified"
}
```

### Upload image

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
  "upload": {
    "id": "uuid",
    "status": "validated"
  }
}
```

### Create generation

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
  "generation_group_id": "uuid",
  "jobs": [
    {"id": "uuid", "period": "3_months", "status": "pending"},
    {"id": "uuid", "period": "6_months", "status": "pending"},
    {"id": "uuid", "period": "12_months", "status": "pending"}
  ]
}
```

### Generation status

```http
GET /api/v1/generations/{generation_group_id}/status
```

Response:

```json
{
  "status": "processing",
  "jobs": [
    {"period": "3_months", "status": "completed"},
    {"period": "6_months", "status": "processing"},
    {"period": "12_months", "status": "pending"}
  ]
}
```

### Results

```http
GET /api/v1/generations/{generation_group_id}/results
```

Response:

```json
{
  "assets": [
    {"period": "3_months", "url": "/api/v1/assets/signed/..."},
    {"period": "6_months", "url": "/api/v1/assets/signed/..."},
    {"period": "12_months", "url": "/api/v1/assets/signed/..."}
  ],
  "disclaimer": "این تصاویر شبیه‌سازی هستند و نتیجه واقعی درمان را تضمین نمی‌کنند."
}
```

## Admin APIs

```http
GET /api/v1/admin/dashboard
GET /api/v1/admin/leads
GET /api/v1/admin/leads/{id}
GET /api/v1/admin/generation-jobs
POST /api/v1/admin/settings/theme
POST /api/v1/admin/settings/ai-provider
```

## Versioning Rules

- هیچ breaking change در `/api/v1` بدون ایجاد `/api/v2` انجام نشود.
- responseها باید stable باشند.
- فیلد جدید قابل اضافه شدن است.
- حذف یا تغییر معنی فیلد ممنوع است مگر با نسخه جدید API.
