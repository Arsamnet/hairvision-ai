# Database

This document defines the Phase 1 HairVision data model and tenant-scoping
invariants.

Business/user-facing dates use the Jalali storage and display contract described
in [Jalali Dates](dates.md). Laravel system timestamps such as `created_at`,
`updated_at`, and `deleted_at` remain Gregorian timestamps in storage and are
converted only when displayed to users.

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

`next_follow_up_at` and `completed_at` are Jalali `char(19)` business date-time
fields in `YYYY/MM/DD HH:mm:ss` format.

### `users`

Legacy dashboard users authenticated by mobile OTP.

This table is still used by the current dashboard auth flow for compatibility,
but the target identity model separates platform and clinic users into
`platform_users` and `clinic_users`.

Dashboard fields:

- `phone_normalized`
- `role`
- `status`

### `platform_users`

Stores system-level dashboard users. These users have platform-scoped roles and
permissions and are not tenant-owned.

Required fields:

- `uuid`
- `name`
- `email`
- `phone_normalized`
- `role`
- `status`

### `clinic_users`

Stores clinic-level users owned by one tenant.

Required fields:

- `tenant_id`
- `uuid`
- `name`
- `email`
- `phone_normalized`
- `role`
- `status`

Unique rule:

```text
tenant_id + phone_normalized
tenant_id + email
```

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

### `ai_model_accesses`

Stores system-admin managed access records for configured AI model types.

Required fields:

- `model_key`
- `usage_category_key`
- `name`
- `credentials`
- `prompt`
- `credit_limit`
- `credit_used`
- `is_active`
- `is_demo`
- `is_protected`

Credentials are encrypted and hidden from serialization. Protected demo rows
cannot be deleted, but they can be disabled.

### `clinic_ai_model_access`

Assigns AI model access records to clinic tenants.

Required fields:

- `clinic_id`
- `ai_model_access_id`
- `is_active`
- `assigned_by`

A clinic can use only active assigned access records.

Only one assignment should be active for the same clinic and
`usage_category_key` at a time. The assignment service enforces this by
deactivating other active assignments in the same category when a new active
access is attached.

### `ai_model_usage_logs`

Stores safe AI model execution logs.

Required fields:

- `clinic_id`
- `user_id`
- `ai_model_access_id`
- `usage_category_key`
- `status`
- `credit_consumed`
- `request_reference`
- `response_reference`
- `error_code`
- `error_message`
- `metadata_json`

### `generation_groups`

Groups the three staged generation jobs for one upload.

Required fields:

- `tenant_id`
- `lead_id`
- `upload_id`
- `status`

### `generation_jobs`

Stores one provider job per stage.

Required fields:

- `generation_group_id`
- `tenant_id`
- `lead_id`
- `upload_id`
- `provider_id`
- `period` (`two_weeks`, `intermediate_regrowth`, or `final_result`)
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

Roles have a `scope`:

- `platform`: system-level roles that permitted platform managers can manage.
- `clinic`: predefined clinic roles that can be assigned to clinic users but
  whose permissions are not editable from dashboard role management.

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

Stores purchasable product definitions. Each row has `plan_type`:

- `base_subscription`
- `service_add_on`
- `credit_top_up`

Base and add-on plans may have `duration_days`; credit top-ups do not extend
time. `usage_limit` remains as an aggregate compatibility value, while service
credits are defined by `plan_service_entitlements`.

### `plan_service_entitlements`

Stores service credit definitions per plan.

Required fields:

- `plan_id`
- `service_key`
- `credit_amount`
- `is_active`

### `invoices`

Stores user- and clinic-owned invoices connected to plans and payment
transactions. Clinic purchases include `tenant_id`, and paid invoices are
activated exactly once through `activated_at`.

Business date fields `due_at`, `paid_at`, `expires_at`, and `activated_at` are
stored as zero-padded Jalali `char(19)` strings.

### `payments`

Stores payment attempts, authority/reference ids, safe gateway response payloads,
and verification status. Clinic-owned payments include `tenant_id`.

`verified_at` is stored as a zero-padded Jalali `char(19)` business date-time
because it is shown as payment business data.

### `subscriptions`

Stores active and historical clinic base subscriptions. Only one active base
subscription should exist per clinic after successful payment. Upgrades replace
the previous active base subscription without carrying remaining time or service
credit. The same active base plan cannot be purchased again while it is active.
Renewal is allowed after expiration by time or any non-active current
subscription state.

`starts_at` and `ends_at` are stored as zero-padded Jalali `char(19)` business
date-time fields. Subscription comparisons use zero-padded Jalali strings through
`App\Support\JalaliDate`.

### `subscription_service_entitlements`

Stores enabled AI services and service-level credit balances for a clinic
subscription.

Required fields:

- `tenant_id`
- `subscription_id`
- `plan_id`
- `service_key`
- `source_type`
- `included_credit`
- `purchased_credit`
- `used_credit`
- `is_active`
- `starts_at`
- `ends_at`

`starts_at` and `ends_at` are stored as zero-padded Jalali `char(19)` business
date-time fields.

### `credit_transactions`

Stores an immutable credit ledger for service grants, top-ups, usage, refunds,
and adjustments.

Required fields:

- `tenant_id`
- `subscription_id`
- `subscription_service_entitlement_id`
- `service_key`
- `type`
- `amount`
- `balance_after`
- `related_type`
- `related_id`
- `created_by`

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
- Clinic service credit usage is checked and consumed through
  `subscription_service_entitlements` and `credit_transactions`.
- Credit exhaustion blocks the affected AI service but does not expire the base
  subscription.
- Clinics may use only active AI model access records assigned through
  `clinic_ai_model_access`.
- AI model usage logs must not contain credentials, private payloads, or storage
  paths.
- Normal users need an active subscription to access operational dashboard
  sections beyond dashboard overview, plans, billing, and settings.
- Super Admin can cross tenant and user boundaries only through explicit
  permission checks.
- Feature migrations should be consolidated into the migration for the related
  model/domain while the schema is still pre-release.
