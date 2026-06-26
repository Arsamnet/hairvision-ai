# Database

This document defines the Phase 1 HairVision data model and tenant-scoping
invariants.

## Phase 1 Tables

### `tenants`

Stores tenant clinics.

Required fields:

- `id`
- `uuid`
- `name`
- `slug`
- `status`

### `tenant_domains`

Maps request hosts to tenants.

Required fields:

- `tenant_id`
- `domain`
- `type`
- `status`
- `ssl_status`
- `verified_at`

### `tenant_landing_page_visits`

Stores landing page views for clinic-level statistics even when the visitor does
not submit a phone number or upload an image.

Required fields:

- `tenant_id`
- `host`
- `ip_hash`
- `user_agent_hash`
- `referer`
- `visited_at`

### `leads`

Stores verified visitor leads per tenant.

Required fields:

- `tenant_id`
- `full_name`
- `phone`
- `phone_normalized`
- `source`
- `phone_verified_at`
- `status`
- `crm_stage`
- `crm_last_note_at`
- `assigned_user_id`

Unique rule:

```text
tenant_id + phone_normalized
```

### `otp_codes`

Stores OTP attempts.

Required fields:

- `tenant_id`
- `phone_normalized`
- `code_hash`
- `purpose`
- `expires_at`
- `verified_at`
- `attempts`
- `ip_address`

Never add a plain `code` column.

### `lead_notes`

Stores CRM-style admin notes for lead follow-up.

Required fields:

- `tenant_id`
- `lead_id`
- `user_id`
- `type`
- `note`
- `metadata_json`

The dashboard is not required for Phase 1, but the data structure exists so a
clinic admin can later change lead stage and add follow-up notes without changing
the public lead flow.

### `users`

Stores dashboard users authenticated by mobile OTP.

Dashboard fields:

- `phone_normalized`
- `role`
- `status`

### `tenant_user`

Stores dashboard user access to clinics.

Required fields:

- `tenant_id`
- `user_id`
- `role`
- `status`

Super admins may access all tenants without a pivot row. Clinic roles use active
`tenant_user` rows for tenant access.

### `uploads`

Stores private visitor upload metadata.

Required fields:

- `tenant_id`
- `lead_id`
- `original_disk`
- `original_path`
- `mime`
- `size`
- `status`

### `ai_providers`

Stores provider definitions. `sample_image` is required for version `1.0.0`.

### `tenant_ai_settings`

Stores tenant provider configuration.

Required fields:

- `tenant_id`
- `provider_id`
- `settings_json`
- `encrypted_api_key`
- `is_default`

### `generation_groups`

Groups the three period jobs for one upload.

Required fields:

- `tenant_id`
- `lead_id`
- `upload_id`
- `status`

### `generation_jobs`

Stores one provider job per period.

Required fields:

- `generation_group_id`
- `tenant_id`
- `lead_id`
- `upload_id`
- `provider_id`
- `period`
- `status`
- `last_error`

### `generated_assets`

Stores generated result metadata.

Required fields:

- `generation_group_id`
- `generation_job_id`
- `tenant_id`
- `lead_id`
- `period`
- `disk`
- `path`
- `mime`
- `size`

### `templates` and `tenant_theme_settings`

Store MVP theme data for the Persian RTL Blade page.

HTML fields may be used for controlled tenant theme snippets, but they must not
introduce arbitrary external scripts or styles.

Additional customer-site alignment fields:

- `favicon_path`
- `back_link_title`
- `back_link_url`

The public page is composed of three areas:

- full-width tenant-controlled header
- full-width HairVision application body
- full-width tenant-controlled footer

### `audit_logs`

Reserved for tenant activity tracking.

### `sms_provider_types`

Stores supported SMS vendors. Required provider keys:

- `melli_payamak`
- `kavenegar`
- `ippanel`
- `sms_ir`

### `sms_provider_accounts`

Stores configured system and user SMS provider accounts.

Required fields:

- `sms_provider_type_id`
- `owner_user_id` nullable
- `scope`
- `name`
- encrypted `credentials`
- encrypted `settings`
- `sender_number`
- `priority`
- `is_active`
- `is_default`
- `last_used_at`

System accounts must have `owner_user_id = null`. User accounts must belong to
the creating user.

### `user_sms_settings`

Stores each dashboard user's selected default SMS provider and
`fallback_to_system` preference.

### `sms_delivery_logs`

Stores safe delivery metadata, status, provider ids, recipient, message preview,
message hash, sanitized errors, provider message id, cost, and timestamps.
Sensitive OTP bodies must not be stored in plain text.

## Required Invariants

- Every tenant-owned table includes `tenant_id`.
- Landing page visits include `tenant_id` and are recorded before lead creation.
- Visitor-owned resources include both `tenant_id` and `lead_id`.
- CRM notes include both `tenant_id` and `lead_id`.
- Public APIs scope by current tenant and, when applicable, current session lead.
- Stored file paths are internal metadata and are never exposed directly.
- Feature migrations should be consolidated into the migration for the related
  model/domain while the schema is still pre-release.
- SMS provider credentials are encrypted and never exposed in dashboard views.
