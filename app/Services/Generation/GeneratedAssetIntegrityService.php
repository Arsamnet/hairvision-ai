<?php

namespace App\Services\Generation;

use App\Models\GeneratedAsset;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Validates private generated files before status recovery or retention work.
 *
 * @since 2.0.0
 */
class GeneratedAssetIntegrityService
{
    /**
     * Verify storage presence, size, MIME, and decodable image dimensions.
     *
     * @param  GeneratedAsset  $asset  Generated asset metadata.
     * @return bool Whether the private file is a usable image.
     *
     * @since 2.0.0
     */
    public function isValid(GeneratedAsset $asset): bool
    {
        try {
            $disk = Storage::disk($asset->disk);

            if (! $disk->exists($asset->path)) {
                return false;
            }

            $size = $disk->size($asset->path);

            if ($size <= 0 || ($asset->size && (int) $asset->size !== $size)) {
                return false;
            }

            $binary = $disk->get($asset->path);
            $details = @getimagesizefromstring($binary);

            return is_array($details)
                && isset($details['mime'])
                && str_starts_with((string) $details['mime'], 'image/')
                && str_starts_with((string) $asset->mime, 'image/');
        } catch (Throwable) {
            return false;
        }
    }
}
