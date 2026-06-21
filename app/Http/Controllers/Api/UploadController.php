<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Services\UploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UploadController extends Controller
{
    public function store(Request $request, UploadService $uploadService): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:5120'],
            'consent' => ['accepted'],
        ], [
            'image.required' => 'لطفا یک تصویر انتخاب کنید.',
            'image.image' => 'فایل انتخاب‌شده باید تصویر معتبر باشد.',
            'image.max' => 'حجم تصویر نباید بیشتر از ۵ مگابایت باشد.',
            'consent.accepted' => 'برای ادامه، تایید پردازش تصویر لازم است.',
        ]);

        $leadId = $request->session()->get('lead_id');
        $tenant = app('currentTenant');

        if (! $leadId) {
            abort(403, 'برای ادامه، ابتدا شماره موبایل را تایید کنید.');
        }

        $lead = Lead::query()
            ->where('tenant_id', '=', $tenant->id)
            ->where('id', '=', $leadId)
            ->whereNotNull('phone_verified_at')
            ->firstOrFail();

        $upload = $uploadService->storeUpload($lead, $request->file('image'));

        return response()->json([
            'success' => true,
            'upload' => ['id' => $upload->uuid, 'status' => $upload->status],
        ]);
    }
}
