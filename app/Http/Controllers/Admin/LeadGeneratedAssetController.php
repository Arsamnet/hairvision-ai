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

        if ($tenant instanceof Tenant && $lead->tenant_id !== $tenant->id) {
            DebugStepLogger::step($request, $scope, 'Lead tenant mismatch', [
                'admin_tenant_id' => $tenant->id,
                'lead_tenant_id' => $lead->tenant_id,
                'lead_id' => $lead->id,
            ]);

            abort(Response::HTTP_NOT_FOUND);
        }

        DebugStepLogger::step($request, $scope, 'Lead tenant scope verified', [
            'admin_tenant_id' => $this->tenantId($tenant),
            'lead_tenant_id' => $lead->tenant_id,
        ]);

        if ($generatedAsset->lead_id !== $lead->id || $generatedAsset->tenant_id !== $lead->tenant_id) {
            DebugStepLogger::step($request, $scope, 'Asset scope mismatch', [
                'lead_id' => $lead->id,
                'lead_tenant_id' => $lead->tenant_id,
                'asset_id' => $generatedAsset->id,
                'asset_lead_id' => $generatedAsset->lead_id,
                'asset_tenant_id' => $generatedAsset->tenant_id,
            ]);

            abort(Response::HTTP_NOT_FOUND);
        }

        DebugStepLogger::step($request, $scope, 'Asset scope verified', [
            'asset_id' => $generatedAsset->id,
            'asset_uuid' => $generatedAsset->uuid,
            'lead_id' => $lead->id,
            'tenant_id' => $lead->tenant_id,
            'disk' => $generatedAsset->disk,
            'mime' => $generatedAsset->mime,
        ]);

        $disk = Storage::disk($generatedAsset->disk);

        if (! $disk->exists($generatedAsset->path)) {
            DebugStepLogger::step($request, $scope, 'Asset file missing on disk', [
                'asset_id' => $generatedAsset->id,
                'asset_uuid' => $generatedAsset->uuid,
                'disk' => $generatedAsset->disk,
            ]);

            abort(Response::HTTP_NOT_FOUND);
        }

        DebugStepLogger::step($request, $scope, 'Asset file exists on disk', [
            'asset_id' => $generatedAsset->id,
            'asset_uuid' => $generatedAsset->uuid,
            'disk' => $generatedAsset->disk,
        ]);

        $stream = $disk->get($generatedAsset->path);
        $mime = (string) $generatedAsset->mime;

        DebugStepLogger::step($request, $scope, 'Asset file loaded', [
            'asset_id' => $generatedAsset->id,
            'asset_uuid' => $generatedAsset->uuid,
            'bytes' => strlen($stream),
            'mime' => $mime,
        ]);

        if (! str_starts_with($mime, 'image/')) {
            DebugStepLogger::step($request, $scope, 'Asset MIME fallback applied', [
                'asset_id' => $generatedAsset->id,
                'asset_uuid' => $generatedAsset->uuid,
                'original_mime' => $mime,
                'fallback_mime' => 'image/jpeg',
            ]);

            $mime = 'image/jpeg';
        }

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
        return $tenant instanceof Tenant ? $tenant->id : null;
    }
}