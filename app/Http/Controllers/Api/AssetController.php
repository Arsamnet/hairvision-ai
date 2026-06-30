<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GeneratedAsset;
use App\Services\LeadSessionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * Streams generated image assets through tenant- and lead-scoped checks.
 *
 * @since 1.0.0
 */
class AssetController extends Controller
{
    /**
     * Stream a private generated asset without exposing storage paths.
     *
     * @param  string  $uuid  Public generated asset UUID.
     * @param  LeadSessionService  $leadSessionService  Resolves the verified session lead.
     */
    public function show(Request $request, string $uuid, LeadSessionService $leadSessionService): Response
    {
        $tenant = app('currentTenant');
        $lead = $leadSessionService->verifiedLead($request, $tenant, __('auth.verify_phone_to_view_asset'));

        $asset = GeneratedAsset::query()
            ->where('uuid', '=', $uuid)
            ->where('tenant_id', '=', $tenant->id)
            ->where('lead_id', '=', $lead->id)
            ->firstOrFail();

        $stream = Storage::disk($asset->disk)->get($asset->path);
        $mime = $asset->mime;

        if (! str_starts_with($mime, 'image/')) {
            $mime = 'image/jpeg';
        }

        return response($stream, Response::HTTP_OK)
            ->header('Content-Type', $mime);
    }
}
