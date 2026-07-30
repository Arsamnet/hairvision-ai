<?php

namespace App\Support;

use Throwable;

/**
 * Builds detailed exception diagnostics without exposing secrets or stack traces.
 *
 * @since 2.0.0
 */
final class SafeThrowableContext
{
    /**
     * Build a bounded, redacted exception-chain context for operational logs.
     *
     * @param  Throwable  $exception  Failure being diagnosed.
     * @param  array<int, string|null>  $redactValues  Request-specific sensitive values to remove.
     * @return array<string, mixed> Safe diagnostic context.
     *
     * @since 2.0.0
     */
    public static function make(Throwable $exception, array $redactValues = []): array
    {
        $chain = [];
        $curlErrorCode = null;
        $handlerContext = [];
        $current = $exception;

        while ($current instanceof Throwable && count($chain) < 5) {
            $message = self::redactMessage($current->getMessage(), $redactValues);

            if ($curlErrorCode === null && preg_match('/cURL error\s+(\d+)/i', $message, $matches)) {
                $curlErrorCode = (int) $matches[1];
            }

            if ($handlerContext === [] && method_exists($current, 'getHandlerContext')) {
                $candidate = $current->getHandlerContext();

                if (is_array($candidate)) {
                    $handlerContext = self::safeHandlerContext($candidate, $redactValues);
                }
            }

            $chain[] = [
                'class' => $current::class,
                'code' => $current->getCode(),
                'message' => $message,
            ];
            $current = $current->getPrevious();
        }

        return [
            'class' => $exception::class,
            'code' => $exception->getCode(),
            'message' => self::redactMessage($exception->getMessage(), $redactValues),
            'curl_error_code' => $curlErrorCode,
            'chain' => $chain,
            'transport' => $handlerContext,
        ];
    }

    /**
     * Redact common credentials, private paths, phone numbers, and request-specific values.
     *
     * @param  string  $message  Diagnostic message to sanitize.
     * @param  array<int, string|null>  $redactValues  Exact values to replace.
     * @return string Safe bounded message.
     *
     * @since 2.0.0
     */
    public static function redactMessage(string $message, array $redactValues = []): string
    {
        foreach ($redactValues as $value) {
            if (is_string($value) && mb_strlen($value) >= 4) {
                $message = str_replace($value, '[redacted]', $message);
            }
        }

        $message = str_replace(
            [base_path(), storage_path()],
            ['[app-path]', '[storage-path]'],
            $message,
        );
        $message = preg_replace('/Bearer\s+[^\s,;]+/i', 'Bearer [redacted]', $message) ?? $message;
        $message = preg_replace('/\bsk-[A-Za-z0-9_-]{8,}\b/', '[redacted-api-key]', $message) ?? $message;
        $message = preg_replace('/\b09\d{9}\b/', '[masked-phone]', $message) ?? $message;
        $message = preg_replace('/(https?:\/\/[^\s?]+)\?[^\s]+/i', '$1?[redacted-query]', $message) ?? $message;

        return mb_strlen($message) <= 600
            ? $message
            : mb_substr($message, 0, 600).'... [truncated]';
    }

    /**
     * Keep only non-sensitive cURL timing and connection diagnostics.
     *
     * @param  array<string, mixed>  $context  Raw handler context.
     * @param  array<int, string|null>  $redactValues  Exact values to replace.
     * @return array<string, mixed> Safe handler context.
     *
     * @since 2.0.0
     */
    private static function safeHandlerContext(array $context, array $redactValues): array
    {
        $safe = [];
        $allowed = [
            'errno',
            'error',
            'http_code',
            'primary_ip',
            'primary_port',
            'namelookup_time',
            'connect_time',
            'appconnect_time',
            'pretransfer_time',
            'starttransfer_time',
            'total_time',
            'redirect_time',
            'ssl_verifyresult',
        ];

        foreach ($allowed as $key) {
            if (! array_key_exists($key, $context) || ! is_scalar($context[$key])) {
                continue;
            }

            $safe[$key] = is_string($context[$key])
                ? self::redactMessage($context[$key], $redactValues)
                : $context[$key];
        }

        return $safe;
    }
}
