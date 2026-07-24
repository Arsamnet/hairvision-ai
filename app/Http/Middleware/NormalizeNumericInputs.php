<?php

namespace App\Http\Middleware;

use App\Support\NumberNormalizer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Normalizes numeric-looking request values before validation and persistence.
 *
 * @since 1.0.0
 */
final class NormalizeNumericInputs
{
    /**
     * Execute the normalize numeric inputs operation.
     *
     * @param  Closure(Request): Response  $next
     * @param  Request  $request  Incoming HTTP request.
     * @return Response HTTP response.
     *
     * @since 1.0.0
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->query->replace($this->normalizeValues($request->query->all()));
        $request->request->replace($this->normalizeValues($request->request->all()));

        return $next($request);
    }

    /**
     * Recursively normalize scalar numeric values while preserving secrets.
     *
     * @param  array<string|int, mixed>  $values
     * @param  bool  $containsSensitiveValues  The contains sensitive values value.
     * @return array<string|int, mixed>
     *
     * @since 1.0.0
     */
    private function normalizeValues(array $values, bool $containsSensitiveValues = false): array
    {
        foreach ($values as $key => $value) {
            $isSensitive = $containsSensitiveValues || $this->isSensitiveKey((string) $key);

            if (is_array($value)) {
                $values[$key] = $this->normalizeValues($value, $isSensitive);

                continue;
            }

            if (! $isSensitive && is_string($value)) {
                $values[$key] = NumberNormalizer::normalizeNumericString($value);
            }
        }

        return $values;
    }

    /**
     * Determine whether sensitive key.
     *
     * @param  string  $key  The key value.
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 1.0.0
     */
    private function isSensitiveKey(string $key): bool
    {
        return preg_match('/(?:password|secret|token|credential|api_key|merchant_id)/i', $key) === 1;
    }
}
