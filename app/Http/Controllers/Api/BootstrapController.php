<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Support\DebugStepLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns public tenant bootstrap data.
 *
 * @since 1.0.0
 */
class BootstrapController extends Controller
{
    /**
     * Build public tenant identity, theme, and feature metadata.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $scope = 'public.bootstrap';

        DebugStepLogger::step($request, $scope, 'Request received');

        $tenant = $this->tenantFromRequest($request, $scope);

        DebugStepLogger::step($request, $scope, 'Tenant resolved', [
            'tenant_id' => $tenant->id,
            'tenant_slug' => $tenant->slug,
        ]);

        $theme = $tenant->theme;

        DebugStepLogger::step($request, $scope, 'Tenant theme resolved', [
            'tenant_id' => $tenant->id,
            'has_theme' => $theme !== null,
            'template' => $theme?->template?->code ?? 'classic',
            'has_primary_color' => filled($theme?->primary_color),
        ]);

        $response = [
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
        ];

        DebugStepLogger::step($request, $scope, 'Response prepared', [
            'tenant_id' => $tenant->id,
            'tenant_slug' => $tenant->slug,
            'template' => $response['tenant']['theme']['template'],
            'upload_enabled' => $response['features']['upload_enabled'],
            'ai_provider' => $response['features']['ai_provider'],
        ]);

        return response()->json($response);
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