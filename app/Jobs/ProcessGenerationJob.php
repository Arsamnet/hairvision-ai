<?php

namespace App\Jobs;

use App\Actions\Ai\RunAiModelAction;
use App\Data\Ai\AiModelResponseData;
use App\Models\GeneratedAsset;
use App\Models\GenerationJob;
use App\Services\AI\Dtos\ImageGenerationRequest;
use App\Services\AI\Dtos\ImageGenerationResponse;
use App\Services\AI\Resolvers\ProviderResolver;
use App\Services\Billing\CreditLedgerService;
use App\Services\Generation\FinalGenerationSmsService;
use App\Services\Generation\GenerationStageCatalog;
use App\Services\GenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesAndRestoresModelIdentifiers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Processes one tenant- and lead-scoped generation stage.
 *
 * The job resolves provider adapters, stores one private output, and advances
 * the group to the next stage when this stage completes.
 *
 * @since 1.0.0
 */
class ProcessGenerationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesAndRestoresModelIdentifiers, SerializesModels;

    /**
     * @param  GenerationJob  $generationJob  Stage job to process.
     */
    public function __construct(public GenerationJob $generationJob) {}

    /**
     * Execute provider submission and persist one completed stage output.
     *
     * Side effects: updates generation status, writes generated assets, and
     * stores provider metadata for internal review.
     */
    public function handle(): void
    {
        $this->generationJob->refresh();

        if ($this->generationJob->status === 'completed' || $this->generationJob->asset()->exists()) {
            $this->generationJob->update([
                'status' => 'completed',
                'completed_at' => $this->generationJob->completed_at ?? now(),
            ]);
            $this->completeGroupOrDispatchNext();

            return;
        }

        $this->generationJob->update([
            'status' => 'processing',
            'started_at' => now(),
            'attempts' => $this->generationJob->attempts + 1,
        ]);

        $resolver = new ProviderResolver;
        $provider = $resolver->resolveForTenant($this->generationJob->tenant_id);

        $upload = $this->generationJob->upload;
        $lead = $this->generationJob->lead;

        if ($this->generationJob->aiModelAccess) {
            try {
                $response = app(RunAiModelAction::class)->execute(
                    clinic: $this->generationJob->tenant,
                    usageCategoryKey: $this->generationJob->aiModelAccess->usage_category_key,
                    payload: [
                        'generation_job_id' => $this->generationJob->uuid,
                        'upload_id' => $upload->uuid,
                        'upload_disk' => $upload->original_disk,
                        'upload_path' => $upload->original_path,
                        'period' => $this->generationJob->period,
                        'prompt' => $this->generationJob->prompt,
                    ],
                    selectedAccess: $this->generationJob->aiModelAccess,
                );

                if (! $response->successful) {
                    $this->markFailed($response->safeErrorMessage ?: __('ai_models.errors.provider_failed'));

                    return;
                }

                if ($this->aiResponseHasImage($response)) {
                    if (! $this->consumeServiceCredit()) {
                        return;
                    }

                    $this->storeGeneratedBinaryAsset($response);
                    $this->markCompletedAndContinue();

                    return;
                }
            } catch (Throwable) {
                $this->markFailed(__('ai_models.errors.provider_failed'));

                return;
            }
        }

        $request = new ImageGenerationRequest(
            tenantUuid: $lead->tenant->uuid,
            leadUuid: $lead->uuid,
            uploadPath: $upload->original_path,
            period: $this->generationJob->period,
            prompt: $this->generationJob->prompt,
            modelName: $this->generationJob->model_name,
            settings: $this->generationJob->aiModelAccess?->settings ?? [],
        );

        $response = $provider->submit($request);

        if ($response->status !== 'completed' || ! $response->localPath) {
            $this->markFailed('sample provider did not return completed');

            return;
        }

        if (! $this->consumeServiceCredit()) {
            return;
        }

        $this->storeGeneratedLocalAsset($response);
        $this->markCompletedAndContinue();
    }

    /**
     * Mark the current stage as completed and advance the generation group.
     */
    private function markCompletedAndContinue(): void
    {
        $this->generationJob->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        $this->completeGroupOrDispatchNext();
    }

    /**
     * Complete the batch once all stages are ready, otherwise start the next stage.
     */
    private function completeGroupOrDispatchNext(): void
    {
        $group = $this->generationJob->group()->with('jobs')->firstOrFail();
        $wasCompleted = $group->status === 'completed';

        if ($group->jobs->every(fn (GenerationJob $job): bool => $job->status === 'completed')) {
            $group->update(['status' => 'completed']);

            if (! $wasCompleted) {
                app(FinalGenerationSmsService::class)->sendCompleted($group->refresh());
            }
        } else {
            $group->update(['status' => 'processing']);
            app(GenerationService::class)->dispatchNextStage($group);
        }
    }

    /**
     * Mark the current stage and group as safely failed.
     */
    private function markFailed(string $message): void
    {
        $this->generationJob->update([
            'status' => 'failed',
            'last_error' => $message,
        ]);

        $this->generationJob->group->update(['status' => 'failed']);
    }

    private function consumeServiceCredit(): bool
    {
        try {
            app(CreditLedgerService::class)->consume(
                tenant: $this->generationJob->tenant,
                serviceKey: $this->generationJob->aiModelAccess?->usage_category_key ?? 'hair_transplant',
                related: $this->generationJob,
                description: __('credits.descriptions.ai_usage', ['period' => app(GenerationStageCatalog::class)->label($this->generationJob->period)]),
            );
        } catch (ValidationException $exception) {
            $this->markFailed(collect($exception->errors())->flatten()->first() ?: __('credits.errors.consume_failed'));

            return false;
        } catch (Throwable) {
            $this->markFailed(__('credits.errors.consume_failed'));

            return false;
        }

        return true;
    }

    private function aiResponseHasImage(AiModelResponseData $response): bool
    {
        return is_string($response->output['binary_content'] ?? null)
            && ($response->output['binary_content'] ?? '') !== '';
    }

    /**
     * Copy provider output into tenant/lead private storage and record metadata.
     */
    private function storeGeneratedLocalAsset(ImageGenerationResponse $response): void
    {
        $source = Storage::disk('local');
        $sourcePath = $response->localPath;
        $destination = $this->destinationPath('jpg');

        $source->makeDirectory(dirname($destination));
        $source->copy($sourcePath, $destination);

        $this->createAsset([
            'disk' => 'local',
            'path' => $destination,
            'mime' => $response->metadata['mime'] ?? $source->mimeType($destination) ?: 'image/jpeg',
            'size' => $response->metadata['size'] ?? $source->size($destination),
            'provider_metadata_json' => $response->metadata,
        ]);
    }

    /**
     * Persist generated bytes returned by a real image edit provider.
     */
    private function storeGeneratedBinaryAsset(AiModelResponseData $response): void
    {
        $disk = Storage::disk('local');
        $extension = (string) ($response->output['extension'] ?? 'jpg');
        $mime = (string) ($response->output['mime'] ?? 'image/jpeg');
        $destination = $this->destinationPath($extension);

        $disk->makeDirectory(dirname($destination));
        $disk->put($destination, $response->output['binary_content']);

        $this->createAsset([
            'disk' => 'local',
            'path' => $destination,
            'mime' => str_starts_with($mime, 'image/') ? $mime : 'image/jpeg',
            'size' => $disk->size($destination),
            'provider_metadata_json' => $response->metadata,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createAsset(array $attributes): void
    {
        if ($this->generationJob->asset()->exists()) {
            return;
        }

        GeneratedAsset::create([
            'generation_group_id' => $this->generationJob->generation_group_id,
            'generation_job_id' => $this->generationJob->id,
            'tenant_id' => $this->generationJob->tenant_id,
            'lead_id' => $this->generationJob->lead_id,
            'period' => $this->generationJob->period,
        ] + $attributes);
    }

    private function destinationPath(string $extension): string
    {
        $extension = ltrim(strtolower($extension), '.');
        $extension = in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true) ? $extension : 'jpg';

        return 'tenants/'.$this->generationJob->tenant->uuid
            .'/leads/'.$this->generationJob->lead->uuid
            .'/results/'.$this->generationJob->group->uuid
            .'/'.$this->generationJob->period.'.'.$extension;
    }
}
