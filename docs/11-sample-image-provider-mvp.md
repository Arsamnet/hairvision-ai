# 11 - Sample Image Provider MVP

## هدف

در MVP، تا زمانی که سرویس AI واقعی آماده نیست، باید یک provider تستی داشته باشیم که هیچ عکس جدیدی تولید نکند و هیچ API خارجی را صدا نزند.

نام provider:

```text
sample_image
```

کلاس پیشنهادی:

```text
App\Services\AI\Providers\SampleImageProvider
```

## رفتار provider

`SampleImageProvider` باید:

1. درخواست generation را دریافت کند.
2. بر اساس period، فایل نمونه آماده را انتخاب کند.
3. فایل نمونه را در مسیر results همان lead کپی کند.
4. generated_asset بسازد.
5. generation_job را completed کند.

این provider نباید:

- به اینترنت وصل شود.
- API خارجی صدا بزند.
- تصویر جدید بسازد.
- تصویر آپلودی کاربر را تغییر دهد.
- خروجی random از URLهای خارجی بگیرد.

## مسیر تصاویر نمونه

پیشنهاد:

```text
storage/app/private/sample-results/3-months.jpg
storage/app/private/sample-results/6-months.jpg
storage/app/private/sample-results/12-months.jpg
```

اگر فقط یک تصویر نمونه داشتیم:

```text
storage/app/private/sample-results/hairvision-sample-result.jpg
```

و برای هر سه period همان فایل کپی شود.

## تنظیمات env

```env
HAIRVISION_DEFAULT_AI_PROVIDER=sample_image
HAIRVISION_SAMPLE_IMAGE_3_MONTHS=sample-results/3-months.jpg
HAIRVISION_SAMPLE_IMAGE_6_MONTHS=sample-results/6-months.jpg
HAIRVISION_SAMPLE_IMAGE_12_MONTHS=sample-results/12-months.jpg
HAIRVISION_SAMPLE_IMAGE_FALLBACK=sample-results/hairvision-sample-result.jpg
```

## شبه‌کد

```php
final class SampleImageProvider implements ImageGenerationProvider
{
    public function code(): string
    {
        return 'sample_image';
    }

    public function supportsAsync(): bool
    {
        return false;
    }

    public function submit(ImageGenerationRequest $request): ImageGenerationResponse
    {
        $samplePath = $this->resolveSamplePath($request->period);

        return new ImageGenerationResponse(
            status: 'completed',
            localPath: $samplePath,
            metadata: [
                'provider' => 'sample_image',
                'period' => $request->period,
                'note' => 'MVP sample image only. No AI generation performed.',
            ]
        );
    }

    public function poll(string $providerJobId): ImageGenerationStatus
    {
        return new ImageGenerationStatus('completed');
    }

    public function download(ImageGenerationResponse $response): array
    {
        return [
            'type' => 'local_path',
            'path' => $response->localPath,
        ];
    }
}
```

## Job implementation expectation

Job باید خروجی sample را به مسیر اختصاصی tenant/lead کپی کند:

```text
storage/app/private/tenants/{tenant_uuid}/leads/{lead_uuid}/results/{generation_group_uuid}/{period}.jpg
```

## Seed اولیه

Seeder باید provider را ایجاد کند:

```text
ai_providers.code = sample_image
ai_providers.name = Sample Image Provider
ai_providers.type = local
ai_providers.is_active = true
ai_providers.supports_async = false
```

و برای tenant تست، همین provider را default کند.

## تست‌های ضروری

- provider هیچ HTTP request خارجی ارسال نمی‌کند.
- اگر فایل sample وجود ندارد، job failed شود و پیام واضح ثبت کند.
- برای هر سه period asset ساخته شود.
- assetها tenant_id درست داشته باشند.
- کاربر tenant دیگر نتواند asset را ببیند.
