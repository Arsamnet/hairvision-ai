# AI Provider Adapters

## Goal

HairVision must not be tightly coupled to one AI model or vendor. Tenant and job
configuration should be able to select a provider through an adapter layer.

Potential provider codes:

```text
sample_image
gapgpt
nano_banana
openai
replicate
custom_http
```

Only `sample_image` is allowed in version `1.0.0`.

## Required Flow

```text
Controller
  -> GenerationService
  -> Dispatch Job
  -> Job
  -> ProviderResolver
  -> ImageGenerationProvider interface
  -> Provider implementation
```

Controllers must never call provider classes directly.

## Current Interface

```php
namespace App\Services\AI\Contracts;

use App\Services\AI\Dtos\ImageGenerationRequest;
use App\Services\AI\Dtos\ImageGenerationResponse;

interface ImageGenerationProvider
{
    public function code(): string;

    public function supportsAsync(): bool;

    public function submit(ImageGenerationRequest $request): ImageGenerationResponse;

    public function poll(string $providerJobId): string;

    public function download(ImageGenerationResponse $response): array;
}
```

## DTOs

```php
final class ImageGenerationRequest
{
    public function __construct(
        public readonly string $tenantUuid,
        public readonly string $leadUuid,
        public readonly string $uploadPath,
        public readonly string $period,
        public readonly string $prompt,
        public readonly ?string $modelName = null,
        public readonly array $settings = [],
    ) {}
}
```

```php
final class ImageGenerationResponse
{
    public function __construct(
        public readonly string $status,
        public readonly ?string $providerJobId = null,
        public readonly ?string $binaryContent = null,
        public readonly ?string $localPath = null,
        public readonly array $metadata = [],
    ) {}
}
```

## Provider Selection Order

Target design:

```text
job override
  -> lead override
  -> tenant default provider
  -> system default provider
  -> sample_image
```

Current 1.0.0 behavior resolves the tenant default when available and falls back
to `sample_image`.

## Job Statuses

```text
pending
submitted
processing
completed
failed
retry_wait
```

## Queue Design

Each period is a separate generation job:

```text
3_months
6_months
12_months
```

This keeps retries, failures, timing, and future provider costs isolated per
period.

## Real Provider Strategy

Real provider implementations must live under:

```text
App\Services\AI\Providers\
```

Examples:

```text
GapGptProvider
NanoBananaProvider
OpenAiImageProvider
CustomHttpProvider
```

Provider settings must be read from `tenant_ai_settings.settings_json` and
secrets must be encrypted. Do not add provider-specific columns to shared job or
asset tables.

## Error Handling

Provider errors must not be shown raw to visitors. Store details in
`generation_jobs.last_error` and logs. Public API responses should use a generic
message.
