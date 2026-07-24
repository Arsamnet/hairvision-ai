<?php

namespace App\Support\Query;

use App\Support\JalaliDate;
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
    /**
     * Apply the supported date range to a query.
     *
     * @param  Builder  $query  Query builder to constrain.
     * @param  Request  $request  Incoming HTTP request.
     * @param  string  $column  The column value.
     * @param  string  $fromKey  The from key value.
     * @param  string  $untilKey  The until key value.
     * @return Builder Configured query builder.
     *
     * @since 1.0.0
     */
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

    /**
     * Parse date.
     *
     * @param  mixed  $value  Input value supplied to the operation.
     * @return CarbonImmutable|null Resolved operation result.
     *
     * @since 1.0.0
     */
    private static function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            if (JalaliDate::isValidJalaliDate((string) $value)) {
                return CarbonImmutable::instance(JalaliDate::fromJalaliDate((string) $value))->startOfDay();
            }

            return CarbonImmutable::createFromFormat('Y-m-d', trim($value))->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
