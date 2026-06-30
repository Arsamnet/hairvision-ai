<?php

namespace App\Jobs;

use App\Actions\Ai\RunAiModelAction;
use App\Models\GeneratedAsset;
use App\Models\GenerationJob;
use App\Services\AI\Dtos\ImageGenerationRequest;
use App\Services\AI\Dtos\ImageGenerationResponse;
use App\Services\AI\Resolvers\ProviderResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesAndRestoresModelIdentifiers;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Processes one tenant- and lead-scoped generation period.
 *
 * The job resolves provider adapters, copies local sample outputs into private
 * result storage, and updates generation job/group status.
 *
 * @since 1.0.0
 */
class ProcessGenerationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesAndRestoresModelIdentifiers, SerializesModels;

    /**
     * @param  GenerationJob  $generationJob  Period job to process.
     */
    public function __construct(public GenerationJob $generationJob) {}

    /**
     * Execute provider submission and persist completed sample output.
     *
     * Side effects: updates generation status, writes generated assets, and
     * stores provider metadata for internal review.
     */
    public function handle(): void
    {
        $this->generationJob->update([
            'status' => 'processing',
            'started_at' => now(),
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
                        'period' => $this->generationJob->period,
                    ],
                    selectedAccess: $this->generationJob->aiModelAccess,
                );

                if (! $response->successful) {
                    $this->markFailed($response->safeErrorMessage ?: __('ai_models.errors.provider_failed'));

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
        );

        $response = $provider->submit($request);

        if ($response->status !== 'completed' || ! $response->localPath) {
            $this->markFailed('sample provider did not return completed');

            return;
        }

        $this->storeGeneratedAsset($response);

        $this->generationJob->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        if ($this->generationJob->group->jobs()->where('status', '!=', 'completed')->count() === 0) {
            $this->generationJob->group->update(['status' => 'completed']);
        } else {
            $this->generationJob->group->update(['status' => 'processing']);
        }
    }

    /**
     * Mark the current period and group as safely failed.
     */
    private function markFailed(string $message): void
    {
        $this->generationJob->update([
            'status' => 'failed',
            'last_error' => $message,
        ]);

        $this->generationJob->group->update(['status' => 'partially_failed']);
    }

    /**
     * Copy provider output into tenant/lead private storage and record metadata.
     */
    private function storeGeneratedAsset(ImageGenerationResponse $response): void
    {
        $source = Storage::disk('local');
        $sourcePath = $response->localPath;
        $destination = 'tenants/'.$this->generationJob->tenant->uuid.'/leads/'.$this->generationJob->lead->uuid.'/results/'.$this->generationJob->group->uuid.'/'.$this->generationJob->period.'.jpg';

        $source->makeDirectory(dirname($destination));
        $source->copy($sourcePath, $destination);

        GeneratedAsset::create([
            'generation_group_id' => $this->generationJob->generation_group_id,
            'generation_job_id' => $this->generationJob->id,
            'tenant_id' => $this->generationJob->tenant_id,
            'lead_id' => $this->generationJob->lead_id,
            'period' => $this->generationJob->period,
            'disk' => 'local',
            'path' => $destination,
            'mime' => $response->metadata['mime'] ?? $source->mimeType($destination) ?: 'image/jpeg',
            'size' => $response->metadata['size'] ?? $source->size($destination),
            'provider_metadata_json' => $response->metadata,
        ]);
    }
}
