<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Upload;
use Illuminate\Http\UploadedFile;

class UploadService
{
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
