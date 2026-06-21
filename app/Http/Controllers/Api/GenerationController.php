<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GenerationGroup;
use App\Models\Lead;
use App\Models\Upload;
use App\Services\GenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GenerationController extends Controller
{
    public function store(Request $request, GenerationService $generationService): JsonResponse
    {
        $request->validate([
            'upload_id' => ['required', 'uuid'],
        ], [
            'upload_id.required' => 'شناسه تصویر ارسال نشده است.',
            'upload_id.uuid' => 'شناسه تصویر معتبر نیست.',
        ]);

        $tenant = app('currentTenant');
        $lead = $this->verifiedLead($request);

        $upload = Upload::query()
            ->where('uuid', '=', $request->post('upload_id'))
            ->where('tenant_id', '=', $tenant->id)
            ->where('lead_id', '=', $lead->id)
            ->firstOrFail();

        $group = $generationService->createForUpload($upload);
        $group->refresh()->load('jobs');

        return response()->json([
            'success' => true,
            'generation_group' => ['id' => $group->uuid, 'status' => $group->status],
            'jobs' => $group->jobs->map(fn ($job) => ['id' => $job->uuid, 'period' => $job->period, 'status' => $job->status]),
        ]);
    }

    public function status(Request $request, string $groupUuid): JsonResponse
    {
        $tenant = app('currentTenant');
        $lead = $this->verifiedLead($request);

        $group = GenerationGroup::query()
            ->where('uuid', '=', $groupUuid)
            ->where('tenant_id', '=', $tenant->id)
            ->where('lead_id', '=', $lead->id)
            ->with('jobs')
            ->firstOrFail();

        return response()->json([
            'status' => $group->status,
            'jobs' => $group->jobs->map(fn ($job) => ['period' => $job->period, 'status' => $job->status]),
        ]);
    }

    public function results(Request $request, string $groupUuid): JsonResponse
    {
        $tenant = app('currentTenant');
        $lead = $this->verifiedLead($request);

        $group = GenerationGroup::query()
            ->where('uuid', '=', $groupUuid)
            ->where('tenant_id', '=', $tenant->id)
            ->where('lead_id', '=', $lead->id)
            ->with('assets')
            ->firstOrFail();

        return response()->json([
            'success' => true,
            'results' => $group->assets->map(fn ($asset) => [
                'period' => $asset->period,
                'url' => '/api/v1/assets/'.$asset->uuid,
            ]),
            'disclaimer' => 'این تصاویر شبیه‌سازی هستند و نتیجه واقعی درمان را تضمین نمی‌کنند.',
        ]);
    }

    private function verifiedLead(Request $request): Lead
    {
        $tenant = app('currentTenant');
        $leadId = $request->session()->get('lead_id');

        if (! $leadId) {
            abort(403, 'برای مشاهده نتایج، ابتدا شماره موبایل را تایید کنید.');
        }

        return Lead::query()
            ->where('tenant_id', '=', $tenant->id)
            ->where('id', '=', $leadId)
            ->whereNotNull('phone_verified_at')
            ->firstOrFail();
    }
}
