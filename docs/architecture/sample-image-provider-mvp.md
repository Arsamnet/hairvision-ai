# Sample Image Provider MVP

## Goal

Version `1.0.0` uses a local test provider named `sample_image`. It proves the
end-to-end product flow without depending on any real AI service.

Class:

```text
App\Services\AI\Providers\SampleImageProvider
```

## Required Behavior

`SampleImageProvider` must:

1. Receive a generation request.
2. Resolve the configured sample image for the requested period.
3. Return a completed local-path response when the sample exists.
4. Return a failed response when no sample is available.
5. Never call the internet.
6. Never call external AI APIs.
7. Never generate or modify images.
8. Never use random external image URLs.

## Sample Image Paths

Configured defaults:

```text
storage/app/private/sample-results/3-months.jpg
storage/app/private/sample-results/6-months.jpg
storage/app/private/sample-results/12-months.jpg
```

Environment variables:

```env
HAIRVISION_DEFAULT_AI_PROVIDER=sample_image
HAIRVISION_SAMPLE_IMAGE_3_MONTHS=sample-results/3-months.jpg
HAIRVISION_SAMPLE_IMAGE_6_MONTHS=sample-results/6-months.jpg
HAIRVISION_SAMPLE_IMAGE_12_MONTHS=sample-results/12-months.jpg
HAIRVISION_SAMPLE_IMAGE_FALLBACK=sample-results/hairvision-sample-result.jpg
```

The `local` disk root is `storage/app/private`, so configured paths are relative
to private storage.

## Debug and Local UX

When `APP_DEBUG=true`, `HAIRVISION_DEBUG_FAST_SAMPLES` defaults to true. In this
mode, sample image jobs run synchronously and the visitor UI should fetch and show
results immediately when the generation response reports completed jobs.

This debug/local path exists only to remove unnecessary waiting while testing the
sample provider flow. Real provider work must still use the queue.

## Job Output Path

Generation jobs copy the selected sample into:

```text
storage/app/private/tenants/{tenant_uuid}/leads/{lead_uuid}/results/{generation_group_uuid}/{period}.jpg
```

The generated asset database record must include:

- `tenant_id`
- `lead_id`
- `generation_group_id`
- `generation_job_id`
- `period`
- private disk/path metadata

## Seeder Requirements

The database seeder must create:

```text
ai_providers.code = sample_image
ai_providers.name = Sample Image Provider
ai_providers.type = local
ai_providers.is_active = true
ai_providers.supports_async = false
```

The test tenant must use this provider as its default.

## Required Tests

- Sample generation creates three completed jobs.
- Sample generation creates three generated assets.
- Generated assets include the correct `tenant_id` and `lead_id`.
- Assets are stored in private tenant/lead paths.
- Another tenant cannot access the assets.
- `SampleImageProvider` sends no external HTTP requests.
- Debug/local sample generation can return completed jobs immediately.
