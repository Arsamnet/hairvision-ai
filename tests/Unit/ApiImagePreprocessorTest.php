<?php

namespace Tests\Unit;

use App\Services\AI\ApiImagePreprocessor;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApiImagePreprocessorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_it_creates_a_jpeg_with_the_configured_longest_side_without_deformation(): void
    {
        $source = $this->patternedPng(1200, 600);
        Storage::disk('local')->put('sources/landscape.png', $source);

        $result = app(ApiImagePreprocessor::class)->prepareForImageApi(
            sourceDisk: 'local',
            sourcePath: 'sources/landscape.png',
            destinationPath: 'prepared/request.webp',
            maxDimension: 300,
            jpegQuality: 72,
        );

        $imageInfo = getimagesizefromstring($result['binary_content']);

        $this->assertIsArray($imageInfo);
        $this->assertSame([300, 150], [$imageInfo[0], $imageInfo[1]]);
        $this->assertSame(IMAGETYPE_JPEG, $imageInfo[2]);
        $this->assertSame('image/jpeg', $result['mime']);
        $this->assertSame('jpg', $result['extension']);
        $this->assertSame('prepared/request.jpg', $result['path']);
        $this->assertSame($source, Storage::disk('local')->get('sources/landscape.png'));
        Storage::disk('local')->assertExists('prepared/request.jpg');
    }

    public function test_it_enlarges_a_smaller_image_and_applies_the_configured_jpeg_quality(): void
    {
        Storage::disk('local')->put('sources/portrait.png', $this->patternedPng(100, 200));

        $lowQuality = app(ApiImagePreprocessor::class)->prepareForImageApi(
            sourceDisk: 'local',
            sourcePath: 'sources/portrait.png',
            destinationPath: 'prepared/low.jpg',
            maxDimension: 400,
            jpegQuality: 15,
        );

        $highQuality = app(ApiImagePreprocessor::class)->prepareForImageApi(
            sourceDisk: 'local',
            sourcePath: 'sources/portrait.png',
            destinationPath: 'prepared/high.jpg',
            maxDimension: 400,
            jpegQuality: 95,
        );

        $imageInfo = getimagesizefromstring($highQuality['binary_content']);

        $this->assertIsArray($imageInfo);
        $this->assertSame([200, 400], [$imageInfo[0], $imageInfo[1]]);
        $this->assertGreaterThan($lowQuality['size'], $highQuality['size']);
    }

    private function patternedPng(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);

        for ($y = 0; $y < $height; $y += 10) {
            for ($x = 0; $x < $width; $x += 10) {
                $color = imagecolorallocate(
                    $image,
                    ($x * 13 + $y * 3) % 256,
                    ($x * 5 + $y * 17) % 256,
                    ($x * 19 + $y * 7) % 256,
                );
                imagefilledrectangle($image, $x, $y, min($x + 9, $width - 1), min($y + 9, $height - 1), $color);
            }
        }

        ob_start();
        imagepng($image);
        $binary = ob_get_clean();
        imagedestroy($image);

        $this->assertIsString($binary);

        return $binary;
    }
}
