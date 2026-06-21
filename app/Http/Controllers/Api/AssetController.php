<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GeneratedAsset;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class AssetController extends Controller
{
    public function show(Request $request, string $uuid)
    {
        $tenant = app('currentTenant');
        $leadId = $request->session()->get('lead_id');

        if (! $leadId) {
            abort(403, 'برای مشاهده تصویر، ابتدا شماره موبایل را تایید کنید.');
        }

        Lead::query()
            ->where('tenant_id', '=', $tenant->id)
            ->where('id', '=', $leadId)
            ->whereNotNull('phone_verified_at')
            ->firstOrFail();

        $asset = GeneratedAsset::query()
            ->where('uuid', '=', $uuid)
            ->where('tenant_id', '=', $tenant->id)
            ->where('lead_id', '=', $leadId)
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
