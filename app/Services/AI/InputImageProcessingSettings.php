<?php

namespace App\Services\AI;

/**
 * Defines and normalizes the shared input-image processing settings.
 *
 * @since 2.0.0
 */
final class InputImageProcessingSettings
{
    public const DEFAULT_ENABLED = true;

    public const DEFAULT_MAX_DIMENSION = 800;

    public const DEFAULT_JPEG_QUALITY = 85;

    public const ENABLED = 'input_image_processing_enabled';

    public const MAX_DIMENSION = 'input_image_max_dimension';

    public const JPEG_QUALITY = 'input_image_jpeg_quality';

    public const MIN_DIMENSION = 1;

    public const MAX_DIMENSION_LIMIT = 8192;

    public const MIN_JPEG_QUALITY = 1;

    public const MAX_JPEG_QUALITY = 100;

    /**
     * Return the product defaults used for new and seeded model accesses.
     *
     * @return array<string, bool|int>
     *
     * @since 2.0.0
     */
    public static function defaults(): array
    {
        return [
            self::ENABLED => self::DEFAULT_ENABLED,
            self::MAX_DIMENSION => (int) config(
                'hairvision.api_image.max_dimension',
                self::DEFAULT_MAX_DIMENSION,
            ),
            self::JPEG_QUALITY => (int) config(
                'hairvision.api_image.jpeg_quality',
                self::DEFAULT_JPEG_QUALITY,
            ),
        ];
    }

    /**
     * Determine whether input-image processing is enabled.
     *
     * @param  array<string, mixed>  $settings
     * @return bool Whether the requested condition is satisfied.
     *
     * @since 2.0.0
     */
    public static function enabled(array $settings): bool
    {
        return filter_var(
            $settings[self::ENABLED] ?? false,
            FILTER_VALIDATE_BOOLEAN,
        );
    }

    /**
     * Normalize known values before they are persisted.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     *
     * @since 2.0.0
     */
    public static function normalize(array $settings): array
    {
        if (array_key_exists(self::ENABLED, $settings)) {
            $settings[self::ENABLED] = self::enabled($settings);
        }

        foreach ([self::MAX_DIMENSION, self::JPEG_QUALITY] as $key) {
            if (array_key_exists($key, $settings) && filled($settings[$key])) {
                $settings[$key] = (int) $settings[$key];
            }
        }

        return $settings;
    }
}
