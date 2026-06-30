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
- `/admin/users/create`
- `/admin/users/{user}`
- `/admin/users/{user}/edit`
- `/admin/reports`
- `/admin/settings`
- `/admin/plans`
- `/admin/billing`
- `/admin/ai-models`
- `/admin/ai-models/{aiModelAccess}`
- `/admin/clinics/{clinic}/ai-models`
- `/admin/my-sms-gateways`
- `/admin/my-sms-gateways/create`
- `/admin/my-sms-gateways/{smsGateway}`
- `/admin/my-sms-gateways/{smsGateway}/edit`
- `/admin/system`
- `/admin/system/access`
- `/admin/system/roles`
- `/admin/system/permissions`
- `/admin/system/settings`
- `/admin/system/sms-gateways`
- `/admin/system/sms-gateways/create`
- `/admin/system/sms-gateways/{smsGateway}`
- `/admin/system/sms-gateways/{smsGateway}/edit`
- `/admin/system/payment-gateways`
- `/admin/system/reports`

Dashboard features:

- Mobile OTP login.
- No global clinic filter in the dashboard header. Clinic-related pages show all
  allowed records by default and expose a `clinic_id` filter inside the page
  filter area when multiple clinics are available.
- Responsive RTL admin shell.
- Persian sidebar menu with consistent local UIcons icon-font icons, item active
  states, permission-hidden items, and plan-locked disabled items. Single items
  render as normal links; groups with multiple visible children render as
  collapsible submenus with active parent and child states.
- Important list pages use the shared search/filter component. The compact bar
  keeps only the main filters visible, the filter dialog holds secondary
  filters, active filters can be edited from chips, and individual filters can
  be removed with their `x` action.
- Sortable table columns use clickable headings with active direction icons and
  a distinct active color. Pagination lives in table footers and renders a
  disabled one-page state when there is nothing to paginate.
- Lead search, clinic/status/stage filters, newest-first sorting, pagination,
  and detail page.
- Lead creation for allowed roles.
- Lead editing with limited editor mode.
- CRM follow-up timeline on lead detail pages. Follow-up records include the
  lead, user, tenant, status, note, optional next follow-up date, optional
  communication channel, and completion state.
- Authorized lead uploaded image preview on lead detail pages. Private upload
  bytes are streamed only through `/admin/leads/{lead}/uploads/{upload}` after
  lead policy and tenant-context checks.
- Read-only viewer access.
- User list/search/view/create/edit/delete for users with the matching
  `users.*` permissions.
- Basic reports by CRM stage and tenant.
- User financial section with own invoices and active subscription state.
- Plan list with backend search/filter parameters, purchase/upgrade action, and
  permission-aware management actions inside the shared three-dot menu.
- Plan status is shown in the header/dashboard. If a normal user has no active
  plan, expired plan, or finished credit, plan-restricted menu items render as
  locked and backend middleware blocks direct route access.
- User-private SMS gateway list/search/view/create/edit/delete with credentials
  masked in the UI.
- AI model access management for Super Admin or permitted admins under
  `/admin/ai-models`, including backend search/filter/pagination, masked
  credentials, credit summaries, usage logs, protected/demo badges, and clinic
  assignment.
- Dedicated system management menu for authorized system managers.
- Roles, permissions, access assignment, settings, system SMS gateways, payment
  gateways, plans, billing, and management reports.
- System dashboard and management reports reuse the shared platform overview
  service so overlapping metrics are not duplicated in controllers.
- Demo seed data for `clinic1`.

Plan-restricted normal-user routes include operational lead, CRM, user, SMS, and
user-report sections. Dashboard, plans, billing/invoices, tenant switch, logout,
and account settings remain accessible without an active plan. System-management
routes remain permission-protected management routes and are not blocked by user
subscription state.

Super Admin dashboard behavior:

- A Super Admin starts in a platform/global context with no clinic selected.
- Clinic filters live inside pages such as leads and users, not in the global
  header.
- Normal clinic users remain backend-scoped to their allowed tenant memberships.

The dashboard does not call AI providers directly. Public `/api/v1` behavior
remains responsible for visitor upload and generation workflows.
