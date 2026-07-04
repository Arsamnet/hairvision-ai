<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Upload;
use App\Support\DebugStepLogger;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Throwable;

/**
 * Stores visitor uploads in private storage.
 *
 * @since 1.0.0
 */
class UploadService
{
    /**
     * Store an uploaded image and create its metadata.
     */
    public function storeUpload(Lead $lead, UploadedFile $file, ?Request $request = null): Upload
    {
        $scope = 'public.upload.service.store';

        $this->debugStep($request, $scope, 'Request received', [
            'tenant_id' => $lead->tenant_id,
            'lead_id' => $lead->id,
            'lead_uuid' => $lead->uuid,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getClientMimeType(),
            'client_extension' => $file->getClientOriginalExtension(),
            'is_valid' => $file->isValid(),
        ]);

        $disk = 'local';
        $directory = 'uploads/'.$lead->uuid;

        $this->debugStep($request, $scope, 'Storage target prepared', [
            'tenant_id' => $lead->tenant_id,
            'lead_id' => $lead->id,
            'disk' => $disk,
            'directory_prepared' => true,
        ]);

        try {
            $path = $file->store($directory, ['disk' => $disk]);
        } catch (Throwable $exception) {
            $this->debugStep($request, $scope, 'File storage failed', [
                'tenant_id' => $lead->tenant_id,
                'lead_id' => $lead->id,
                'disk' => $disk,
                'exception' => $exception::class,
            ]);

            throw $exception;
        }

        if (! is_string($path) || $path === '') {
            $this->debugStep($request, $scope, 'File storage returned invalid path', [
                'tenant_id' => $lead->tenant_id,
                'lead_id' => $lead->id,
                'disk' => $disk,
                'path_is_string' => is_string($path),
            ]);

            throw new RuntimeException('Upload file could not be stored.');
        }

        $this->debugStep($request, $scope, 'File stored', [
            'tenant_id' => $lead->tenant_id,
            'lead_id' => $lead->id,
            'disk' => $disk,
            'has_path' => true,
        ]);

        try {
            $upload = Upload::create([
                'tenant_id' => $lead->tenant_id,
                'lead_id' => $lead->id,
                'original_disk' => $disk,
                'original_path' => $path,
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'width' => null,
                'height' => null,
                'status' => 'validated',
            ]);
        } catch (Throwable $exception) {
            $this->debugStep($request, $scope, 'Upload record creation failed', [
                'tenant_id' => $lead->tenant_id,
                'lead_id' => $lead->id,
                'disk' => $disk,
                'exception' => $exception::class,
            ]);

            throw $exception;
        }

        $this->debugStep($request, $scope, 'Upload record created', [
            'tenant_id' => $upload->tenant_id,
            'lead_id' => $upload->lead_id,
            'upload_id' => $upload->id,
            'upload_uuid' => $upload->uuid,
            'disk' => $upload->original_disk,
            'mime' => $upload->mime,
            'size' => $upload->size,
            'status' => $upload->status,
        ]);

        return $upload;
    }

    /**
     * Write a debug step when a request is available.
     *
     * @param  array<string, mixed>  $context
     */
    private function debugStep(?Request $request, string $scope, string $message, array $context = []): void
    {
        if (! $request) {
            return;
        }

        DebugStepLogger::step($request, $scope, $message, $context);
    }
}