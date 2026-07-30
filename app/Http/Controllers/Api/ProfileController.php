<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreSourceImageRequest;
use App\Http\Resources\AiModelCategoryResource;
use App\Http\Resources\GenerationRequestResource;
use App\Models\GenerationRequest;
use App\Models\Tenant;
use App\Services\LeadSessionService;
use App\Services\Generation\GenerationResumeTokenService;
use App\Services\Profile\LeadProfileService;
use App\Services\Visitor\PublicApplicationAvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public verified-lead profile APIs.
 *
 * @since 2.0.0
 */
class ProfileController extends Controller
{
    /**
     * Display the requested profile resource.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  LeadSessionService  $leadSessionService  The lead session service value.
     * @param  LeadProfileService  $profiles  The profiles value.
     * @return JsonResponse JSON response payload.
     *
     * @since 2.0.0
     */
    public function show(Request $request, LeadSessionService $leadSessionService, LeadProfileService $profiles): JsonResponse
    {
        $tenant = $this->tenantFromRequest($request);
        $lead = $leadSessionService->verifiedLead($request, $tenant, __('auth.verify_phone_to_continue'));
        $profile = $profiles->profileForLead($lead)->load('currentSourceImage');

        return response()->json([
            'data' => [
                'lead' => [
                    'id' => $lead->uuid,
                    'phone' => $lead->phone_normalized,
                ],
                'source_image' => $profile->currentSourceImage ? [
                    'id' => $profile->currentSourceImage->uuid,
                    'version' => $profile->currentSourceImage->version,
                    'status' => $profile->currentSourceImage->status,
                    'url' => '/api/v1/profile/source-images/'.$profile->currentSourceImage->uuid.'/stream',
                ] : null,
            ],
        ]);
    }

    /**
     * Execute the store source image operation.
     *
     * @param  StoreSourceImageRequest  $request  Incoming HTTP request.
     * @param  LeadSessionService  $leadSessionService  The lead session service value.
     * @param  LeadProfileService  $profiles  The profiles value.
     * @return JsonResponse JSON response payload.
     *
     * @since 2.0.0
     */
    public function storeSourceImage(
        StoreSourceImageRequest $request,
        LeadSessionService $leadSessionService,
        LeadProfileService $profiles
    ): JsonResponse {
        $tenant = $this->tenantFromRequest($request);
        $lead = $leadSessionService->verifiedLead($request, $tenant, __('auth.verify_phone_to_continue'));
        $source = $profiles->replaceSourceImage($lead, $request->file('image'));

        return response()->json([
            'data' => [
                'id' => $source->uuid,
                'version' => $source->version,
                'status' => $source->status,
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Execute the categories operation.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  LeadSessionService  $leadSessionService  The lead session service value.
     * @param  PublicApplicationAvailabilityService  $availability  The availability value.
     * @return JsonResponse JSON response payload.
     *
     * @since 2.0.0
     */
    public function categories(
        Request $request,
        LeadSessionService $leadSessionService,
        PublicApplicationAvailabilityService $availability
    ): JsonResponse {
        $tenant = $this->tenantFromRequest($request);
        $leadSessionService->verifiedLead($request, $tenant, __('auth.verify_phone_to_continue'));

        $categories = $availability->categoriesForTenant($tenant);

        return response()->json([
            'data' => AiModelCategoryResource::collection($categories),
        ]);
    }

    /**
     * Execute the generations operation.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  LeadSessionService  $leadSessionService  The lead session service value.
     * @param  GenerationResumeTokenService  $resumeTokens  Session-bound generation credential manager.
     * @return JsonResponse JSON response payload.
     *
     * @since 2.0.0
     */
    public function generations(
        Request $request,
        LeadSessionService $leadSessionService,
        GenerationResumeTokenService $resumeTokens,
    ): JsonResponse
    {
        $tenant = $this->tenantFromRequest($request);
        $lead = $leadSessionService->verifiedLead($request, $tenant, __('auth.verify_phone_to_view_results'));

        $generations = GenerationRequest::query()
            ->where('tenant_id', $tenant->id)
            ->where('lead_id', $lead->id)
            ->with(['sourceImage', 'category', 'output', 'currentAsset', 'attempts'])
            ->latest('id')
            ->get();

        foreach ($generations as $generation) {
            $resumeTokens->remember($request, $generation);
        }

        return response()->json([
            'data' => GenerationRequestResource::collection($generations),
        ]);
    }

    /**
     * Stream a private source image owned by the current verified lead.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  string  $sourceImage  Public source-image UUID.
     * @param  LeadSessionService  $leadSessionService  Verified lead resolver.
     * @return \Illuminate\Http\Response Private image response.
     *
     * @since 2.0.0
     */
    public function streamSourceImage(
        Request $request,
        string $sourceImage,
        LeadSessionService $leadSessionService,
    ): \Illuminate\Http\Response {
        $tenant = $this->tenantFromRequest($request);
        $lead = $leadSessionService->verifiedLead($request, $tenant, __('auth.verify_phone_to_view_asset'));
        $image = $lead->sourceImages()
            ->where('uuid', $sourceImage)
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();
        $disk = Storage::disk($image->disk);

        if (! $disk->exists($image->path)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response($disk->get($image->path), Response::HTTP_OK)
            ->header('Content-Type', str_starts_with((string) $image->mime, 'image/') ? $image->mime : 'image/jpeg')
            ->header('Cache-Control', 'private, max-age=300');
    }

    /**
     * Execute the tenant from request operation.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @return Tenant Resolved operation result.
     *
     * @since 2.0.0
     */
    private function tenantFromRequest(Request $request): Tenant
    {
        $tenant = $request->attributes->get('currentTenant');

        if (! $tenant instanceof Tenant) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return $tenant;
    }
}
