# AI Models

HairVision keeps AI model types in code configuration and stores actual access
records in the database.

## Configuration

Configured model types live in `config/ai-models.php`.

The file defines:

- `usage_categories`
- `models`

The initial usage category is:

- `hair_transplant`: `کاشت مو`

The initial models are:

- `sample_image`: the protected HairVision demo model used by the MVP sample
  generation flow.
- `gapgpt`: the GapGPT image-edit model used by the seeded Clinic Aria demo for
  staged hair-transplant generation.

Config defines model capabilities, supported categories, provider class, timeout,
credential field names, prompt support, and credit support. It does not contain
real API keys or sensitive values.

The `sample_image` model is displayed in Persian as `مدل نمونه HairVision`. It is
a demo provider, requires no credentials, can be disabled, and is protected from
deletion. It must not call external services or generate new images. Each stage
returns one configured local sample image:

- `two_weeks`
- `intermediate_regrowth`
- `final_result`

The `gapgpt` model is displayed in Persian as `ویرایش تصویر GapGPT`. It is a
real web-service provider and requires credentials before it can be activated.

## Access Records

Database access records live in `ai_model_accesses`.

Each record stores the configured model key, usage category, display name,
encrypted credentials, settings, prompt, active status, demo/protected flags,
priority, optional credit limit, consumed credit, and creator/updater ids.

Admins can create multiple access records for the same configured model. Stored
credentials can be replaced, but they are never displayed in plain text.

Credential precedence for GapGPT:

1. Encrypted `ai_model_accesses.credentials.api_key` on the selected access
   record.
2. `config('services.gapgpt.api_key')`, backed by `GAPGPT_API_KEY`.

The demo seeder creates a GapGPT hair-transplant access record with three
stage-specific prompts under `settings.stage_prompts`. It never seeds a real API
key. The seeded GapGPT record is active only when `GAPGPT_API_KEY` is configured;
otherwise it is assigned to Clinic Aria but inactive.

GapGPT environment values:

```env
GAPGPT_API_KEY=<GAPGPT_API_KEY>
GAPGPT_IMAGE_EDIT_ENDPOINT=https://api.gapgpt.app/v1/images/edits
GAPGPT_IMAGE_MODEL=gpt-image-2
GAPGPT_IMAGE_SIZE=1024x1024
GAPGPT_IMAGE_RESPONSE_FORMAT=url
```

## GapGPT Multipart Request

GapGPT image edits must use multipart form data, not JSON:

```text
POST https://api.gapgpt.app/v1/images/edits
Accept: application/json
Authorization: Bearer <GAPGPT_API_KEY>
```

Required multipart fields:

- `image`: uploaded source image file
- `model`: `gpt-image-2`
- `prompt`: stage-specific prompt
- `n`: `1`
- `response_format`: `url`
- `size`: `1024x1024`

Do not set `Content-Type: application/json` for this request. Laravel's HTTP
client should create the multipart boundary automatically through `attach()`.
Automated tests use HTTP fakes and must not call the real GapGPT API.

## Clinic Assignment

Clinics are HairVision tenants. Assignments live in
`clinic_ai_model_access`.

A clinic can use only active `ai_model_accesses` rows that are actively assigned
to that clinic. Inactive model records, inactive assignments, unassigned records,
and finished-credit records are blocked by backend services before provider
execution.

## Usage Flow

Public generation still uses `/api/v1/generations`. The request payload did not
change.

Server-side flow:

1. Resolve the current tenant from the request host.
2. Resolve the verified lead session.
3. Resolve the upload by tenant and lead.
4. Resolve an active assigned AI model access record for `hair_transplant`.
5. Create one generation group and three period jobs.
6. Each period job runs through `RunAiModelAction`.
7. The action validates assignment, active state, and credit.
8. The configured provider class runs through `AiModelProviderInterface`.
9. Successful limited-credit executions increment credit by one per period job.
10. A safe usage log is written.
11. The existing local `SampleImageProvider` copies private sample assets.

## Admin Dashboard

AI model management routes:

- `GET /admin/ai-models`
- `GET /admin/ai-models/{aiModelAccess}`
- `POST /admin/ai-models`
- `PUT /admin/ai-models/{aiModelAccess}`
- `DELETE /admin/ai-models/{aiModelAccess}`
- `PATCH /admin/ai-models/{aiModelAccess}/toggle`
- `GET /admin/clinics/{clinic}/ai-models`
- `POST /admin/clinics/{clinic}/ai-models`
- `DELETE /admin/clinics/{clinic}/ai-models/{aiModelAccess}`

The dashboard supports backend search, filters, pagination, masked credentials,
status badges, protected/demo badges, credit summaries, usage logs, and clinic
assignment links.

Protected demo records cannot be deleted. They can be disabled.

## Services

Important classes:

- `App\Services\AI\AiModelResolver`
- `App\Services\AI\AiModelManager`
- `App\Actions\Ai\RunAiModelAction`
- `App\Actions\Ai\AssignAiModelToClinicAction`
- `App\Contracts\Ai\AiModelProviderInterface`
- `App\Services\AI\Providers\HairVisionDemoProvider`
- `App\Services\AI\Providers\GapGptImageEditProvider`

Controllers validate requests, authorize actions, and call these services.
Provider logic is not placed in controllers.
