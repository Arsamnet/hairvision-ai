<?php

namespace App\Services\AI;

use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\FileExtension;
use Intervention\Image\ImageManager;
use InvalidArgumentException;
use RuntimeException;

/**
 * Normalizes private AI input and generated images to proportional JPEG files.
 *
 * @since 2.0.0
 */
class JpegImageNormalizer
{
    /**
     * Normalize generated image bytes with the product storage defaults.
     *
     * @param  string  $binary  The binary value.
     * @return array{
     *     binary_content: string,
     *     mime: string,
     *     extension: string,
     *     size: int,
     *     width: int,
     *     height: int
     * }
     *
     * @since 2.0.0
     */
    public function normalizeWithProductDefaults(string $binary): array
    {
        return $this->normalize(
            $binary,
            (int) config(
                'hairvision.api_image.max_dimension',
                InputImageProcessingSettings::DEFAULT_MAX_DIMENSION,
            ),
            (int) config(
                'hairvision.api_image.jpeg_quality',
                InputImageProcessingSettings::DEFAULT_JPEG_QUALITY,
            ),
        );
    }

    /**
     * Encode image bytes as JPEG with an exact longest-side dimension.
     *
     * @param  string  $binary  The binary value.
     * @param  int  $maxDimension  The max dimension value.
     * @param  int  $jpegQuality  The jpeg quality value.
     * @return array{
     *     binary_content: string,
     *     mime: string,
     *     extension: string,
     *     size: int,
     *     width: int,
     *     height: int
     * }
     *
     * @since 2.0.0
     */
    public function normalize(string $binary, int $maxDimension, int $jpegQuality): array
    {
        if ($binary === '') {
            throw new RuntimeException('Image content is empty.');
        }

        if ($maxDimension < InputImageProcessingSettings::MIN_DIMENSION
            || $maxDimension > InputImageProcessingSettings::MAX_DIMENSION_LIMIT) {
            throw new InvalidArgumentException('Image maximum dimension is invalid.');
        }

        if ($jpegQuality < InputImageProcessingSettings::MIN_JPEG_QUALITY
            || $jpegQuality > InputImageProcessingSettings::MAX_JPEG_QUALITY) {
            throw new InvalidArgumentException('Image JPEG quality is invalid.');
        }

        $manager = ImageManager::usingDriver(Driver::class);

        /* scale() preserves the aspect ratio without cropping. The longest
         * side becomes exact, including when a smaller image is enlarged. */
        $image = $manager->decodeBinary($binary)->orient()->scale(
            width: $maxDimension,
            height: $maxDimension,
        );

        $encodedBinary = (string) $image->encodeUsingFileExtension(
            FileExtension::JPG,
            quality: $jpegQuality,
            progressive: true,
        );

        if ($encodedBinary === '') {
            throw new RuntimeException('Normalized JPEG image is empty.');
        }

        return [
            'binary_content' => $encodedBinary,
            'mime' => 'image/jpeg',
            'extension' => 'jpg',
            'size' => strlen($encodedBinary),
            'width' => $image->width(),
            'height' => $image->height(),
        ];
    }
}
