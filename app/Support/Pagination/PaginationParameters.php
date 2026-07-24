<?php

namespace App\Support\Pagination;

use App\Services\Settings\SystemSettingService;
use Illuminate\Http\Request;

/**
 * Normalizes reusable API/admin pagination and sorting input.
 *
 * @since 1.0.0
 */
class PaginationParameters
{
    /**
     * Create a new pagination parameters instance.
     *
     * @param  int  $page  The page value.
     * @param  int  $perPage  The per page value.
     * @param  string|null  $search  The search value.
     * @param  string  $sort  The sort value.
     * @param  string  $direction  The direction value.
     *
     * @since 1.0.0
     */
    public function __construct(
        public readonly int $page,
        public readonly int $perPage,
        public readonly ?string $search,
        public readonly string $sort,
        public readonly string $direction,
    ) {}

    /**
     * Build pagination parameters from standard request input.
     *
     * @param  array<int, string>  $allowedSorts
     * @param  Request  $request  Incoming HTTP request.
     * @param  string  $defaultSort  The default sort value.
     * @param  string  $defaultDirection  The default direction value.
     * @return self Current fluent instance.
     *
     * @since 1.0.0
     */
    public static function fromRequest(
        Request $request,
        array $allowedSorts,
        string $defaultSort = 'created_at',
        string $defaultDirection = 'desc'
    ): self {
        $settings = app(SystemSettingService::class);
        $maxPerPage = (int) rescue(
            fn () => $settings->get('api.pagination.max_per_page', config('hairvision.pagination.max_per_page', 100)),
            config('hairvision.pagination.max_per_page', 100),
            report: false
        );
        $defaultPerPage = (int) rescue(
            fn () => $settings->get('api.pagination.default_per_page', config('hairvision.pagination.default_per_page', 15)),
            config('hairvision.pagination.default_per_page', 15),
            report: false
        );
        $maxPerPage = max(1, min($maxPerPage, 500));
        $defaultPerPage = max(1, min($defaultPerPage, $maxPerPage));
        $perPageInput = $request->input('per_page', $defaultPerPage);
        $perPage = max(1, min((int) $perPageInput, $maxPerPage));
        $page = (int) $request->input('page', 1);

        $sort = (string) $request->input('sort', $defaultSort);
        $sort = in_array($sort, $allowedSorts, true) ? $sort : $defaultSort;
        $defaultDirection = strtolower($defaultDirection) === 'asc' ? 'asc' : 'desc';
        $direction = strtolower((string) $request->input('direction', $defaultDirection)) === 'asc'
            ? 'asc'
            : 'desc';
        $search = $request->input('search', $request->input('q'));

        return new self(
            page: max(1, $page),
            perPage: $perPage,
            search: filled($search) ? trim((string) $search) : null,
            sort: $sort,
            direction: $direction,
        );
    }
}
