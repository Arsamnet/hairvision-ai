# Roles and Permissions

HairVision uses internal role-based access control rather than an external
permissions package.

Roles:

- `super_admin`: platform-level user, can access all clinics and all leads.
- `clinic_admin`: manages leads and users for assigned clinics.
- `editor`: can view clinic leads and update limited operational fields.
- `viewer`: read-only clinic access.

Current dashboard enforcement:

- Permissions are stored in `permissions`.
- Access levels are stored in `roles`.
- Role permission assignments are stored in `permission_role`.
- `users.role` stores the global/default access level.
- `tenant_user.role` stores the tenant-context access level.
- Lead policies enforce list, view, create, update, and delete rules.
- Lead policies also gate CRM follow-up creation and updates through
  `createNote` and `updateNote`.
- User management is protected by `UserPolicy`, `permission:*` route
  middleware, and `UserManagementService` scoped queries. `super_admin` can
  list, search, view, create, edit, and delete users. Other users need the
  matching `users.list`, `users.search`, `users.view`, `users.create`,
  `users.edit`, and `users.delete` permissions.
- AI model management is protected by `AiModelAccessPolicy`,
  `permission:*` route middleware, and `ai_models.*` permissions. Default
  clinic roles do not receive global AI model permissions.
- Clinic managers can create users only inside their allowed clinics and can
  assign only clinic-level roles: `clinic_admin`, `editor`, and `viewer`.
- Viewers cannot edit leads.
- Editors can update `source`, `crm_stage`, and notes only.
- Clinic admins can create and update clinic leads.
- Super admins can cross tenant boundaries through explicit policy bypass.
- Lead deletion is disabled.
- Plan definitions can be created, updated, activated/deactivated, or deleted
  only by Super Admin users or users with the relevant plan permission such as
  `plans.create`, `plans.edit`, `plans.delete`, or `plans.manage`.
- Role and user-status display labels are translated through `lang/fa/system.php`.
  Internal role and status keys remain English.

Frontend visibility is treated as a usability aid only. Backend policies and
tenant-scoped queries are the authority.

Plan access middleware:

- Normal users without an active plan can access dashboard overview, plans,
  billing/invoices, tenant switch/logout, and account settings.
- Operational menu items remain visible as locked when the user has permission
  but lacks active plan access.
- Direct URL access to plan-restricted routes returns a Persian `403` response
  for JSON requests or redirects to the plans page for web requests.
- Super Admin and system-management users with management permissions are exempt
  from subscription gating.

Example permission codes:

- `users.list`
- `users.search`
- `users.view`
- `users.create`
- `users.edit`
- `users.delete`
- `roles.manage`
- `permissions.manage`
- `settings.manage`
- `sms_gateways.manage`
- `payment_gateways.manage`
- `ai_models.list`
- `ai_models.assign_to_clinic`
- `ai_models.manage_credentials`
- `plans.manage`
- `billing.manage`
- `reports.management.view`
- `reports.user.view`
