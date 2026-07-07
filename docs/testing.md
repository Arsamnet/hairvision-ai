# Testing

HairVision changes are complete only after the automated test suite passes.

Required command after behavior changes:

```bash
php artisan test
```

Run the production asset build after CSS, JavaScript, Blade component, Vite, or
font changes:

```bash
npm run build
```

## Coverage Expectations

Add focused tests for changed behavior. Examples:

- Shared utilities such as `App\Support\NumberNormalizer`.
- Validation rules such as `App\Rules\SupportedDigits`.
- Shared query helpers such as `App\Support\Query\SearchFilter` when table
  search/filter behavior changes.
- Shared dashboard/report overview services when management metrics change.
- Tenant-scoped query and filter behavior.
- Permission-aware dashboard actions.
- Modal create flows where validation, redirect, or authorization changes.
- Table action menus for important management tables.
- Sidebar visibility, active state, and locked state.
- Localization keys used by new UI.
- AI model config, encrypted credentials, protected demo behavior, clinic
  assignment, credit consumption, usage logs, and direct-route authorization.
- SaaS plan types, service entitlements, add-on/top-up purchase rules, credit
  ledger rows, and atomic credit consumption.
- AI generation blocking for expired subscriptions, missing service access, and
  exhausted service credit.
- Jalali date conversion, validation, digit normalization, business-date string
  storage, user-facing timestamp display, and Jalali date range filters.
- Shared table filter behavior, active filter chips, sortable heading state,
  and disabled pagination-footer state for one-page results.
- Mobile/tablet drawer markup, permission-aware drawer links, and drawer
  localization keys when navigation changes.
- Mobile list/card markup for important dashboard tables when responsive table
  behavior changes.
- Responsive JavaScript modules that disable duplicate mobile/desktop form
  controls or synchronize drawer/modal state.

Tests should assert backend behavior, not just hidden UI. Unauthorized actions
must be blocked by policies, middleware, scoped services, or route checks.
