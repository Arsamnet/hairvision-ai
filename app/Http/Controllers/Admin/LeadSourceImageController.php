<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\LeadSourceImage;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Streams private version 2 lead source images to authorized dashboard users.
 *
 * @since 2.0.0
 */
class LeadSourceImageController extends Controller
{
    /**
     * Stream a tenant- and lead-scoped source image.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  Lead  $lead  Lead in the current tenant scope.
     * @param  LeadSourceImage  $leadSourceImage  The lead source image value.
     * @return Response HTTP response.
     *
     * @since 2.0.0
     */
    public function show(Request $request, Lead $lead, LeadSourceImage $leadSourceImage): Response
    {
        Gate::authorize('view', $lead);

        $tenant = $request->attributes->get('adminTenant');

        if ($tenant instanceof Tenant && (int) $lead->tenant_id !== (int) $tenant->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        if (
            (int) $leadSourceImage->lead_id !== (int) $lead->id
            || (int) $leadSourceImage->tenant_id !== (int) $lead->tenant_id
            || $leadSourceImage->status !== 'validated'
        ) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $path = $leadSourceImage->path;

        if (! is_string($path) || $path === '') {
            abort(Response::HTTP_NOT_FOUND);
        }

        $disk = Storage::disk($leadSourceImage->disk ?: 'local');

        if (! $disk->exists($path)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $content = $disk->get($path);

        if (! is_string($content) || $content === '') {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response($content, Response::HTTP_OK)
            ->header('Content-Type', $this->resolveImageMime((string) $leadSourceImage->mime, $path))
            ->header('Cache-Control', 'private, max-age=300');
    }

    /**
     * Resolve a safe image MIME type from stored metadata or the file extension.
     *
     * @param  string|null  $storedMime  The stored mime value.
     * @param  string  $path  Storage or resource path.
     * @return string Resolved string value.
     *
     * @since 2.0.0
     */
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
