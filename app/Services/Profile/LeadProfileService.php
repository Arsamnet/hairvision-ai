<?php

namespace App\Services\Profile;

use App\Models\GenerationAttempt;
use App\Models\GenerationRequest;
use App\Models\Lead;
use App\Models\LeadProfile;
use App\Models\LeadSourceImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Manages tenant-scoped lead profiles and source image replacement.
 *
 * @since 2.0.0
 */
class LeadProfileService
{
    public function profileForLead(Lead $lead): LeadProfile
    {
        return LeadProfile::query()->firstOrCreate([
            'tenant_id' => $lead->tenant_id,
            'lead_id' => $lead->id,
        ]);
    }

    public function replaceSourceImage(Lead $lead, UploadedFile $file): LeadSourceImage
    {
        $disk = 'local';
        $directory = 'tenants/'.$lead->tenant->uuid.'/leads/'.$lead->uuid.'/source-images';
        $path = $file->store($directory, ['disk' => $disk]);

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Source image file could not be stored.');
        }

        $checksum = hash_file('sha256', Storage::disk($disk)->path($path)) ?: null;

        return DB::transaction(function () use ($lead, $file, $disk, $path, $checksum): LeadSourceImage {
            $profile = LeadProfile::query()
                ->where('tenant_id', $lead->tenant_id)
                ->where('lead_id', $lead->id)
                ->lockForUpdate()
                ->first();

            if (! $profile) {
                $profile = LeadProfile::query()->create([
                    'tenant_id' => $lead->tenant_id,
                    'lead_id' => $lead->id,
                ]);

                $profile->refresh();
            }

            $previous = LeadSourceImage::query()
                ->where('tenant_id', $lead->tenant_id)
                ->where('lead_id', $lead->id)
                ->where('is_current', true)
                ->lockForUpdate()
                ->get();

            foreach ($previous as $sourceImage) {
                $sourceImage->forceFill([
                    'is_current' => false,
                    'replaced_at' => now(),
                ])->save();

                $this->cancelQueuedRequestsForSource($sourceImage);
            }

            $nextVersion = ((int) LeadSourceImage::query()
                ->where('tenant_id', $lead->tenant_id)
                ->where('lead_id', $lead->id)
                ->max('version')) + 1;

            return LeadSourceImage::query()->create([
                'tenant_id' => $lead->tenant_id,
                'lead_id' => $lead->id,
                'lead_profile_id' => $profile->id,
                'version' => $nextVersion,
                'is_current' => true,
                'disk' => $disk,
                'path' => $path,
                'mime' => $file->getClientMimeType(),
                'size' => (int) $file->getSize(),
                'checksum' => $checksum,
                'status' => 'validated',
            ]);
        });
    }

    private function cancelQueuedRequestsForSource(LeadSourceImage $sourceImage): void
    {
        $requests = GenerationRequest::query()
            ->where('lead_source_image_id', $sourceImage->id)
            ->whereIn('status', ['queued'])
            ->get();

        foreach ($requests as $request) {
            $request->update([
                'status' => 'cancelled',
                'safe_error_code' => 'source_image_replaced',
                'safe_error_message' => __('messages.source_image_replaced'),
            ]);

            GenerationAttempt::query()
                ->where('generation_request_id', $request->id)
                ->whereIn('status', ['queued', 'dispatched'])
                ->update([
                    'status' => 'cancelled',
                    'safe_failure_class' => 'source_image_replaced',
                    'safe_error_message' => __('messages.source_image_replaced'),
                    'updated_at' => now(),
                ]);
        }
    }
}
