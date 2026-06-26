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
- `status`
- `communication_channel`
- `note`
- `next_follow_up_at`
- `completed_at`
- `metadata_json`

The dashboard stores follow-up records separately from the main lead edit form
so clinic users can add timeline history without changing the lead record every
time. `status` stores the CRM stage key, `communication_channel` stores values
such as `phone`, `sms`, `whatsapp`, `in_person`, or `other`, and completion is
tracked through `completed_at`.

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

## System Management Tables

### `roles`

Stores dashboard access levels by stable `code`.

Default codes:

- `super_admin`
- `clinic_admin`
- `editor`
- `viewer`

### `permissions`

Stores backend permission codes such as:

- `users.list`
- `users.search`
- `users.view`
- `users.create`
- `users.edit`
- `users.delete`

### `permission_role`

Assigns permissions to access levels.

### `system_settings`

Stores database-backed platform settings editable by authorized system managers.
Values are cached and the cache is cleared on update.

### `sms_gateways`

Stores system SMS gateway records and user-private SMS gateway records.
Credentials are encrypted and hidden from serialized output.

System records use:

```text
owner_scope = system
user_id = null
```

User-private records use:

```text
owner_scope = user
user_id = users.id
```

### `payment_gateways`

Stores system payment gateway records. Credentials are encrypted and hidden from
serialized output.

### `plans`

Stores plan definitions, including price, duration, usage limit, features, and
status.

### `invoices`

Stores user-owned invoices connected to plans and payment transactions.

### `payments`

Stores payment attempts, authority/reference ids, safe gateway response payloads,
and verification status.

### `subscriptions`

Stores active and historical user plan entitlements. Only one active
subscription should exist per user after successful payment; upgrades replace the
previous active subscription without carrying remaining time or credit.

## Required Invariants

- Every tenant-owned table includes `tenant_id`.
- Landing page visits include `tenant_id` and are recorded before lead creation.
- Visitor-owned resources include both `tenant_id` and `lead_id`.
- CRM notes include both `tenant_id` and `lead_id`.
- CRM follow-up writes update `leads.crm_stage` and `leads.crm_last_note_at`
  inside a database transaction.
- Public APIs scope by current tenant and, when applicable, current session lead.
- Stored file paths are internal metadata and are never exposed directly.
- Dashboard upload previews stream private upload bytes through authorized admin
  routes and never expose private storage paths.
- Normal users may query only their own invoices, subscriptions, private SMS
  gateways, and user reports.
- Normal users need an active subscription to access operational dashboard
  sections beyond dashboard overview, plans, billing, and settings.
- Super Admin can cross tenant and user boundaries only through explicit
  permission checks.
- Feature migrations should be consolidated into the migration for the related
  model/domain while the schema is still pre-release.
