# Localization

HairVision dashboard user-facing copy is Persian and uses Laravel language files
under `lang/fa`.

Current files:

- `dashboard.php`: dashboard titles, cards, plan notices, CTAs, and shell text.
- `menu.php`: sidebar groups, menu item labels, active and locked states.
- `auth.php`: dashboard OTP messages.
- `plans.php`: plan fields, statuses, management, purchase, and upgrade copy.
- `payments.php`: invoices, payments, subscription labels, and statuses.
- `leads.php`: lead labels, lead statuses, CRM stages, uploads, and generation
  period labels.
- `crm.php`: follow-up timeline labels, channels, and flash messages.
- `sms.php`: SMS gateway messages.
- `settings.php`: account and system settings copy.
- `system.php`: shared labels for system-management pages.
- `permissions.php`: permission, tenant, and plan-access denial messages.
- `reports.php`: user and management report labels.
- `messages.php`: shared yes/no/empty/system labels and seeded provider names.
- `validation.php`: Persian validation lines and field names.

Stable internal codes such as permission codes, route names, provider codes,
payment statuses, and CRM status keys remain English because they are technical
identifiers. Display names and user-facing messages should be translated through
`__()` in controllers, services, requests, Blade views, and seeders.

Seeded display names for roles, permissions, gateways, plans, providers,
settings, templates, demo users, and demo leads should be Persian where safe.
Technical values such as `sample_image`, `local`, `IRR`, and permission codes
must remain stable.
