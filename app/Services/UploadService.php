<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Upload;
use Illuminate\Http\UploadedFile;

/**
 * Stores visitor uploads in private Laravel storage.
 *
 * @since 1.0.0
 */
class UploadService
{
    /**
     * Store an uploaded image and create tenant-owned upload metadata.
     *
     * Side effects: writes a file to the configured private disk and inserts an
     * upload row scoped by tenant and lead.
     */
    public function storeUpload(Lead $lead, UploadedFile $file): Upload
    {
        $disk = 'local';
        $path = $file->store('uploads/'.$lead->uuid, ['disk' => $disk]);

        return Upload::create([
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
    }
}
