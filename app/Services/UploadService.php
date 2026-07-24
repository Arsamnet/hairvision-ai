<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Upload;
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
     *
     * @param  Lead  $lead  Lead in the current tenant scope.
     * @param  UploadedFile  $file  The file value.
     * @param  Request|null  $request  Incoming HTTP request.
     * @return Upload Resolved operation result.
     *
     * @since 1.0.0
     */
    public function storeUpload(Lead $lead, UploadedFile $file, ?Request $request = null): Upload
    {
        $scope = 'public.upload.service.store';

        $disk = 'local';
        $directory = 'uploads/'.$lead->uuid;

        try {
            $path = $file->store($directory, ['disk' => $disk]);
        } catch (Throwable $exception) {
            throw $exception;
        }

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('Upload file could not be stored.');
        }

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
            throw $exception;
        }

        return $upload;
    }

    /**
     * Write a debug step when a request is available.
     *
     * @param  array<string, mixed>  $context
     */
}
