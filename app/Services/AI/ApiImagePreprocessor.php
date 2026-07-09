<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\FileExtension;
use Intervention\Image\ImageManager;
use RuntimeException;

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
     *     binary_content: string
     * }
     */
    public function prepareForImageApi(
        string $sourceDisk,
        string $sourcePath,
        string $destinationPath,
        int $maxDimension = 1024,
        int $jpegQuality = 85,
        string $format = 'jpg',
    ): array {
        $scope = 'ai.image_preprocessor';

        $requestedFormat = strtolower($format);
        $format = in_array($requestedFormat, ['jpg', 'jpeg', 'png'], true) ? $requestedFormat : 'jpg';

        $sourceStorage = Storage::disk($sourceDisk);

        $sourceExists = $sourceStorage->exists($sourcePath);

        if (! $sourceExists) {
            throw new RuntimeException('Source image does not exist.');
        }

        $binary = $sourceStorage->get($sourcePath);

        if (! is_string($binary) || $binary === '') {
            throw new RuntimeException('Source image is empty.');
        }

        $manager = ImageManager::usingDriver(Driver::class);

        /*
         * Intervention Image v4:
         * - decodeBinary() reads raw binary image data.
         * - scaleDown() keeps aspect ratio and never upscales.
         */
        $image = $manager->decodeBinary($binary)->scaleDown(
            width: $maxDimension,
            height: $maxDimension,
        );

        if ($format === 'png') {
            $encoded = $image->encodeUsingFileExtension(FileExtension::PNG);
            $extension = 'png';
            $mime = 'image/png';

        } else {
            $encoded = $image->encodeUsingFileExtension(
                FileExtension::JPG,
                quality: $jpegQuality,
                progressive: true,
            );

            $extension = 'jpg';
            $mime = 'image/jpeg';

        }

        $encodedBinary = (string) $encoded;

        if ($encodedBinary === '') {
            throw new RuntimeException('Encoded API image is empty.');
        }

        $destinationPath = preg_replace('/\.(jpg|jpeg|png|webp)$/i', '', $destinationPath);
        $destinationPath .= '.'.$extension;

        $localStorage = Storage::disk('local');

        $localStorage->makeDirectory(dirname($destinationPath));

        $localStorage->put($destinationPath, $encodedBinary);

        $storedSize = $localStorage->size($destinationPath);

        return [
            'disk' => 'local',
            'path' => $destinationPath,
            'mime' => $mime,
            'extension' => $extension,
            'size' => $storedSize,
            'binary_content' => $encodedBinary,
        ];
    }

    private function pathHash(string $path): string
    {
        return substr(hash('sha256', $path), 0, 16);
    }

    private function extensionOf(string $path): ?string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return $extension !== '' ? strtolower($extension) : null;
    }
}
