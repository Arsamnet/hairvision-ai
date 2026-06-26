# Dashboard

The admin dashboard lives under `/admin` and uses Laravel Blade, sessions,
policies, and server-rendered forms.

Routes:

- `/admin`
- `/admin/dashboard`
- `/admin/leads`
- `/admin/leads/create`
- `/admin/leads/{lead}`
- `/admin/leads/{lead}/edit`
- `/admin/users`
- `/admin/reports`
- `/admin/settings`
- `/admin/sms/providers`
- `/admin/sms/logs`
- `/admin/sms/statistics`
- `/admin/account/sms-settings`

Dashboard features:

- Mobile OTP login.
- Active clinic switcher.
- Responsive RTL admin shell.
- Lead search, filters, pagination, and detail page.
- Lead creation for allowed roles.
- Lead editing with limited editor mode.
- Read-only viewer access.
- User list for super admins and clinic admins.
- Basic reports by CRM stage and tenant.
- SMS provider management for system and user-owned provider accounts.
- Account-level SMS provider selection.
- SMS delivery logs and aggregate statistics.
- Demo seed data for `clinic1`.

The dashboard does not call AI providers directly. Public `/api/v1` behavior
remains responsible for visitor upload and generation workflows.
