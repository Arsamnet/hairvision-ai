<?php

namespace App\Services\AI;

use App\Support\DebugStepLogger;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\FileExtension;
use Intervention\Image\ImageManager;
use RuntimeException;

class ApiImagePreprocessor
{
    private int $debugStepCounter = 0;

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
        $this->debugStepCounter = 0;

        $requestedFormat = strtolower($format);
        $format = in_array($requestedFormat, ['jpg', 'jpeg', 'png'], true) ? $requestedFormat : 'jpg';

        $this->debugStep($scope, 'API image preprocessing started', [
            'source_disk' => $sourceDisk,
            'source_path_hash' => $this->pathHash($sourcePath),
            'source_extension' => $this->extensionOf($sourcePath),
            'destination_path_hash' => $this->pathHash($destinationPath),
            'destination_extension' => $this->extensionOf($destinationPath),
            'requested_format' => $requestedFormat,
            'normalized_format' => $format,
            'max_dimension' => $maxDimension,
            'jpeg_quality' => $jpegQuality,
        ]);

        $sourceStorage = Storage::disk($sourceDisk);

        $sourceExists = $sourceStorage->exists($sourcePath);

        $this->debugStep($scope, 'Source image existence checked', [
            'source_disk' => $sourceDisk,
            'source_path_hash' => $this->pathHash($sourcePath),
            'source_exists' => $sourceExists,
        ]);

        if (! $sourceExists) {
            $this->debugStep($scope, 'Source image missing; preprocessing stopped', [
                'source_disk' => $sourceDisk,
                'source_path_hash' => $this->pathHash($sourcePath),
            ]);

            throw new RuntimeException('Source image does not exist.');
        }

        $binary = $sourceStorage->get($sourcePath);

        $this->debugStep($scope, 'Source image binary loaded', [
            'source_disk' => $sourceDisk,
            'source_path_hash' => $this->pathHash($sourcePath),
            'binary_is_string' => is_string($binary),
            'binary_size' => is_string($binary) ? strlen($binary) : null,
        ]);

        if (! is_string($binary) || $binary === '') {
            $this->debugStep($scope, 'Source image binary is empty; preprocessing stopped', [
                'source_disk' => $sourceDisk,
                'source_path_hash' => $this->pathHash($sourcePath),
                'binary_is_string' => is_string($binary),
            ]);

            throw new RuntimeException('Source image is empty.');
        }

        $manager = ImageManager::usingDriver(Driver::class);

        $this->debugStep($scope, 'Image manager initialized', [
            'driver' => Driver::class,
        ]);

        /*
         * Intervention Image v4:
         * - decodeBinary() reads raw binary image data.
         * - scaleDown() keeps aspect ratio and never upscales.
         */
        $image = $manager->decodeBinary($binary)->scaleDown(
            width: $maxDimension,
            height: $maxDimension,
        );

        $this->debugStep($scope, 'Source image decoded and scaled down', [
            'max_dimension' => $maxDimension,
            'normalized_format' => $format,
        ]);

        if ($format === 'png') {
            $encoded = $image->encodeUsingFileExtension(FileExtension::PNG);
            $extension = 'png';
            $mime = 'image/png';

            $this->debugStep($scope, 'Image encoded as PNG', [
                'extension' => $extension,
                'mime' => $mime,
            ]);
        } else {
            $encoded = $image->encodeUsingFileExtension(
                FileExtension::JPG,
                quality: $jpegQuality,
                progressive: true,
            );

            $extension = 'jpg';
            $mime = 'image/jpeg';

            $this->debugStep($scope, 'Image encoded as JPEG', [
                'extension' => $extension,
                'mime' => $mime,
                'jpeg_quality' => $jpegQuality,
                'progressive' => true,
            ]);
        }

        $encodedBinary = (string) $encoded;

        $this->debugStep($scope, 'Encoded image binary prepared', [
            'encoded_binary_size' => strlen($encodedBinary),
            'extension' => $extension,
            'mime' => $mime,
        ]);

        if ($encodedBinary === '') {
            $this->debugStep($scope, 'Encoded image binary is empty; preprocessing stopped', [
                'extension' => $extension,
                'mime' => $mime,
            ]);

            throw new RuntimeException('Encoded API image is empty.');
        }

        $destinationPath = preg_replace('/\.(jpg|jpeg|png|webp)$/i', '', $destinationPath);
        $destinationPath .= '.'.$extension;

        $localStorage = Storage::disk('local');

        $this->debugStep($scope, 'Destination path normalized', [
            'destination_disk' => 'local',
            'destination_path_hash' => $this->pathHash($destinationPath),
            'destination_extension' => $extension,
        ]);

        $localStorage->makeDirectory(dirname($destinationPath));

        $this->debugStep($scope, 'Destination directory ensured', [
            'destination_disk' => 'local',
            'destination_path_hash' => $this->pathHash($destinationPath),
        ]);

        $localStorage->put($destinationPath, $encodedBinary);

        $storedSize = $localStorage->size($destinationPath);

        $this->debugStep($scope, 'Preprocessed API image stored', [
            'destination_disk' => 'local',
            'destination_path_hash' => $this->pathHash($destinationPath),
            'destination_exists' => $localStorage->exists($destinationPath),
            'stored_size' => $storedSize,
            'extension' => $extension,
            'mime' => $mime,
        ]);

        return [
            'disk' => 'local',
            'path' => $destinationPath,
            'mime' => $mime,
            'extension' => $extension,
            'size' => $storedSize,
            'binary_content' => $encodedBinary,
        ];
    }

    private function debugStep(string $scope, string $message, array $context = []): void
    {
        DebugStepLogger::workflowStep($scope, ++$this->debugStepCounter, $message, $context);
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