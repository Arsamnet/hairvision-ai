# System Management

HairVision includes a native Laravel system-management area under `/admin/system`.
It uses the existing session-authenticated admin dashboard and does not introduce
an external permission package.

## Access Model

System management is available to:

- users with `role = super_admin`
- users whose active access level includes the required permission

Backend route middleware enforces permissions with the `permission:*` middleware.
Blade menu visibility is only a usability layer.

## Access Levels and Permissions

Access levels are stored in `roles`, permissions are stored in `permissions`, and
assignments are stored in `permission_role`.

The existing user model remains single-role:

- `users.role` is the global/default access level
- `tenant_user.role` is the tenant-context access level

The default seeded access levels are:

- `super_admin`
- `clinic_admin`
- `editor`
- `viewer`

Super Admin users bypass normal restrictions through the permission service and
receive every seeded permission.

## Management Routes

Important routes:

- `/admin/system`
- `/admin/system/access`
- `/admin/system/roles`
- `/admin/system/permissions`
- `/admin/system/settings`
- `/admin/system/sms-gateways`
- `/admin/system/payment-gateways`
- `/admin/system/reports`

User-scoped operational routes:

- `/admin/plans`
- `/admin/billing`
- `/admin/my-sms-gateways`
- `/admin/reports`

## System Settings

Editable system settings are stored in `system_settings` and cached by
`SystemSettingService`.

Seeded settings include:

- `system.name`
- `support.phone`
- `notifications.global_enabled`
- `billing.invoice_due_days`
- `api.pagination.default_per_page`
- `api.pagination.max_per_page`

The settings cache is cleared after updates.

## SMS Gateways

System SMS gateways and user-private SMS gateways are both stored in
`sms_gateways`.

Rules:

- system managers can list, search, view, create, edit, and delete SMS gateways
  with the relevant `sms_gateways.*` permission
- normal users can list, search, view, create, edit, and delete only their own
  private SMS gateways
- system OTP/platform messages use the system default gateway when configured
- user customer messages use only that user's private gateway
- user-private gateways cannot be used by another user
- credentials are stored in encrypted casts, hidden from serialized output, and
  shown in Blade only as masked summaries

The MVP local adapter is a no-op and does not call external services.

## Payment Gateways and Billing

Payment gateways are stored in `payment_gateways`; one enabled gateway may be
selected as default. The MVP local payment adapter creates deterministic local
references and does not call external services.

Billing tables:

- `plans`
- `invoices`
- `payments`
- `subscriptions`

Payment verification is idempotent. A successful plan payment activates one
subscription and replaces any previous active subscription without carrying over
remaining time or usage credit.

Users cannot buy and activate the same active plan again while the current
subscription still has time and usage credit. Buying the same plan is allowed
again only after the current subscription expires by time, expires by usage, or
is no longer active. Buying a different active plan is treated as an upgrade and
does not transfer remaining time or credit.

Plan definition management is separate from user plan purchase:

- normal users can view and buy only active plans from `/admin/plans`
- Super Admin or users with plan-management permission can create, edit,
  activate/deactivate, or delete definitions through system routes
- route middleware accepts either action-specific permissions such as
  `plans.create` or the broader `plans.manage`
- users without active plans may still access `/admin/plans` and
  `/admin/billing`

## Reports

Reports are separated into:

- management reports: `/admin/system/reports`
- user reports: `/admin/reports`

Management reports require `reports.management.view`. User reports require
`reports.user.view` and remain scoped through dashboard query services.

## Pagination

`App\Support\Pagination\PaginationParameters` normalizes:

- `page`
- `per_page`
- `search`
- `sort`
- `direction`

`App\Support\Pagination\PaginatedResponse` returns consistent metadata only.
Dashboard lists do not load DataTables assets and do not accept DataTables
request fields.
