<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GeneratedAsset;
use App\Models\Lead;
use App\Models\Tenant;
use App\Support\DebugStepLogger;
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

        DebugStepLogger::step($request, $scope, 'Request received', [
            'user_id' => $request->user()?->id,
            'admin_tenant_id' => $this->tenantId($tenant),
            'lead_id' => $lead->id,
            'lead_uuid' => $lead->uuid,
            'asset_id' => $generatedAsset->id,
            'asset_uuid' => $generatedAsset->uuid,
        ]);

        Gate::authorize('view', $lead);

        DebugStepLogger::step($request, $scope, 'Lead authorization granted', [
            'user_id' => $request->user()?->id,
            'lead_id' => $lead->id,
        ]);

        if ($tenant instanceof Tenant && (int) $lead->tenant_id !== (int) $tenant->id) {
            DebugStepLogger::step($request, $scope, 'Lead tenant mismatch', [
                'admin_tenant_id' => $tenant->id,
                'admin_tenant_id_type' => gettype($tenant->id),
                'lead_tenant_id' => $lead->tenant_id,
                'lead_tenant_id_type' => gettype($lead->tenant_id),
                'lead_id' => $lead->id,
            ]);

            abort(Response::HTTP_NOT_FOUND);
        }

        DebugStepLogger::step($request, $scope, 'Lead tenant scope verified', [
            'admin_tenant_id' => $this->tenantId($tenant),
            'lead_tenant_id' => $lead->tenant_id,
        ]);

        if (
            (int) $generatedAsset->lead_id !== (int) $lead->id
            || (int) $generatedAsset->tenant_id !== (int) $lead->tenant_id
        ) {
            DebugStepLogger::step($request, $scope, 'Asset scope mismatch', [
                'lead_id' => $lead->id,
                'lead_id_type' => gettype($lead->id),
                'lead_tenant_id' => $lead->tenant_id,
                'lead_tenant_id_type' => gettype($lead->tenant_id),
                'asset_id' => $generatedAsset->id,
                'asset_lead_id' => $generatedAsset->lead_id,
                'asset_lead_id_type' => gettype($generatedAsset->lead_id),
                'asset_tenant_id' => $generatedAsset->tenant_id,
                'asset_tenant_id_type' => gettype($generatedAsset->tenant_id),
            ]);

            abort(Response::HTTP_NOT_FOUND);
        }

        $diskName = $generatedAsset->disk ?: 'local';
        $path = $generatedAsset->path;

        DebugStepLogger::step($request, $scope, 'Asset scope verified', [
            'asset_id' => $generatedAsset->id,
            'asset_uuid' => $generatedAsset->uuid,
            'lead_id' => $lead->id,
            'tenant_id' => $lead->tenant_id,
            'disk' => $diskName,
            'path' => $path,
            'mime' => $generatedAsset->mime,
        ]);

        if (! is_string($path) || $path === '') {
            DebugStepLogger::step($request, $scope, 'Asset path is empty', [
                'asset_id' => $generatedAsset->id,
                'asset_uuid' => $generatedAsset->uuid,
                'disk' => $diskName,
                'path' => $path,
            ]);

            abort(Response::HTTP_NOT_FOUND);
        }

        $disk = Storage::disk($diskName);

        if (! $disk->exists($path)) {
            DebugStepLogger::step($request, $scope, 'Asset file missing on disk', [
                'asset_id' => $generatedAsset->id,
                'asset_uuid' => $generatedAsset->uuid,
                'disk' => $diskName,
                'path' => $path,
            ]);

            abort(Response::HTTP_NOT_FOUND);
        }

        DebugStepLogger::step($request, $scope, 'Asset file exists on disk', [
            'asset_id' => $generatedAsset->id,
            'asset_uuid' => $generatedAsset->uuid,
            'disk' => $diskName,
            'path' => $path,
        ]);

        $stream = $disk->get($path);

        if (! is_string($stream) || $stream === '') {
            DebugStepLogger::step($request, $scope, 'Asset file content is empty', [
                'asset_id' => $generatedAsset->id,
                'asset_uuid' => $generatedAsset->uuid,
                'disk' => $diskName,
                'path' => $path,
            ]);

            abort(Response::HTTP_NOT_FOUND);
        }

        $mime = $this->resolveImageMime((string) $generatedAsset->mime, $path);

        DebugStepLogger::step($request, $scope, 'Asset file loaded', [
            'asset_id' => $generatedAsset->id,
            'asset_uuid' => $generatedAsset->uuid,
            'bytes' => strlen($stream),
            'mime' => $mime,
        ]);

        DebugStepLogger::step($request, $scope, 'Response prepared', [
            'asset_id' => $generatedAsset->id,
            'asset_uuid' => $generatedAsset->uuid,
            'mime' => $mime,
            'cache_control' => 'private, max-age=300',
        ]);

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