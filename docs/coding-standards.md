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

## API and Frontend Rules

- Maintain API-first architecture.
- Blade pages must call `/api/v1` endpoints instead of duplicating business
  logic.
- Public visitor UI is Persian, right-to-left, and uses the embedded Vazirmatn
  font.
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
- Avoid large methods.
- Keep each method focused on one responsibility.
- Use meaningful class, method, and variable names.
- Add abstractions only when they remove real complexity or match an established
  local pattern.
- Do not expose storage paths directly.
- Do not build provider-specific database columns.
- Do not make synchronous real AI calls from the HTTP lifecycle.
- Add or update tests for every behavior change.

## SemVer Rules

- Version `1.0.x` should harden the current public visitor MVP without breaking
  `/api/v1`.
- Add `/api/v2` for breaking API response or behavior changes.
- Adding response fields is allowed in `/api/v1`.
- Removing fields or changing field meaning requires a new API version.
