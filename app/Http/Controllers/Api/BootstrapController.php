<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class BootstrapController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $tenant = app('currentTenant');
        $theme = $tenant->theme;

        return response()->json([
            'tenant' => [
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'theme' => [
                    'primary_color' => $theme->primary_color ?? '#0f766e',
                    'logo_url' => null,
                    'template' => $theme->template?->code ?? 'classic',
                ],
            ],
            'features' => [
                'ai_provider' => config('hairvision.default_ai_provider'),
                'upload_enabled' => true,
            ],
        ]);
    }
}
