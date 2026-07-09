<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreUploadRequest;
use App\Models\Tenant;
use App\Services\LeadSessionService;
use App\Services\UploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Stores private visitor uploads.
 *
 * @since 1.0.0
 */
class UploadController extends Controller
{
    /**
     * Store an uploaded image for a verified visitor lead.
     */
    public function store(
        StoreUploadRequest $request,
        UploadService $uploadService,
        LeadSessionService $leadSessionService
    ): JsonResponse {
        $scope = 'public.upload.store';

        $tenant = $this->tenantFromRequest($request, $scope);

        $lead = $leadSessionService->verifiedLead($request, $tenant, __('auth.verify_phone_to_continue'));

        $image = $request->file('image');

        try {
            $upload = $uploadService->storeUpload($lead, $image, $request);
        } catch (Throwable $exception) {
            throw $exception;
        }

        return response()->json([
            'success' => true,
            'upload' => [
                'id' => $upload->uuid,
                'status' => $upload->status,
            ],
        ]);
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
