<?php

namespace App\Support\Pagination;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Formats consistent JSON pagination metadata.
 *
 * @since 1.0.0
 */
class PaginatedResponse
{
    /**
     * Convert a paginator to a standard API payload.
     */
    public static function make(LengthAwarePaginator $paginator, ?int $draw = null): array
    {
        $payload = [
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ];

        if ($draw !== null) {
            $payload['draw'] = $draw;
            $payload['recordsTotal'] = $paginator->total();
            $payload['recordsFiltered'] = $paginator->total();
        }

        return $payload;
    }
}
