<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Upload;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
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
        abort_unless(! $tenant || $lead->tenant_id === $tenant->id, 404);
        abort_unless($upload->lead_id === $lead->id && $upload->tenant_id === $lead->tenant_id, 404);

        $disk = $upload->original_disk ?: 'local';
        abort_unless(Storage::disk($disk)->exists($upload->original_path), 404);

        $mime = str_starts_with((string) $upload->mime, 'image/') ? $upload->mime : 'image/jpeg';

        return response(Storage::disk($disk)->get($upload->original_path), Response::HTTP_OK)
            ->header('Content-Type', $mime)
            ->header('Cache-Control', 'private, max-age=300');
    }
}
