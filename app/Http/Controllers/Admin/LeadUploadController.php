<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Upload;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Streams private lead uploads to authorized dashboard users.
 *
 * @since 1.0.0
 */
class LeadUploadController extends Controller
{
    public function show(Request $request, Lead $lead, Upload $upload): Response
    {
        Gate::authorize('view', $lead);

        $tenant = $request->attributes->get('adminTenant');

        Log::debug('[admin.lead_upload.show] Upload image requested', [
            'lead_id' => $lead->id,
            'lead_tenant_id' => $lead->tenant_id,
            'lead_tenant_id_type' => gettype($lead->tenant_id),
            'upload_id' => $upload->id,
            'upload_lead_id' => $upload->lead_id,
            'upload_lead_id_type' => gettype($upload->lead_id),
            'upload_tenant_id' => $upload->tenant_id,
            'upload_tenant_id_type' => gettype($upload->tenant_id),
            'admin_tenant_id' => $tenant?->id,
            'admin_tenant_id_type' => $tenant ? gettype($tenant->id) : null,
            'disk' => $upload->original_disk,
            'path' => $upload->original_path,
        ]);

        abort_unless(
            ! $tenant || (int) $lead->tenant_id === (int) $tenant->id,
            Response::HTTP_NOT_FOUND
        );

        abort_unless(
            (int) $upload->lead_id === (int) $lead->id
            && (int) $upload->tenant_id === (int) $lead->tenant_id,
            Response::HTTP_NOT_FOUND
        );

        $disk = $upload->original_disk ?: 'local';
        $path = $upload->original_path;

        abort_unless(
            is_string($path) && $path !== '',
            Response::HTTP_NOT_FOUND
        );

        $storage = Storage::disk($disk);
        $exists = $storage->exists($path);

        Log::debug('[admin.lead_upload.show] Upload storage lookup', [
            'upload_id' => $upload->id,
            'disk' => $disk,
            'path' => $path,
            'exists' => $exists,
        ]);

        abort_unless($exists, Response::HTTP_NOT_FOUND);

        $mime = $this->resolveImageMime((string) $upload->mime, $path);

        return response($storage->get($path), Response::HTTP_OK)
            ->header('Content-Type', $mime)
            ->header('Cache-Control', 'private, max-age=300');
    }

    private function resolveImageMime(?string $storedMime, string $path): string
    {
        if (is_string($storedMime) && str_starts_with($storedMime, 'image/')) {
            return $storedMime;
        }

        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'image/jpeg',
        };
    }
}