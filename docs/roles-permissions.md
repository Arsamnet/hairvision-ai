# Roles and Permissions

HairVision starts with internal role-based access control rather than an
external permissions package.

Roles:

- `super_admin`: platform-level user, can access all clinics and all leads.
- `clinic_admin`: manages leads and users for assigned clinics.
- `editor`: can view clinic leads and update limited operational fields.
- `viewer`: read-only clinic access.

Current dashboard enforcement:

- Lead policies enforce view, create, update, and delete rules.
- Viewers cannot edit leads.
- Editors can update `source`, `crm_stage`, and notes only.
- Clinic admins can create and update clinic leads.
- Super admins can cross tenant boundaries through explicit policy bypass.
- Lead deletion is disabled.
- Super admins can manage all SMS provider accounts and logs.
- Normal users can create, update, and delete only their own user-scoped SMS
  provider accounts.
- Normal users can view active system SMS providers as selectable options but
  cannot modify system providers.
- Users can view only their own SMS logs; super admins can view all logs.

Frontend visibility is treated as a usability aid only. Backend policies and
tenant-scoped queries are the authority.
