<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AiModelCategoryResource;
use App\Models\Lead;
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
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  PublicApplicationAvailabilityService  $availability  The availability value.
     * @return JsonResponse JSON response payload.
     *
     * @since 1.0.0
     * @since 2.0.0 Behavior updated for the version 2.0.0 release.
     */
    public function __invoke(Request $request, PublicApplicationAvailabilityService $availability): JsonResponse
    {
        $scope = 'public.bootstrap';

        $tenant = $this->tenantFromRequest($request, $scope);

        $theme = $tenant->theme;
        $categories = $availability->categoriesForTenant($tenant);
        $sessionLeadId = $request->session()->get('lead_id');
        $hasVerifiedLead = $sessionLeadId && Lead::query()
            ->whereKey((int) $sessionLeadId)
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('phone_verified_at')
            ->exists();

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
            'session' => [
                'verified' => (bool) $hasVerifiedLead,
            ],
            'categories' => AiModelCategoryResource::collection($categories)->resolve($request),
        ];

        return response()->json($response);
    }

    /**
     * Get the tenant resolved by tenant middleware.
     *
     * @param  Request  $request  Incoming HTTP request.
     * @param  string  $scope  The scope value.
     * @return Tenant Resolved operation result.
     *
     * @since 1.0.0
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
