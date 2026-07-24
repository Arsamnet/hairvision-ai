<?php

namespace App\Services\AI;

use App\Models\AiModelAccess;
use App\Models\AiModelUsageLog;
use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Builds lead-aware, tenant-safe AI model usage reports.
 *
 * @since 2.0.0
 */
class AiModelUsageReportService
{
    /**
     * Return usage for one model, optionally restricted to one clinic.
     *
     * @param  AiModelAccess  $access  The access value.
     * @param  Tenant|null  $clinicScope  The clinic scope value.
     * @param  string  $pageName  The page name value.
     * @return LengthAwarePaginator Paginated result set.
     *
     * @since 2.0.0
     */
    public function forModel(
        AiModelAccess $access,
        ?Tenant $clinicScope = null,
        string $pageName = 'logs_page',
    ): LengthAwarePaginator {
        return $this->baseQuery()
            ->where('ai_model_access_id', $access->id)
            ->when($clinicScope, fn (Builder $query) => $query->where('clinic_id', $clinicScope->id))
            ->paginate(10, ['*'], $pageName)
            ->withQueryString();
    }

    /**
     * Return usage belonging to one clinic across all of its assigned models.
     *
     * @param  Tenant  $clinic  Tenant in the current operation scope.
     * @param  string  $pageName  The page name value.
     * @return LengthAwarePaginator Paginated result set.
     *
     * @since 2.0.0
     */
    public function forClinic(Tenant $clinic, string $pageName = 'usage_page'): LengthAwarePaginator
    {
        return $this->baseQuery()
            ->where('clinic_id', $clinic->id)
            ->paginate(10, ['*'], $pageName)
            ->withQueryString();
    }

    /**
     * Build the shared eager-loaded usage-log query.
     *
     * @return Builder<AiModelUsageLog>
     *
     * @since 2.0.0
     */
    private function baseQuery(): Builder
    {
        return AiModelUsageLog::query()
            ->with(['clinic', 'lead', 'user', 'aiModelAccess'])
            ->latest('id');
    }
}
