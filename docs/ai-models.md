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

## Access Records

Database access records live in `ai_model_accesses`.

Each record stores the configured model key, usage category, display name,
encrypted credentials, settings, prompt, active status, demo/protected flags,
priority, optional credit limit, consumed credit, and creator/updater ids.

Admins can create multiple access records for the same configured model. Stored
credentials can be replaced, but they are never displayed in plain text.

The demo seeder creates a GapGPT hair-transplant access record with three
stage-specific prompts under `settings.stage_prompts`. It stores a development
default API key and lets `GAPGPT_API_KEY` override that value. The seeded record
and Clinic Aria assignment are active so `php artisan migrate:fresh --seed`
produces a usable demo clinic without extra admin setup.

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
- `App\Services\AI\Providers\MockupAiProvider`

Controllers validate requests, authorize actions, and call these services.
Provider logic is not placed in controllers.
