# Dashboard

The admin dashboard lives under `/admin` and uses Laravel Blade, sessions,
policies, and server-rendered forms.

Routes:

- `/admin`
- `/admin/dashboard`
- `/admin/leads`
- `/admin/leads/create`
- `/admin/leads/{lead}`
- `/admin/leads/{lead}/uploads/{upload}`
- `/admin/leads/{lead}/follow-ups`
- `/admin/leads/{lead}/edit`
- `/admin/users`
- `/admin/reports`
- `/admin/settings`
- `/admin/plans`
- `/admin/billing`
- `/admin/my-sms-gateways`
- `/admin/system`
- `/admin/system/access`
- `/admin/system/roles`
- `/admin/system/permissions`
- `/admin/system/settings`
- `/admin/system/sms-gateways`
- `/admin/system/payment-gateways`
- `/admin/system/reports`

Dashboard features:

- Mobile OTP login.
- Active clinic switcher for clinic users and optional clinic selection for
  Super Admin users.
- Responsive RTL admin shell.
- Grouped Persian sidebar menu with consistent inline SVG icons, group active
  states, item active states, permission-hidden items, and plan-locked disabled
  items.
- Lead search, localized filters, pagination, and detail page.
- Lead creation for allowed roles.
- Lead editing with limited editor mode.
- CRM follow-up timeline on lead detail pages. Follow-up records include the
  lead, user, tenant, status, note, optional next follow-up date, optional
  communication channel, and completion state.
- Authorized lead uploaded image preview on lead detail pages. Private upload
  bytes are streamed only through `/admin/leads/{lead}/uploads/{upload}` after
  lead policy and tenant-context checks.
- Read-only viewer access.
- User list for super admins and clinic admins.
- Basic reports by CRM stage and tenant.
- User financial section with own invoices and active subscription state.
- Plan list and purchase/upgrade action.
- Plan status is shown in the header/dashboard. If a normal user has no active
  plan, expired plan, or finished credit, plan-restricted menu items render as
  locked and backend middleware blocks direct route access.
- User-private SMS gateway configuration.
- Dedicated system management menu for authorized system managers.
- Roles, permissions, access assignment, settings, gateways, plans, billing, and
  management reports.
- Demo seed data for `clinic1`.

Plan-restricted normal-user routes include operational lead, CRM, user, SMS, and
user-report sections. Dashboard, plans, billing/invoices, tenant switch, logout,
and account settings remain accessible without an active plan. System-management
routes remain permission-protected management routes and are not blocked by user
subscription state.

Super Admin dashboard behavior:

- A Super Admin starts in a platform/global context with no clinic selected.
- No clinic is written to `admin_active_tenant_id` unless the Super Admin
  manually selects one.
- The tenant switcher can select a clinic or return to all clinics.
- Normal clinic users do not see global clinic filters and remain scoped to
  their active tenant membership.

The dashboard does not call AI providers directly. Public `/api/v1` behavior
remains responsible for visitor upload and generation workflows.
