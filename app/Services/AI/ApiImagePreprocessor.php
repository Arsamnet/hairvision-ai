<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\FileExtension;
use Intervention\Image\ImageManager;
use InvalidArgumentException;
use RuntimeException;

/**
 * Creates private, provider-ready JPEG copies of source images.
 *
 * @since 2.0.0
 */
class ApiImagePreprocessor
{
    /**
     * Prepare an optimized API input image while keeping the original upload unchanged.
     *
     * @return array{
     *     disk: string,
     *     path: string,
     *     mime: string,
     *     extension: string,
     *     size: int,
     *     width: int,
     *     height: int,
     *     binary_content: string
     * }
     */
    public function prepareForImageApi(
        string $sourceDisk,
        string $sourcePath,
        string $destinationPath,
        int $maxDimension,
        int $jpegQuality,
    ): array {
        if ($maxDimension < InputImageProcessingSettings::MIN_DIMENSION
            || $maxDimension > InputImageProcessingSettings::MAX_DIMENSION_LIMIT) {
            throw new InvalidArgumentException('Input image maximum dimension is invalid.');
        }

        if ($jpegQuality < InputImageProcessingSettings::MIN_JPEG_QUALITY
            || $jpegQuality > InputImageProcessingSettings::MAX_JPEG_QUALITY) {
            throw new InvalidArgumentException('Input image JPEG quality is invalid.');
        }

        $sourceStorage = Storage::disk($sourceDisk);

        if (! $sourceStorage->exists($sourcePath)) {
            throw new RuntimeException('Source image does not exist.');
        }

        $binary = $sourceStorage->get($sourcePath);

        if (! is_string($binary) || $binary === '') {
            throw new RuntimeException('Source image is empty.');
        }

        $manager = ImageManager::usingDriver(Driver::class);

        /* scale() fits the image inside the square boundary without cropping
         * or changing its aspect ratio. The longest side becomes the exact
         * configured dimension, including when a smaller image is enlarged. */
        $image = $manager->decodeBinary($binary)->orient()->scale(
            width: $maxDimension,
            height: $maxDimension,
        );

        $encoded = $image->encodeUsingFileExtension(
            FileExtension::JPG,
            quality: $jpegQuality,
            progressive: true,
        );

        $encodedBinary = (string) $encoded;

        if ($encodedBinary === '') {
            throw new RuntimeException('Encoded API image is empty.');
        }

        $destinationPath = (string) preg_replace('/\.[^.\/]+$/', '', $destinationPath);
        $destinationPath .= '.jpg';

        $localStorage = Storage::disk('local');

        try {
            $localStorage->makeDirectory(dirname($destinationPath));

            if (! $localStorage->put($destinationPath, $encodedBinary)) {
                throw new RuntimeException('Prepared API image could not be stored.');
            }

            $storedSize = $localStorage->size($destinationPath);
        } catch (\Throwable $exception) {
            $localStorage->delete($destinationPath);

            throw $exception;
        }

        return [
            'disk' => 'local',
            'path' => $destinationPath,
            'mime' => 'image/jpeg',
            'extension' => 'jpg',
            'size' => $storedSize,
            'width' => $image->width(),
            'height' => $image->height(),
            'binary_content' => $encodedBinary,
        ];
    }
}
