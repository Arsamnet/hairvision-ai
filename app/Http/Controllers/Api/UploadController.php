<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreUploadRequest;
use App\Services\LeadSessionService;
use App\Services\UploadService;
use Illuminate\Http\JsonResponse;

/**
 * Stores private visitor uploads after OTP-backed lead verification.
 *
 * @since 1.0.0
 */
class UploadController extends Controller
{
    /**
     * Persist an uploaded image outside the public webroot.
     *
     * @param  StoreUploadRequest  $request  Validated image upload request.
     * @param  UploadService  $uploadService  Service responsible for private file storage.
     * @param  LeadSessionService  $leadSessionService  Resolves the verified session lead.
     */
    public function store(
        StoreUploadRequest $request,
        UploadService $uploadService,
        LeadSessionService $leadSessionService
    ): JsonResponse {
        $tenant = app('currentTenant');
        $lead = $leadSessionService->verifiedLead($request, $tenant, 'برای ادامه، ابتدا شماره موبایل را تایید کنید.');

        $upload = $uploadService->storeUpload($lead, $request->file('image'));

        return response()->json([
            'success' => true,
            'upload' => ['id' => $upload->uuid, 'status' => $upload->status],
        ]);
    }
}
