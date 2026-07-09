<?php

namespace App\Services\Debug;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Stringable;
use Throwable;

/**
 * Logs ordered debug steps with safe context redaction.
 *
 * @since 1.0.0
 */
final class DebugStepLogger
{
    private const COUNTER_KEY = '_debug_step_logger_counter';

    /**
     * @var array<string, int>
     */
    private static array $workflowCounters = [];

    /**
     * Log a request-scoped step when debug mode is enabled.
     *
     * @param  array<string, mixed>  $context
     */
    public static function step(Request $request, string $scope, string $message, array $context = []): void
    {
        if (! self::enabled()) {
            return;
        }

        $step = ((int) $request->attributes->get(self::COUNTER_KEY, 0)) + 1;

        $request->attributes->set(self::COUNTER_KEY, $step);

        self::write($scope, $step, $message, array_merge(self::requestContext($request), $context));
    }

    /**
     * Log a non-request workflow step with an automatic per-scope counter.
     *
     * @param  array<string, mixed>  $context
     */
    public static function record(string $scope, string $message, array $context = []): void
    {
        if (! self::enabled()) {
            return;
        }

        $step = (self::$workflowCounters[$scope] ?? 0) + 1;
        self::$workflowCounters[$scope] = $step;

        self::write($scope, $step, $message, $context);
    }

    /**
     * Reset automatic workflow counters.
     */
    public static function reset(?string $scope = null): void
    {
        if ($scope === null) {
            self::$workflowCounters = [];

            return;
        }

        unset(self::$workflowCounters[$scope]);
    }

    /**
     * Determine whether step debugging is currently enabled.
     */
    public static function enabled(): bool
    {
        return (bool) config('app.debug');
    }

    /**
     * Redact sensitive values before they can reach the logger.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public static function sanitize(array $context): array
    {
        $sanitized = [];

        foreach ($context as $key => $value) {
            $sanitized[$key] = self::sanitizeValue((string) $key, $value);
        }

        return $sanitized;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function write(string $scope, int $step, string $message, array $context): void
    {
        Log::debug(
            sprintf('[%s] Step %02d: %s', $scope, $step, $message),
            self::sanitize($context),
        );
    }

    /**
     * Build safe request metadata without request body, cookies, or headers.
     *
     * @return array<string, mixed>
     */
    private static function requestContext(Request $request): array
    {
        return [
            'method' => $request->method(),
            'path' => $request->path(),
            'route_name' => $request->route()?->getName(),
            'tenant_id' => $request->attributes->get('currentTenant')?->id,
            'user_id' => $request->hasSession() ? Auth::id() : null,
        ];
    }

    private static function sanitizeValue(string $key, mixed $value, int $depth = 0): mixed
    {
        if (self::isSensitiveKey($key)) {
            return '[redacted]';
        }

        if ($depth >= 4) {
            return '[max-depth]';
        }

        if ($value instanceof Throwable) {
            return [
                'class' => $value::class,
                'message' => self::limit($value->getMessage()),
                'code' => $value->getCode(),
            ];
        }

        if ($value instanceof UploadedFile) {
            return [
                'client_extension' => $value->getClientOriginalExtension(),
                'mime_type' => $value->getMimeType(),
                'size' => $value->getSize(),
            ];
        }

        if ($value instanceof Collection) {
            $value = $value->all();
        }

        if (is_array($value)) {
            $items = [];
            $index = 0;

            foreach ($value as $childKey => $childValue) {
                if ($index >= 25) {
                    $items['__truncated'] = count($value) - $index;

                    break;
                }

                $items[$childKey] = self::sanitizeValue((string) $childKey, $childValue, $depth + 1);
                $index++;
            }

            return $items;
        }

        if (is_object($value)) {
            $safe = ['class' => $value::class];

            foreach (['id', 'uuid'] as $attribute) {
                if (isset($value->{$attribute}) && is_scalar($value->{$attribute})) {
                    $safe[$attribute] = $value->{$attribute};
                }
            }

            return $safe;
        }

        if (is_string($value) || $value instanceof Stringable) {
            $value = (string) $value;

            if (self::isPhoneKey($key)) {
                return self::maskPhone($value);
            }

            return self::limit($value);
        }

        return $value;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $key = Str::lower($key);

        if (in_array($key, ['code', 'otp', 'password', 'authorization', 'cookie', 'session', 'image', 'photo', 'file'], true)) {
            return true;
        }

        return Str::contains($key, [
            'password',
            'token',
            'secret',
            'credential',
            'bearer',
            'authorization',
            'api_key',
            'apikey',
            'code_hash',
            'private_path',
            'storage_path',
            'raw_payload',
            'raw_response',
            'payload',
            'authority',
            'signature',
            'cookie',
            'session',
        ]);
    }

    private static function isPhoneKey(string $key): bool
    {
        return Str::contains(Str::lower($key), ['phone', 'mobile']);
    }

    private static function maskPhone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (strlen($digits) < 7) {
            return '[masked-phone]';
        }

        return substr($digits, 0, 4).'***'.substr($digits, -2);
    }

    private static function limit(string $value): string
    {
        if (mb_strlen($value) <= 300) {
            return $value;
        }

        return mb_substr($value, 0, 300).'... [truncated]';
    }
}
