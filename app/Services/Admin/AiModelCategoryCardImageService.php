<?php

namespace App\Services\Admin;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Stores and removes administrator-managed AI application card images.
 *
 * @since 2.1.0
 */
class AiModelCategoryCardImageService
{
    private const STORAGE_DIRECTORY = 'category-cards';

    private const PUBLIC_PATH_PREFIX = '/storage/category-cards/';

    /**
     * Store a validated card image on the public disk.
     *
     * @param  UploadedFile  $image  Validated image upload.
     * @return string Public image path.
     *
     * @since 2.1.0
     */
    public function store(UploadedFile $image): string
    {
        $extension = strtolower($image->extension() ?: 'jpg');
        $path = $image->storeAs(
            self::STORAGE_DIRECTORY,
            Str::uuid().'.'.$extension,
            'public'
        );

        if (! is_string($path) || $path === '') {
            throw new RuntimeException('The AI application card image could not be stored.');
        }

        return '/storage/'.$path;
    }

    /**
     * Remove an uploaded card image while preserving system theme assets.
     *
     * @param  string|null  $publicPath  Stored public image path.
     * @return void No value is returned.
     *
     * @since 2.1.0
     */
    public function deleteManaged(?string $publicPath): void
    {
        $publicPath = trim((string) $publicPath);

        if (! str_starts_with($publicPath, self::PUBLIC_PATH_PREFIX)) {
            return;
        }

        $relativePath = Str::after($publicPath, '/storage/');

        if ($relativePath === '' || str_contains($relativePath, '..')) {
            return;
        }

        Storage::disk('public')->delete($relativePath);
    }
}
