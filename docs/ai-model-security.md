# AI Model Security

AI model access management is a system-admin feature. Clinic/customer users must
not see global model configuration, access credentials, or provider details.

## Credential Storage

`AiModelAccess.credentials` uses Laravel's encrypted array cast and is hidden
from model serialization.

Rules:

- Real credentials are stored only in encrypted database values.
- Credentials are never written into config files.
- Admin UI shows only masked summaries.
- Blank credential fields during update preserve the stored secret.
- Validation errors name fields but never echo submitted secret values.
- Usage logs do not store credentials.

## Authorization

Global AI model management is protected by:

- `permission:*` route middleware
- `AiModelAccessPolicy`
- `StoreAiModelAccessRequest`
- `UpdateAiModelAccessRequest`
- `StoreClinicAiModelAccessRequest`

Super Admin users receive all AI model permissions through the permission
catalog, but protected demo deletion still runs through the policy delete check
and is denied.

Clinic/customer users do not receive global `ai_models.*` permissions from the
default seeded roles.

## Clinic Isolation

Clinic assignment uses tenant ids from route model binding and authenticated
dashboard context. Public generation resolves the clinic from the request host.

Backend services enforce:

- model record is active
- clinic assignment exists
- clinic assignment is active
- usage category matches
- credit is available

Client-controlled `tenant_id` or clinic id values are not accepted in public
generation requests.

## Credit and Logs

Limited-credit records consume one credit only after a provider response
succeeds. Failed provider responses create failed logs and do not increment
credit.

`ai_model_usage_logs` stores safe metadata only:

- clinic id
- user id when available
- access record id
- usage category
- status
- consumed credit
- opaque request/response references
- safe error code/message

Do not add API keys, raw provider payloads, private image paths, or visitor
photos to usage logs.
