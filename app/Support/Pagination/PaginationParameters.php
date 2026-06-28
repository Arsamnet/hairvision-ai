<?php

namespace App\Support\Pagination;

use Illuminate\Http\Request;

/**
 * Normalizes reusable API/admin pagination and sorting input.
 *
 * @since 1.0.0
 */
class PaginationParameters
{
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
     */
    public static function fromRequest(Request $request, array $allowedSorts, string $defaultSort = 'created_at'): self
    {
        $maxPerPage = (int) config('hairvision.pagination.max_per_page', 100);
        $defaultPerPage = (int) config('hairvision.pagination.default_per_page', 15);
        $perPageInput = $request->input('per_page', $defaultPerPage);
        $perPage = max(1, min((int) $perPageInput, $maxPerPage));
        $page = (int) $request->input('page', 1);

        $sort = (string) $request->input('sort', $defaultSort);
        $sort = in_array($sort, $allowedSorts, true) ? $sort : $defaultSort;
        $direction = strtolower((string) $request->input('direction', 'desc')) === 'asc'
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
