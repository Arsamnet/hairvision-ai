<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreUploadRequest;
use App\Models\Tenant;
use App\Services\LeadSessionService;
use App\Services\UploadService;
use App\Support\DebugStepLogger;
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

        DebugStepLogger::step($request, $scope, 'Request received', [
            'has_image' => $request->hasFile('image'),
        ]);

        $tenant = $this->tenantFromRequest($request, $scope);

        DebugStepLogger::step($request, $scope, 'Tenant resolved', [
            'tenant_id' => $tenant->id,
        ]);

        $lead = $leadSessionService->verifiedLead($request, $tenant, __('auth.verify_phone_to_continue'));

        DebugStepLogger::step($request, $scope, 'Verified lead resolved', [
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'lead_uuid' => $lead->uuid,
        ]);

        $image = $request->file('image');

        DebugStepLogger::step($request, $scope, 'Upload file resolved', [
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'file_size' => $image?->getSize(),
            'mime_type' => $image?->getMimeType(),
            'client_extension' => $image?->getClientOriginalExtension(),
        ]);

        try {
            $upload = $uploadService->storeUpload($lead, $image, $request);
        } catch (Throwable $exception) {
            DebugStepLogger::step($request, $scope, 'Upload storage failed', [
                'tenant_id' => $tenant->id,
                'lead_id' => $lead->id,
                'exception' => $exception::class,
            ]);

            throw $exception;
        }

        DebugStepLogger::step($request, $scope, 'Upload stored', [
            'tenant_id' => $tenant->id,
            'lead_id' => $lead->id,
            'upload_id' => $upload->id,
            'upload_uuid' => $upload->uuid,
            'status' => $upload->status,
        ]);

        DebugStepLogger::step($request, $scope, 'Response prepared', [
            'upload_uuid' => $upload->uuid,
            'status' => $upload->status,
        ]);

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
            DebugStepLogger::step($request, $scope, 'Tenant missing from request attributes');

            abort(Response::HTTP_NOT_FOUND);
        }

        return $tenant;
    }
}