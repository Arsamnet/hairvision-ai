<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GeneratedAsset;
use App\Models\Tenant;
use App\Services\LeadSessionService;
use App\Support\DebugStepLogger;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Streams private generated assets.
 *
 * @since 1.0.0
 */
class AssetController extends Controller
{
    /**
     * Stream a generated image after tenant and lead checks.
     */
    public function show(Request $request, string $uuid, LeadSessionService $leadSessionService): Response
    {
        $scope = 'public.asset.show';

        DebugStepLogger::step($request, $scope, 'Request received', [
            'asset_uuid' => $uuid,
        ]);

        $tenant = $this->tenantFromRequest($request, $scope);

        DebugStepLogger::step($request, $scope, 'Tenant resolved', [
            'tenant_id' => $tenant->id,
        ]);

        $lead = $leadSessionService->verifiedLead($request, $tenant, __('auth.verify_phone_to_view_asset'));

        DebugStepLogger::step($request, $scope, 'Verified lead resolved', [
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'lead_uuid' => $lead->uuid,
        ]);

        $asset = GeneratedAsset::query()
            ->where('uuid', '=', $uuid)
            ->where('tenant_id', '=', $tenant->id)
            ->where('lead_id', '=', $lead->id)
            ->firstOrFail();

        DebugStepLogger::step($request, $scope, 'Generated asset resolved', [
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'asset_id' => $asset->id,
            'asset_uuid' => $asset->uuid,
            'disk' => $asset->disk,
            'mime' => $asset->mime,
        ]);

        $disk = Storage::disk($asset->disk);

        if (! $disk->exists($asset->path)) {
            DebugStepLogger::step($request, $scope, 'Asset file missing on disk', [
                'tenant_id' => $tenant->id,
                'lead_id' => $lead->id,
                'asset_id' => $asset->id,
                'asset_uuid' => $asset->uuid,
                'disk' => $asset->disk,
            ]);

            abort(Response::HTTP_NOT_FOUND);
        }

        DebugStepLogger::step($request, $scope, 'Asset file exists on disk', [
            'asset_id' => $asset->id,
            'asset_uuid' => $asset->uuid,
            'disk' => $asset->disk,
        ]);

        $stream = $disk->get($asset->path);
        $mime = (string) $asset->mime;

        DebugStepLogger::step($request, $scope, 'Asset file loaded', [
            'asset_id' => $asset->id,
            'asset_uuid' => $asset->uuid,
            'bytes' => strlen($stream),
            'mime' => $mime,
        ]);

        if (! str_starts_with($mime, 'image/')) {
            DebugStepLogger::step($request, $scope, 'Asset MIME fallback applied', [
                'asset_id' => $asset->id,
                'asset_uuid' => $asset->uuid,
                'original_mime' => $mime,
                'fallback_mime' => 'image/jpeg',
            ]);

            $mime = 'image/jpeg';
        }

        DebugStepLogger::step($request, $scope, 'Response prepared', [
            'asset_id' => $asset->id,
            'asset_uuid' => $asset->uuid,
            'mime' => $mime,
            'cache_control' => 'private, max-age=300',
        ]);

        return response($stream, Response::HTTP_OK)
            ->header('Content-Type', $mime)
            ->header('Cache-Control', 'private, max-age=300');
    }

    /**
     * Get the tenant resolved by tenant middleware.
     */
    private function tenantFromRequest(Request $request, string $scope): Tenant
    {
        $tenant = $request->attributes->get('currentTenant');

        if (! $tenant instanceof Tenant) {
            DebugStepLogger::step($request, $scope, 'Tenant missing from request attributes');

            abort(Response::HTTP_NOT_FOUND);
        }

        return $tenant;
    }
}