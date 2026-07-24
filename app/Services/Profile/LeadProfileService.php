<?php

namespace App\Services\Profile;

use App\Models\GenerationAttempt;
use App\Models\GenerationRequest;
use App\Models\Lead;
use App\Models\LeadProfile;
use App\Models\LeadSourceImage;
use App\Services\Billing\CreditLedgerService;
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
    /**
     * Create a new lead profile service instance.
     *
     * @param  CreditLedgerService  $credits  The credits value.
     *
     * @since 2.0.0
     */
    public function __construct(private readonly CreditLedgerService $credits) {}

    /**
     * Execute the profile for lead operation.
     *
     * @param  Lead  $lead  Lead in the current tenant scope.
     * @return LeadProfile Resolved operation result.
     *
     * @since 2.0.0
     */
    public function profileForLead(Lead $lead): LeadProfile
    {
        return LeadProfile::query()->firstOrCreate([
            'tenant_id' => $lead->tenant_id,
            'lead_id' => $lead->id,
        ]);
    }

    /**
     * Execute the replace source image operation.
     *
     * @param  Lead  $lead  Lead in the current tenant scope.
     * @param  UploadedFile  $file  The file value.
     * @return LeadSourceImage Resolved operation result.
     *
     * @since 2.0.0
     */
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

    /**
     * Cancel queued requests for source.
     *
     * @param  LeadSourceImage  $sourceImage  The source image value.
     * @return void No value is returned.
     *
     * @since 2.0.0
     */
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

            $attempts = GenerationAttempt::query()
                ->where('generation_request_id', $request->id)
                ->whereIn('status', ['queued', 'reserved', 'dispatched'])
                ->lockForUpdate()
                ->get();

            foreach ($attempts as $attempt) {
                $attempt->forceFill([
                    'status' => 'cancelled',
                    'safe_failure_class' => 'source_image_replaced',
                    'safe_error_message' => __('messages.source_image_replaced'),
                ])->save();
                $this->credits->releaseReservation($attempt, __('messages.source_image_replaced'));
            }
        }
    }
}
