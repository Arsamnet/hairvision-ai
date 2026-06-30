<?php

namespace App\Support\Query;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Applies reusable date range filters to dashboard list queries.
 *
 * @since 1.0.0
 */
class DateRangeFilter
{
    public static function apply(
        Builder $query,
        Request $request,
        string $column = 'created_at',
        string $fromKey = 'created_from',
        string $untilKey = 'created_until'
    ): Builder {
        $from = self::parseDate($request->input($fromKey));
        $until = self::parseDate($request->input($untilKey));

        return $query
            ->when($from, fn (Builder $query) => $query->whereDate($column, '>=', $from?->toDateString()))
            ->when($until, fn (Builder $query) => $query->whereDate($column, '<=', $until?->toDateString()));
    }

    private static function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', trim($value))->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
