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

The demo access is not editable from the dashboard. Admins may only toggle its
active state.

The `gapgpt` model is displayed in Persian as `ویرایش تصویر GapGPT`. It is a
real web-service provider and requires credentials before it can be activated.
It is the only dashboard-creatable access model in the current release and
supports `hair_transplant` and `eyebrow_transplant` usage categories.

## Access Records

Database access records live in `ai_model_accesses`.

Each record stores the configured model key, usage category, display name,
encrypted credentials, settings, prompt, active status, demo/protected flags,
priority, optional credit limit, consumed credit, and creator/updater ids.

Admins can create multiple access records for the same configured model. Stored
credentials can be replaced, but they are never displayed in plain text.

GapGPT credentials and request settings are stored on the selected access
record. The encrypted credential lives in
`ai_model_accesses.credentials.api_key`, and endpoint/model/size/response
format/timeouts/retry values live in `ai_model_accesses.settings`. The provider
does not read GapGPT-specific environment variables.

The demo seeder creates a GapGPT hair-transplant access record with default
request settings and three stage-specific prompts under
`settings.stage_prompts`. It never seeds a real API key, and the seeded GapGPT
record stays inactive until an admin enters the encrypted credential and
activates the access from the AI model dashboard.

## GapGPT Multipart Request

GapGPT image edits must use multipart form data, not JSON:

```text
POST https://api.gapgpt.app/v1/images/edits
Accept: application/json
Authorization: Bearer <encrypted access credential>
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

For each clinic and usage category, only one assigned access may be active at a
time. Assigning an active model access for `hair_transplant`, for example,
deactivates other active `hair_transplant` assignments for that clinic while
leaving active `eyebrow_transplant` assignments untouched.

## Usage Flow

Public generation still uses `/api/v1/generations`. The request payload did not
change.

Server-side flow:

1. Resolve the current tenant from the request host.
2. Resolve the verified lead session.
3. Resolve the upload by tenant and lead.
4. Resolve an active assigned AI model access record for `hair_transplant`.
5. Create one generation group and three period jobs.
6. For GapGPT, all three pending period jobs are dispatched together; the
   default staged flow dispatches the next pending job.
7. Each period job runs through `RunAiModelAction`.
8. The action validates assignment, active state, and credit.
9. The configured provider class runs through `AiModelProviderInterface`.
10. Successful limited-credit executions increment credit by one per period job.
11. A safe usage log is written.
12. The existing local `SampleImageProvider` copies private sample assets.

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
status badges, protected/demo badges, credit summaries, usage logs, dashboard
creation/editing for editable configured models, and clinic assignment links.

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
