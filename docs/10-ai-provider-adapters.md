# 10 - AI Provider Adapters

## هدف

HairVision نباید به یک مدل یا سرویس AI خاص وابسته باشد. هر tenant یا حتی هر job باید بتواند provider متفاوت داشته باشد.

Providerهای قابل پشتیبانی:

```text
sample_image
gapgpt
nano_banana
openai
replicate
custom_http
```

## قانون معماری

Controller نباید مستقیماً provider را صدا بزند.

جریان درست:

```text
Controller
  -> GenerationService
  -> Dispatch Job
  -> Job
  -> AiProviderResolver
  -> ImageGenerationProvider Interface
  -> Provider Implementation
```

## Interface پیشنهادی

```php
namespace App\Services\AI\Contracts;

interface ImageGenerationProvider
{
    public function code(): string;

    public function supportsAsync(): bool;

    public function submit(ImageGenerationRequest $request): ImageGenerationResponse;

    public function poll(string $providerJobId): ImageGenerationStatus;

    public function download(ImageGenerationResponse $response): array;
}
```

## DTOها

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

## Provider selection

اولویت انتخاب provider:

```text
job override
  -> lead override
  -> tenant default provider
  -> system default provider
  -> sample_image
```

در MVP system default باید `sample_image` باشد.

## وضعیت jobها

```text
pending
submitted
processing
completed
failed
retry_wait
```

## Queue design

هر period یک job جدا داشته باشد:

```text
3_months
6_months
12_months
```

مزیت:

- خطای یک period باعث شکست همه خروجی‌ها نمی‌شود.
- retry مستقل است.
- زمان‌بندی و هزینه بهتر کنترل می‌شود.

## Real Provider Strategy

برای providerهای واقعی، implementation باید جدا باشد:

```text
App\Services\AI\Providers\GapGptProvider
App\Services\AI\Providers\NanoBananaProvider
App\Services\AI\Providers\OpenAiImageProvider
App\Services\AI\Providers\CustomHttpProvider
```

هر provider باید تنظیمات خودش را از `tenant_ai_settings.settings_json` بگیرد.

## خطاها

خطاهای provider نباید به کاربر خام نمایش داده شوند. در API کاربر فقط پیام عمومی نمایش داده شود:

```json
{
  "message": "پردازش تصویر با خطا مواجه شد. لطفاً بعداً دوباره تلاش کنید."
}
```

جزئیات در `generation_jobs.last_error` و log ذخیره شود.
