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
use Illuminate\Support\Facades\Log;
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
        $jobId = $this->generationJob->uuid;
        $groupId = $this->generationJob->group->uuid;
        $period = $this->generationJob->period;
        
        Log::info('Generation job started', [
            'generation_job_id' => $jobId,
            'generation_group_id' => $groupId,
            'period' => $period,
            'tenant_id' => $this->generationJob->tenant_id,
            'lead_id' => $this->generationJob->lead_id,
            'model_key' => $this->generationJob->model_name,
        ]);
        
        $this->generationJob->refresh();

        // Check if already completed or asset already exists
        if ($this->generationJob->status === 'completed' || $this->generationJob->asset()->exists()) {
            Log::info('Generation job already completed', [
                'generation_job_id' => $jobId,
                'status' => $this->generationJob->status,
                'has_asset' => $this->generationJob->asset()->exists(),
            ]);
            
            $this->generationJob->update([
                'status' => 'completed',
                'completed_at' => $this->generationJob->completed_at ?? now(),
            ]);
            $this->completeGroupOrDispatchNext();

            return;
        }

        // Mark as processing
        $this->generationJob->update([
            'status' => 'processing',
            'started_at' => now(),
            'attempts' => $this->generationJob->attempts + 1,
        ]);

        Log::info('Generation job marked as processing', [
            'generation_job_id' => $jobId,
            'attempt' => $this->generationJob->attempts,
        ]);

        $resolver = new ProviderResolver;
        $provider = $resolver->resolveForTenant($this->generationJob->tenant_id);

        $upload = $this->generationJob->upload;
        $lead = $this->generationJob->lead;

        // Use new RunAiModelAction if model access is available
        if ($this->generationJob->aiModelAccess) {
            try {
                Log::info('Executing AI model via RunAiModelAction', [
                    'generation_job_id' => $jobId,
                    'model_key' => $this->generationJob->aiModelAccess->model_key,
                    'usage_category' => $this->generationJob->aiModelAccess->usage_category_key,
                ]);
                
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

                Log::info('AI model execution completed', [
                    'generation_job_id' => $jobId,
                    'successful' => $response->successful,
                    'status' => $response->status,
                    'error_code' => $response->errorCode,
                ]);

                if (! $response->successful) {
                    $errorMsg = $response->safeErrorMessage ?: __('ai_models.errors.provider_failed');
                    Log::warning('AI model execution failed', [
                        'generation_job_id' => $jobId,
                        'error_code' => $response->errorCode,
                        'error_message' => $errorMsg,
                    ]);
                    $this->markFailed($errorMsg);

                    return;
                }

                if ($this->aiResponseHasImage($response)) {
                    if (! $this->consumeServiceCredit()) {
                        return;
                    }

                    Log::info('Generated image received, storing asset', [
                        'generation_job_id' => $jobId,
                        'period' => $period,
                    ]);

                    $this->storeGeneratedBinaryAsset($response);
                    $this->markCompletedAndContinue();

                    return;
                }

                Log::warning('AI model response missing image', [
                    'generation_job_id' => $jobId,
                    'response_keys' => array_keys($response->output),
                ]);
                $this->markFailed(__('ai_models.errors.missing_generated_image'));

                return;
            } catch (Throwable $exception) {
                Log::error('AI model execution exception', [
                    'generation_job_id' => $jobId,
                    'exception_class' => $exception::class,
                    'exception_message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                ]);
                $this->markFailed(__('ai_models.errors.provider_failed'));

                return;
            }
        }

        // Legacy: Use ProviderResolver (for sample provider)
        Log::info('Using legacy provider via ProviderResolver', [
            'generation_job_id' => $jobId,
            'provider_class' => $provider::class,
        ]);
        
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

        Log::info('Legacy provider response received', [
            'generation_job_id' => $jobId,
            'response_status' => $response->status,
            'has_local_path' => ! empty($response->localPath),
        ]);

        if ($response->status !== 'completed' || ! $response->localPath) {
            Log::warning('Legacy provider did not return completed', [
                'generation_job_id' => $jobId,
                'status' => $response->status,
                'local_path' => $response->localPath,
            ]);
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
        $jobId = $this->generationJob->uuid;
        
        Log::info('Marking generation job as completed', [
            'generation_job_id' => $jobId,
            'period' => $this->generationJob->period,
        ]);
        
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
        $groupId = $group->uuid;
        $wasCompleted = $group->status === 'completed';
        
        $completedCount = $group->jobs->filter(fn (GenerationJob $job): bool => $job->status === 'completed')->count();
        Log::info('Checking group completion status', [
            'generation_group_id' => $groupId,
            'total_jobs' => $group->jobs->count(),
            'completed_jobs' => $completedCount,
        ]);

        if ($group->jobs->every(fn (GenerationJob $job): bool => $job->status === 'completed')) {
            Log::info('All generation jobs completed, marking group as complete', [
                'generation_group_id' => $groupId,
            ]);
            
            $group->update(['status' => 'completed']);

            if (! $wasCompleted) {
                Log::info('Sending completion SMS notification', [
                    'generation_group_id' => $groupId,
                ]);
                app(FinalGenerationSmsService::class)->sendCompleted($group->refresh());
            }
        } else {
            Log::info('More jobs pending, dispatching next stage', [
                'generation_group_id' => $groupId,
                'completed_jobs' => $completedCount,
                'total_jobs' => $group->jobs->count(),
            ]);
            
            $group->update(['status' => 'processing']);
            app(GenerationService::class)->dispatchNextStage($group);
        }
    }

    /**
     * Mark the current stage and group as safely failed.
     */
    private function markFailed(string $message): void
    {
        $jobId = $this->generationJob->uuid;
        $groupId = $this->generationJob->group->uuid;
        
        Log::error('Marking generation job as failed', [
            'generation_job_id' => $jobId,
            'generation_group_id' => $groupId,
            'period' => $this->generationJob->period,
            'error_message' => $message,
            'attempt' => $this->generationJob->attempts,
        ]);
        
        $this->generationJob->update([
            'status' => 'failed',
            'last_error' => $message,
        ]);

        $this->generationJob->group->update(['status' => 'failed']);
    }

    private function consumeServiceCredit(): bool
    {
        $jobId = $this->generationJob->uuid;
        
        try {
            Log::info('Consuming service credit for generation', [
                'generation_job_id' => $jobId,
                'service_key' => $this->generationJob->aiModelAccess?->usage_category_key ?? 'hair_transplant',
            ]);
            
            app(CreditLedgerService::class)->consume(
                tenant: $this->generationJob->tenant,
                serviceKey: $this->generationJob->aiModelAccess?->usage_category_key ?? 'hair_transplant',
                related: $this->generationJob,
                description: __('credits.descriptions.ai_usage', ['period' => app(GenerationStageCatalog::class)->label($this->generationJob->period)]),
            );
            
            Log::info('Service credit consumed successfully', [
                'generation_job_id' => $jobId,
            ]);
        } catch (ValidationException $exception) {
            $error = collect($exception->errors())->flatten()->first() ?: __('credits.errors.consume_failed');
            Log::warning('Service credit consumption failed - validation error', [
                'generation_job_id' => $jobId,
                'error' => $error,
            ]);
            $this->markFailed($error);

            return false;
        } catch (Throwable $exception) {
            Log::error('Service credit consumption failed - exception', [
                'generation_job_id' => $jobId,
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);
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
        $jobId = $this->generationJob->uuid;
        
        Log::info('Storing generated local asset', [
            'generation_job_id' => $jobId,
            'source_path' => $response->localPath,
        ]);
        
        $source = Storage::disk('local');
        $sourcePath = $response->localPath;
        $destination = $this->destinationPath('jpg');

        $source->makeDirectory(dirname($destination));
        $source->copy($sourcePath, $destination);

        Log::info('Asset file copied to destination', [
            'generation_job_id' => $jobId,
            'destination_path' => $destination,
        ]);

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
        $jobId = $this->generationJob->uuid;
        $extension = (string) ($response->output['extension'] ?? 'jpg');
        $mime = (string) ($response->output['mime'] ?? 'image/jpeg');
        
        Log::info('Storing generated binary asset', [
            'generation_job_id' => $jobId,
            'extension' => $extension,
            'mime' => $mime,
        ]);
        
        $disk = Storage::disk('local');
        $destination = $this->destinationPath($extension);

        $disk->makeDirectory(dirname($destination));
        $disk->put($destination, $response->output['binary_content']);

        Log::info('Binary asset written to disk', [
            'generation_job_id' => $jobId,
            'destination_path' => $destination,
            'file_size' => $disk->size($destination),
        ]);

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
        $jobId = $this->generationJob->uuid;
        
        if ($this->generationJob->asset()->exists()) {
            Log::warning('Asset already exists for this job', [
                'generation_job_id' => $jobId,
            ]);
            return;
        }

        Log::info('Creating generated asset record', [
            'generation_job_id' => $jobId,
            'path' => $attributes['path'],
            'disk' => $attributes['disk'],
        ]);
        
        GeneratedAsset::create([
            'generation_group_id' => $this->generationJob->generation_group_id,
            'generation_job_id' => $this->generationJob->id,
            'tenant_id' => $this->generationJob->tenant_id,
            'lead_id' => $this->generationJob->lead_id,
            'period' => $this->generationJob->period,
        ] + $attributes);
        
        Log::info('Generated asset record created', [
            'generation_job_id' => $jobId,
        ]);
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
