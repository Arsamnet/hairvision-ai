# Jalali Dates

HairVision stores and displays user-facing business dates with the Jalali/Shamsi
calendar for version `1.0.0`.

## Central Utility

All conversion, validation, and digit normalization must go through:

```php
App\Support\JalaliDate
```

Do not duplicate calendar conversion logic in controllers, models, Blade views,
JavaScript, tests, or services. Use the provided validation rules for user input:

- `App\Rules\JalaliDate`
- `App\Rules\JalaliDateTime`

Use the Blade components for display:

- `<x-jalali-date :value="$value" />`
- `<x-jalali-date-time :value="$value" />`

## Storage Rules

Business/user-facing date-time fields are stored as zero-padded Jalali strings:

```text
YYYY/MM/DD HH:mm:ss
1405/01/31 23:59:59
```

Business date-time columns use `char(19)` so lexicographic sorting and filtering
remain correct when values are zero-padded.

Current Jalali business date-time columns:

- `lead_notes.next_follow_up_at`
- `lead_notes.completed_at`
- `invoices.due_at`
- `invoices.paid_at`
- `invoices.expires_at`
- `invoices.activated_at`
- `payments.verified_at`
- `subscriptions.starts_at`
- `subscriptions.ends_at`
- `subscription_service_entitlements.starts_at`
- `subscription_service_entitlements.ends_at`

## Gregorian System Timestamps

Laravel/framework timestamps remain Gregorian in storage:

- `created_at`
- `updated_at`
- `deleted_at`
- OTP expiration/verification timestamps
- queue/job timestamps
- AI provider execution log timestamps
- internal audit or technical metadata timestamps

When any of these timestamps are shown to users, render them through
`JalaliDate` or the Blade components.

## Input And Filters

User-entered date-time values must use:

```text
1405/01/31 23:59:59
```

Date-only filters must use:

```text
1405/01/31
```

Persian, Arabic, and Latin digits are accepted and normalized before validation.
Report/list filters accept Jalali dates and convert them to Gregorian Carbon
only when querying Gregorian timestamp columns such as `created_at`.

## Datepicker

Dashboard date fields use the local bundled `@majidh1/jalalidatepicker` package
through Vite. Do not load datepicker assets from a CDN.

Use these attributes for fields that accept dates:

- `data-jalali-date` for date-only values such as report/list filters.
- `data-jalali-datetime` for date-time values such as CRM follow-up dates.

The admin bundle initializes the picker from
`resources/js/admin/jalali-datepickers.js`.

## Timezone

Conversions use `config('app.timezone')`. Do not hard-code timezone offsets.

## Migration Strategy

HairVision is still pre-release for this schema area, so the Phase 1 migrations
define the final Jalali business-date column types directly. For a production
database, add a new non-destructive migration that converts existing Gregorian
business date values to Jalali strings before changing application writes.
