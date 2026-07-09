<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GeneratedAsset;
use App\Models\Tenant;
use App\Services\LeadSessionService;
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

        $tenant = $this->tenantFromRequest($request, $scope);

        $lead = $leadSessionService->verifiedLead($request, $tenant, __('auth.verify_phone_to_view_asset'));

        $asset = GeneratedAsset::query()
            ->where('uuid', '=', $uuid)
            ->where('tenant_id', '=', $tenant->id)
            ->where('lead_id', '=', $lead->id)
            ->firstOrFail();

        $disk = Storage::disk($asset->disk);

        if (! $disk->exists($asset->path)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $stream = $disk->get($asset->path);
        $mime = (string) $asset->mime;

        if (! str_starts_with($mime, 'image/')) {
            $mime = 'image/jpeg';
        }

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
            abort(Response::HTTP_NOT_FOUND);
        }

        return $tenant;
    }
}
