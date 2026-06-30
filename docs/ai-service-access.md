# AI Service Access

AI generation must pass both subscription and model-access checks.

Before creating generation jobs, `GenerationService` checks that the clinic has:

1. an active base subscription
2. an active entitlement for `hair_transplant`
3. enough remaining service credit for the three result periods
4. an active AI model access assignment for the same usage category

Generation jobs consume one service credit only after the model/provider path has
successfully completed for that period. Failed provider responses do not consume
service credit. Failed model access still follows the existing AI model usage log
rules and does not consume model credit when provider execution fails.

Current public visitor generation uses `hair_transplant`. The service catalog and
plan system also define `eyebrow_transplant` for add-ons and future generation
flows.
