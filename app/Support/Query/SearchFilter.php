<?php

namespace App\Support\Query;

use App\Support\NumberNormalizer;
use Illuminate\Database\Eloquent\Builder;

/**
 * Applies a reusable LIKE search block to table/list queries.
 *
 * @since 1.0.0
 */
class SearchFilter
{
    /**
     * Apply a contains search across columns and optional relation columns.
     *
     * @param  array<int, string>  $columns
     * @param  array<string, array<int, string>>  $relations
     */
    public static function apply(Builder $query, ?string $search, array $columns, array $relations = []): Builder
    {
        if (! filled($search)) {
            return $query;
        }

        $terms = self::terms((string) $search);

        if ($terms === []) {
            return $query;
        }

        foreach ($terms as $termVariants) {
            $query->where(function (Builder $query) use ($columns, $relations, $termVariants): void {
                $hasCondition = false;

                foreach ($columns as $column) {
                    foreach ($termVariants as $variant) {
                        $method = $hasCondition ? 'orWhere' : 'where';
                        $query->{$method}($column, 'like', $variant);
                        $hasCondition = true;
                    }
                }

                foreach ($relations as $relation => $relationColumns) {
                    $method = $hasCondition ? 'orWhereHas' : 'whereHas';
                    $query->{$method}($relation, function (Builder $relationQuery) use ($relationColumns, $termVariants): void {
                        $hasRelationCondition = false;

                        foreach ($relationColumns as $column) {
                            foreach ($termVariants as $variant) {
                                $method = $hasRelationCondition ? 'orWhere' : 'where';
                                $relationQuery->{$method}($column, 'like', $variant);
                                $hasRelationCondition = true;
                            }
                        }
                    });
                    $hasCondition = true;
                }
            });
        }

        return $query;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private static function terms(string $search): array
    {
        $normalized = NumberNormalizer::toLatinDigits(trim($search));
        $tokens = preg_split('/\s+/u', $normalized, flags: PREG_SPLIT_NO_EMPTY) ?: [];

        return collect($tokens)
            ->take(6)
            ->map(fn (string $token): array => self::variants($token))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function variants(string $search): array
    {
        $variants = [$search];
        $digitsOnly = NumberNormalizer::digitsOnly($search);

        if ($digitsOnly !== '' && $digitsOnly !== $search) {
            $variants[] = $digitsOnly;
        }

        return collect($variants)
            ->map(fn (string $variant): string => self::likeTerm($variant))
            ->unique()
            ->values()
            ->all();
    }

    private static function likeTerm(string $search): string
    {
        return '%'.addcslashes(trim($search), '\\%_').'%';
    }
}
