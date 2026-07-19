<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AiModelCategoryResource;
use App\Models\Tenant;
use App\Services\Visitor\PublicApplicationAvailabilityService;
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
    public function __invoke(Request $request, PublicApplicationAvailabilityService $availability): JsonResponse
    {
        $scope = 'public.bootstrap';

        $tenant = $this->tenantFromRequest($request, $scope);

        $theme = $tenant->theme;
        $categories = $availability->categoriesForTenant($tenant);

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
                'upload_enabled' => true,
            ],
            'categories' => AiModelCategoryResource::collection($categories)->resolve($request),
        ];

        return response()->json($response);
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
