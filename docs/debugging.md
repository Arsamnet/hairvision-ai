# Debugging and Step Logging

HairVision uses ordered debug step logging to make request flows easier to inspect during local development and troubleshooting.

Debug step logging must be implemented through `App\Support\DebugStepLogger` instead of calling `Log::debug()` directly inside controllers, services, jobs, or middleware.

## Purpose

Use debug step logging when a workflow has multiple important stages, such as tenant resolution, lead verification, upload lookup, generation group creation, queued job dispatching, provider request preparation, provider response handling, asset creation, and API response preparation.

The goal is to make the execution order visible in `storage/logs/laravel.log` when `APP_DEBUG=true`.

## Core Rule

Debug step logs must only be written through:

```php
DebugStepLogger::step($request, $scope, $message, $context);
```

The logger must remain silent when `APP_DEBUG=false`.

Do not use raw `Log::debug()` for ordered workflow tracing unless there is a specific reason and it is documented in the code.

## Scope Naming

Use a short dot-separated scope name that identifies the workflow.

Good examples:

```php
$scope = 'generation.store';
$scope = 'generation.status';
$scope = 'upload.store';
$scope = 'otp.verify';
$scope = 'tenant.resolve';
```

Avoid vague names such as:

```php
$scope = 'debug';
$scope = 'test';
$scope = 'controller';
```

## Safe Context Data

Only log safe debugging metadata.

Allowed examples:

```php
[
    'tenant_id' => $tenant->id,
    'lead_id' => $lead->id,
    'upload_uuid' => $upload->uuid,
    'generation_group_uuid' => $group->uuid,
    'status' => $group->status,
    'jobs_count' => $group->jobs->count(),
]
```

Do not log sensitive or private data, including OTP codes, phone numbers, raw uploaded image paths, full provider payloads, provider API keys, access tokens, personal medical notes, or raw exception traces containing secrets.

## Controller Usage Example

```php
$scope = 'generation.store';

DebugStepLogger::step($request, $scope, 'Request received', [
    'upload_id' => $request->post('upload_id'),
]);

$tenant = $this->tenantFromRequest($request, $scope);

DebugStepLogger::step($request, $scope, 'Tenant resolved', [
    'tenant_id' => $tenant->id,
]);
```

## Services and Jobs

Services and queued jobs may also use debug step logging when they are part of a multi-step workflow.

When a `Request` instance is not available, prefer adding a dedicated workflow logger or passing a trace identifier explicitly. Do not create fake request objects only for logging.

## Expected Output

When `APP_DEBUG=true`, logs should appear in order:

```text
[generation.store] Step 01: Request received
[generation.store] Step 02: Tenant resolved
[generation.store] Step 03: Verified lead resolved
[generation.store] Step 04: Upload resolved
[generation.store] Step 05: Generation group created
[generation.store] Step 06: Response prepared
```

When `APP_DEBUG=false`, no debug step logs should be written.

## Agent and Developer Requirements

When adding a new controller action, middleware, service workflow, or queued job with multiple steps:

1. Use `DebugStepLogger` for ordered debug logs.
2. Keep comments and PHPDoc concise.
3. Log only safe metadata.
4. Use clear scope names.
5. Do not expose sensitive user, tenant, provider, or image data in logs.
6. Add or update tests when the logging behavior affects control flow or error handling.