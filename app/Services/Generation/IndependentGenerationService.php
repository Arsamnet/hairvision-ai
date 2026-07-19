<?php

namespace App\Services\Generation;

use App\Jobs\ProcessGenerationAttempt;
use App\Models\AiModelCategory;
use App\Models\AiModelCategoryOutput;
use App\Models\GenerationAttempt;
use App\Models\GenerationRequest;
use App\Models\Lead;
use App\Models\LeadSourceImage;
use App\Services\Billing\ServiceEntitlementService;
use App\Services\Visitor\PublicApplicationAvailabilityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates independent v2 output generation requests.
 *
 * @since 2.0.0
 */
class IndependentGenerationService
{
    public function __construct(
        private readonly ServiceEntitlementService $entitlements,
        private readonly AiModelRouter $router,
        private readonly PublicApplicationAvailabilityService $availability,
    ) {}

    public function create(
        Lead $lead,
        LeadSourceImage $sourceImage,
        AiModelCategory $category,
        AiModelCategoryOutput $output,
        string $idempotencyKey,
    ): GenerationRequest {
        if (! $sourceImage->is_current) {
            throw ValidationException::withMessages([
                'source_image_id' => __('messages.source_image_not_current'),
            ]);
        }

        $this->availability->assertCanRequest($lead->tenant, $category, $output);
        $this->entitlements->assertCanUse($lead->tenant, $category->code, max(1, (int) $output->credit_cost));

        $existing = GenerationRequest::query()
            ->where('tenant_id', $lead->tenant_id)
            ->where('lead_id', $lead->id)
            ->where('idempotency_key', $idempotencyKey)
            ->with(['category', 'output', 'currentAsset', 'attempts.model'])
            ->first();

        if ($existing) {
            return $existing;
        }

        $model = $this->router->selectFor($lead->tenant, $category, $output);

        $request = DB::transaction(function () use ($lead, $sourceImage, $category, $output, $idempotencyKey, $model): GenerationRequest {
            $request = GenerationRequest::query()->create([
                'tenant_id' => $lead->tenant_id,
                'lead_id' => $lead->id,
                'lead_source_image_id' => $sourceImage->id,
                'ai_model_category_id' => $category->id,
                'ai_model_category_output_id' => $output->id,
                'status' => 'queued',
                'idempotency_key' => $idempotencyKey,
                'requested_at' => now(),
            ]);

            $attempt = $request->attempts()->create([
                'tenant_id' => $lead->tenant_id,
                'lead_id' => $lead->id,
                'ai_model_id' => $model->id,
                'attempt_number' => 1,
                'status' => 'queued',
                'prompt' => $output->prompt,
            ]);

            $this->markAttemptDispatched($attempt);

            return $request->load(['category', 'output', 'currentAsset', 'attempts.model']);
        });

        $this->dispatchAttempt($request->attempts->first());

        return $request->refresh()->load(['category', 'output', 'currentAsset', 'attempts.model']);
    }

    public function dispatchAttempt(?GenerationAttempt $attempt): void
    {
        if (! $attempt instanceof GenerationAttempt) {
            return;
        }

        dispatch(new ProcessGenerationAttempt($attempt->refresh()));
    }

    private function markAttemptDispatched(GenerationAttempt $attempt): void
    {
        $attempt->forceFill([
            'status' => 'dispatched',
            'dispatched_at' => now(),
        ])->save();
    }
}
