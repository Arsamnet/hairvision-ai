# Permissions

HairVision permissions are stored in the database and seeded from
`App\Services\Auth\PermissionCatalog`.

The canonical role and permission overview is in
[`docs/roles-permissions.md`](roles-permissions.md). This file lists the current
AI model permission additions.

## AI Model Permissions

- `ai_models.list`
- `ai_models.search`
- `ai_models.view`
- `ai_models.create`
- `ai_models.edit`
- `ai_models.delete`
- `ai_models.toggle`
- `ai_models.assign_to_clinic`
- `ai_models.manage_credentials`
- `ai_models.manage_prompt`
- `ai_models.manage_credit`

Super Admin receives all permissions. Default clinic/customer roles do not
receive global AI model management permissions.

Clinic managers can create users inside their own clinic through existing
`users.create` and `users.edit` permissions, but non-super admins can assign only
clinic-level role codes:

- `clinic_admin`
- `editor`
- `viewer`

They cannot assign `super_admin` or future system/admin roles.
