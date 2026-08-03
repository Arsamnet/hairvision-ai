<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreLandingEventRequest;
use App\Models\Tenant;
use App\Services\Analytics\LandingAnalyticsService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accepts bounded first-party events from a host-resolved clinic landing page.
 *
 * @since 2.1.0
 */
class LandingAnalyticsController extends Controller
{
    /**
     * Persist one idempotent landing event.
     *
     * @param  StoreLandingEventRequest  $request  Validated public event request.
     * @param  LandingAnalyticsService  $analytics  First-party event recorder.
     * @return JsonResponse Accepted response that does not disclose token validity.
     *
     * @since 2.1.0
     */
    public function __invoke(StoreLandingEventRequest $request, LandingAnalyticsService $analytics): JsonResponse
    {
        $tenant = $request->attributes->get('currentTenant');

        if (! $tenant instanceof Tenant) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $analytics->record($request, $tenant, $request->validated());

        return response()->json(['accepted' => true], Response::HTTP_ACCEPTED);
    }
}
