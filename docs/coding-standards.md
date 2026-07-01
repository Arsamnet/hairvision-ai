# Coding Standards

HairVision follows Laravel conventions, PSR-12, clean code, SOLID design, and
semantic versioning.

## Laravel Rules

- Use Laravel 13 conventions.
- Keep controllers thin.
- Controllers should validate request data, call a service/action, and return
  JSON or a view shell.
- Use Form Requests for validation.
- Use API Resources when response serialization grows beyond simple MVP arrays.
- Use policies or gates for authorization.
- Use config files for environment-based values.
- Prefer dependency injection over service location when adding new code.
- Keep business logic in services, actions, jobs, or model methods where safe.
- Never put AI provider logic inside controllers.
- Resolve tenant from the request host, never from user input.
- Never trust `tenant_id` from request bodies.

## User-Facing Error Handling

- Wrap user-facing write flows, external integrations, gateways, providers, and
  background handoff points in `try`/`catch` blocks at the controller, service,
  job, or adapter boundary where an exception could otherwise reach the user.
- Log caught exceptions through Laravel logging (`Log::warning`, `Log::error`,
  or `report`) with safe operational context such as model ids, operation names,
  and endpoint labels.
- Do not log or display OTP codes, credentials, bearer tokens, private storage
  paths, full gateway authority values, uploaded payloads, or raw sensitive
  provider responses.
- Do not show exception messages, stack traces, SQL errors, provider diagnostics,
  or gateway error bodies to end users. Return only a vague, localized fallback
  message such as "خطایی رخ داد. لطفا دوباره تلاش کنید."
- Authorization failures and validation errors may continue to use Laravel's
  normal response flow, but unexpected operational failures must be caught,
  logged, and translated to a generic user-safe response.

## API and Frontend Rules

- Maintain API-first architecture.
- Blade pages must call `/api/v1` endpoints instead of duplicating business
  logic.
- Public visitor UI is Persian, right-to-left, and uses the embedded Vazirmatn
  font.
- Admin dashboard UI is also RTL Blade and must enforce authorization on the
  backend with policies or tenant-scoped services.
- No visitor workflow may depend on arbitrary external frontend resources.
- Do not introduce React, Vue, Inertia, Livewire, or Next.js into the MVP unless
  explicitly requested.

## PHPDoc Rules

Add useful PHPDoc where it improves understanding, especially for:

- controllers
- services
- Form Requests
- models
- policies
- jobs
- middleware
- events
- listeners
- important public methods
- complex protected or private methods

PHPDoc should describe:

- method purpose
- input parameters
- output or return type
- exceptions
- side effects
- version history when relevant

Use native PHP parameter and return types where safe and compatible with Laravel
magic methods and framework conventions. Use PHPDoc for data shapes or framework
relationships that need more explanation.

Avoid comments that only repeat the method name or obvious code.

Preferred PHPDoc style:

```php
/**
 * Store a new client record.
 *
 * Validates the incoming request data, creates a client entity,
 * and returns the created resource response.
 *
 * @param  \App\Http\Requests\Client\StoreClientRequest  $request
 * @return \Illuminate\Http\JsonResponse
 *
 * @throws \Throwable
 *
 * @since 1.0.0
 */
public function store(StoreClientRequest $request): JsonResponse
{
    //
}
```

## Clean Code Rules

- Avoid duplicated logic.
- Move repeated generic formatting and normalization to focused support classes,
  such as `App\Support\NumberNormalizer`.
- Move digit-only validation to reusable validation rules, such as
  `App\Rules\SupportedDigits`, instead of controller checks.
- Move repeated list search behavior to focused query helpers, such as
  `App\Support\Query\SearchFilter`, and keep pagination/sorting on
  `PaginationParameters`.
- Move repeated tenant-scoped dashboard filtering into focused services or
  actions instead of copying request-input authorization checks.
- Move repeated dashboard/report statistics into focused services instead of
  duplicating count/sum queries in controllers.
- Keep plan purchase, service entitlement, and credit ledger behavior in billing
  services/actions; controllers should not mutate subscription credits directly.
- Do not model service credit as only one mutable subscription counter. Use
  `subscription_service_entitlements` plus `credit_transactions`.
- Avoid large methods.
- Keep each method focused on one responsibility.
- Use meaningful class, method, and variable names.
- Add abstractions only when they remove real complexity or match an established
  local pattern.
- Do not expose storage paths directly.
- Do not build provider-specific database columns.
- Do not make synchronous real AI calls from the HTTP lifecycle.
- Add or update tests for every behavior change.

## Dashboard Reuse Rules

- Use shared Blade components for cards, badges, empty states, modals, and table
  row action menus.
- Use the local `x-admin.icon` UIcons regular-rounded icon surface; do not add
  Font Awesome, inline SVG dashboard icons, external icon assets, or one-off
  custom icons for buttons, menus, and primary UI elements.
- Put table row actions inside a three-dot action menu at the beginning/right
  side of RTL tables.
- Compact list/card management actions should also use the shared action-menu
  pattern when it improves consistency.
- Keep create forms on list pages inside modal triggers when the module supports
  creating records.
- Implement table search and filters through backend query parameters and
  preserve filters through pagination.

## SemVer Rules

- Version `1.0.x` should harden the current public visitor MVP without breaking
  `/api/v1`.
- Add `/api/v2` for breaking API response or behavior changes.
- Adding response fields is allowed in `/api/v1`.
- Removing fields or changing field meaning requires a new API version.
