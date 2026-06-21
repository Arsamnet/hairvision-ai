<?php

namespace App\Jobs;

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

class ProcessGenerationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesAndRestoresModelIdentifiers, SerializesModels;

    public function __construct(public GenerationJob $generationJob) {}

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
            $this->generationJob->update([
                'status' => 'failed',
                'last_error' => 'sample provider did not return completed',
            ]);

            $this->generationJob->group->update(['status' => 'partially_failed']);

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
