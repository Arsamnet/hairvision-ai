<?php

namespace App\Services\Generation;

use App\Models\AiModelCategory;
use App\Models\AiModelCategoryOutput;
use App\Models\GenerationAttempt;
use App\Models\GenerationRequest;
use App\Models\Lead;
use App\Models\LeadSourceImage;
use App\Services\Billing\CreditLedgerService;
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
    /**
     * Create a new independent generation service instance.
     *
     * @param  ServiceEntitlementService  $entitlements  The entitlements value.
     * @param  CreditLedgerService  $credits  The credits value.
     * @param  AiModelRouter  $router  The router value.
     * @param  PublicApplicationAvailabilityService  $availability  The availability value.
     * @param  ModelQueueCoordinator  $queue  The queue value.
     *
     * @since 2.0.0
     */
    public function __construct(
        private readonly ServiceEntitlementService $entitlements,
        private readonly CreditLedgerService $credits,
        private readonly AiModelRouter $router,
        private readonly PublicApplicationAvailabilityService $availability,
        private readonly ModelQueueCoordinator $queue,
    ) {}

    /**
     * Display the form for creating a new independent generation service.
     *
     * @param  Lead  $lead  Lead in the current tenant scope.
     * @param  LeadSourceImage  $sourceImage  The source image value.
     * @param  AiModelCategory  $category  The category value.
     * @param  AiModelCategoryOutput  $output  The output value.
     * @param  string  $idempotencyKey  The idempotency key value.
     * @return GenerationRequest Resolved operation result.
     *
     * @since 2.0.0
     */
    public function create(
        Lead $lead,
        LeadSourceImage $sourceImage,
        AiModelCategory $category,
        AiModelCategoryOutput $output,
        string $idempotencyKey,
    ): GenerationRequest {
        $request = DB::transaction(function () use ($lead, $sourceImage, $category, $output, $idempotencyKey): GenerationRequest {
            Lead::query()->whereKey($lead->id)->lockForUpdate()->firstOrFail();

            $existing = GenerationRequest::query()
                ->where('tenant_id', $lead->tenant_id)
                ->where('lead_id', $lead->id)
                ->where('idempotency_key', $idempotencyKey)
                ->with(['category', 'output', 'currentAsset', 'attempts.model'])
                ->first();

            if ($existing) {
                return $existing;
            }

            $lockedSource = LeadSourceImage::query()->lockForUpdate()->findOrFail($sourceImage->id);

            if (! $lockedSource->is_current) {
                throw ValidationException::withMessages([
                    'source_image_id' => __('messages.source_image_not_current'),
                ]);
            }

            $this->availability->assertCanRequest($lead->tenant, $category, $output);
            $this->entitlements->assertCanUse($lead->tenant, $category->code, max(1, (int) $output->credit_cost));
            $model = $this->router->selectFor($lead->tenant, $category, $output);

            $request = GenerationRequest::query()->create([
                'tenant_id' => $lead->tenant_id,
                'lead_id' => $lead->id,
                'lead_source_image_id' => $lockedSource->id,
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

            $this->credits->reserveForAttempt(
                $attempt,
                $lead->tenant,
                $category->code,
                max(1, (int) $output->credit_cost),
                __('credits.descriptions.ai_usage', ['period' => $output->name]),
            );

            return $request->load(['category', 'output', 'currentAsset', 'attempts.model']);
        });

        $this->dispatchAttempt($request->attempts->last());

        return $request->refresh()->load(['category', 'output', 'currentAsset', 'attempts.model']);
    }

    /**
     * Dispatch attempt.
     *
     * @param  GenerationAttempt|null  $attempt  The attempt value.
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
    public function dispatchAttempt(?GenerationAttempt $attempt): void
    {
        if (! $attempt instanceof GenerationAttempt) {
            return;
        }

        $this->queue->dispatchAvailableForModel((int) $attempt->ai_model_id);
    }

    /**
     * Create a paid regeneration attempt while preserving the current result.
     *
     * @param  GenerationRequest  $generation  The generation value.
     * @param  Lead  $lead  Lead in the current tenant scope.
     * @return GenerationRequest Resolved operation result.
     *
     * @since 2.0.0
     */
    public function regenerate(GenerationRequest $generation, Lead $lead): GenerationRequest
    {
        $attempt = DB::transaction(function () use ($generation, $lead): GenerationAttempt {
            Lead::query()->whereKey($lead->id)->lockForUpdate()->firstOrFail();
            $locked = GenerationRequest::query()
                ->with(['sourceImage', 'category', 'output', 'attempts'])
                ->lockForUpdate()
                ->findOrFail($generation->id);

            if (! $locked->sourceImage?->is_current) {
                throw ValidationException::withMessages([
                    'source_image_id' => __('messages.source_image_not_current'),
                ]);
            }

            if ($locked->attempts->contains(fn (GenerationAttempt $item): bool => in_array($item->status, GenerationAttempt::NON_TERMINAL_STATUSES, true))) {
                throw ValidationException::withMessages([
                    'generation' => __('messages.generation_already_processing'),
                ]);
            }

            $category = $locked->category;
            $output = $locked->output;

            if (! $category || ! $output) {
                throw ValidationException::withMessages([
                    'generation' => __('messages.generation_output_invalid'),
                ]);
            }

            $this->availability->assertCanRequest($lead->tenant, $category, $output);
            $model = $this->router->selectFor($lead->tenant, $category, $output);
            $attempt = $locked->attempts()->create([
                'tenant_id' => $lead->tenant_id,
                'lead_id' => $lead->id,
                'ai_model_id' => $model->id,
                'attempt_number' => ((int) $locked->attempts->max('attempt_number')) + 1,
                'status' => 'queued',
                'prompt' => $output->prompt,
            ]);

            $this->credits->reserveForAttempt(
                $attempt,
                $lead->tenant,
                $category->code,
                max(1, (int) $output->credit_cost),
                __('credits.descriptions.ai_usage', ['period' => $output->name]),
            );
            $locked->forceFill([
                'status' => 'queued',
                'failed_at' => null,
                'safe_error_code' => null,
                'safe_error_message' => null,
            ])->save();

            return $attempt;
        });

        $this->dispatchAttempt($attempt);

        return $generation->refresh()->load(['sourceImage', 'category', 'output', 'currentAsset', 'attempts']);
    }
}
