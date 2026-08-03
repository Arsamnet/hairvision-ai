<?php

namespace App\Services\Admin;

use App\Models\GeneratedAsset;
use App\Models\GenerationAttempt;
use App\Models\GenerationJob;
use App\Models\GenerationRequest;
use App\Models\Lead;
use App\Models\LeadSourceImage;
use App\Models\Upload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Deletes a tenant-owned lead and its private file assets safely.
 *
 * @since 2.1.0
 */
class LeadDeletionService
{
    /**
     * Delete a lead after ensuring no generation work is still active.
     *
     * Database constraints remove tenant/lead-owned records and retain
     * system history whose lead foreign key is nullable. Private files are
     * removed only after the database transaction commits successfully.
     *
     * @param  Lead  $lead  Lead in the authorized tenant scope.
     * @return void No value is returned.
     *
     * @throws ValidationException When generation work is not terminal.
     *
     * @since 2.1.0
     */
    public function delete(Lead $lead): void
    {
        [$files, $directories] = DB::transaction(function () use ($lead): array {
            $lockedLead = Lead::query()
                ->with('tenant:id,uuid')
                ->lockForUpdate()
                ->findOrFail($lead->id);

            $this->ensureGenerationWorkIsTerminal($lockedLead);

            $files = $this->privateFiles($lockedLead);
            $directories = $this->privateDirectories($lockedLead);

            $lockedLead->delete();

            return [$files, $directories];
        });

        $this->deletePrivateFiles($files, $directories);
    }

    /**
     * Reject deletion while a legacy or independent generation is active.
     *
     * @param  Lead  $lead  Lead in the authorized tenant scope.
     * @return void No value is returned.
     *
     * @throws ValidationException When generation work is not terminal.
     *
     * @since 2.1.0
     */
    private function ensureGenerationWorkIsTerminal(Lead $lead): void
    {
        $scope = static fn ($query) => $query
            ->where('tenant_id', $lead->tenant_id)
            ->where('lead_id', $lead->id);

        $hasActiveRequest = $scope(GenerationRequest::query())
            ->whereNotIn('status', ['completed', 'failed', 'cancelled'])
            ->lockForUpdate()
            ->first(['id']) !== null;

        $hasActiveAttempt = $scope(GenerationAttempt::query())
            ->whereNotIn('status', ['completed', 'failed', 'cancelled', 'expired'])
            ->lockForUpdate()
            ->first(['id']) !== null;

        $hasActiveLegacyJob = $scope(GenerationJob::query())
            ->whereNotIn('status', GenerationJob::TERMINAL_STATUSES)
            ->lockForUpdate()
            ->first(['id']) !== null;

        if ($hasActiveRequest || $hasActiveAttempt || $hasActiveLegacyJob) {
            throw ValidationException::withMessages([
                'lead' => __('leads.delete_blocked_active_generation'),
            ]);
        }
    }

    /**
     * Collect private file references before cascade deletion removes metadata.
     *
     * @param  Lead  $lead  Lead in the authorized tenant scope.
     * @return array<int, array{disk: string, path: string}> Private file references.
     *
     * @since 2.1.0
     */
    private function privateFiles(Lead $lead): array
    {
        $uploads = Upload::query()
            ->where('tenant_id', $lead->tenant_id)
            ->where('lead_id', $lead->id)
            ->get(['original_disk', 'original_path'])
            ->map(fn (Upload $upload): array => [
                'disk' => (string) $upload->original_disk,
                'path' => (string) $upload->original_path,
            ]);

        $sourceImages = LeadSourceImage::query()
            ->where('tenant_id', $lead->tenant_id)
            ->where('lead_id', $lead->id)
            ->get(['disk', 'path'])
            ->map(fn (LeadSourceImage $sourceImage): array => [
                'disk' => (string) $sourceImage->disk,
                'path' => (string) $sourceImage->path,
            ]);

        $generatedAssets = GeneratedAsset::query()
            ->where('tenant_id', $lead->tenant_id)
            ->where('lead_id', $lead->id)
            ->get(['disk', 'path'])
            ->map(fn (GeneratedAsset $asset): array => [
                'disk' => (string) $asset->disk,
                'path' => (string) $asset->path,
            ]);

        return $uploads
            ->concat($sourceImages)
            ->concat($generatedAssets)
            ->filter(fn (array $file): bool => $this->isSafePrivatePath($file['path']))
            ->unique(fn (array $file): string => $file['disk']."\0".$file['path'])
            ->values()
            ->all();
    }

    /**
     * Build known lead-owned local directories for residual private files.
     *
     * @param  Lead  $lead  Lead in the authorized tenant scope.
     * @return array<int, array{disk: string, path: string}> Private directories.
     *
     * @since 2.1.0
     */
    private function privateDirectories(Lead $lead): array
    {
        if (! Str::isUuid((string) $lead->uuid)) {
            return [];
        }

        $directories = [[
            'disk' => 'local',
            'path' => 'uploads/'.$lead->uuid,
        ]];

        if (Str::isUuid((string) $lead->tenant?->uuid)) {
            $directories[] = [
                'disk' => 'local',
                'path' => 'tenants/'.$lead->tenant->uuid.'/leads/'.$lead->uuid,
            ];
        }

        return $directories;
    }

    /**
     * Remove private files without exposing their paths through an exception.
     *
     * @param  array<int, array{disk: string, path: string}>  $files  Private file references.
     * @param  array<int, array{disk: string, path: string}>  $directories  Private directories.
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    private function deletePrivateFiles(array $files, array $directories): void
    {
        foreach ($files as $file) {
            if (! $this->diskExists($file['disk'])) {
                continue;
            }

            try {
                Storage::disk($file['disk'])->delete($file['path']);
            } catch (Throwable) {
                // Keep private paths out of logs and preserve the committed deletion.
            }
        }

        foreach ($directories as $directory) {
            if (! $this->diskExists($directory['disk']) || ! $this->isSafePrivatePath($directory['path'])) {
                continue;
            }

            try {
                Storage::disk($directory['disk'])->deleteDirectory($directory['path']);
            } catch (Throwable) {
                // Keep private paths out of logs and preserve the committed deletion.
            }
        }
    }

    /**
     * Determine whether a configured filesystem disk is available.
     *
     * @param  string  $disk  Filesystem disk name.
     * @return bool Whether the disk is configured.
     *
     * @since 2.1.0
     */
    private function diskExists(string $disk): bool
    {
        return $disk !== '' && is_array(config('filesystems.disks.'.$disk));
    }

    /**
     * Reject absolute or traversing paths before filesystem deletion.
     *
     * @param  string  $path  Private relative path.
     * @return bool Whether the path stays inside its configured disk root.
     *
     * @since 2.1.0
     */
    private function isSafePrivatePath(string $path): bool
    {
        return $path !== ''
            && ! str_starts_with($path, '/')
            && ! str_starts_with($path, '\\')
            && ! in_array('..', preg_split('~[\\\\/]~', $path) ?: [], true);
    }
}
