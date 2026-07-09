<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GeneratedAsset;
use App\Models\Lead;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Streams private generated assets to dashboard users.
 *
 * @since 1.0.0
 */
class LeadGeneratedAssetController extends Controller
{
    /**
     * Stream an authorized generated image.
     */
    public function show(Request $request, Lead $lead, GeneratedAsset $generatedAsset): Response
    {
        $scope = 'admin.leads.assets.show';
        $tenant = $request->attributes->get('adminTenant');

        Gate::authorize('view', $lead);

        if ($tenant instanceof Tenant && (int) $lead->tenant_id !== (int) $tenant->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        if (
            (int) $generatedAsset->lead_id !== (int) $lead->id
            || (int) $generatedAsset->tenant_id !== (int) $lead->tenant_id
        ) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $diskName = $generatedAsset->disk ?: 'local';
        $path = $generatedAsset->path;

        if (! is_string($path) || $path === '') {
            abort(Response::HTTP_NOT_FOUND);
        }

        $disk = Storage::disk($diskName);

        if (! $disk->exists($path)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $stream = $disk->get($path);

        if (! is_string($stream) || $stream === '') {
            abort(Response::HTTP_NOT_FOUND);
        }

        $mime = $this->resolveImageMime((string) $generatedAsset->mime, $path);

        return response($stream, Response::HTTP_OK)
            ->header('Content-Type', $mime)
            ->header('Cache-Control', 'private, max-age=300');
    }

    /**
     * Return a safe tenant ID for logs.
     */
    private function tenantId(mixed $tenant): ?int
    {
        return $tenant instanceof Tenant ? (int) $tenant->id : null;
    }

    /**
     * Resolve a safe image MIME type without relying on Storage::mimeType().
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
