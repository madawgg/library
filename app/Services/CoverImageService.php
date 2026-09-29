<?php

namespace App\Services;

use GdImage;
use InvalidArgumentException;

/**
 * Converts an uploaded picture into the stored cover format with GD (spec 002, RF-03):
 * WebP, at most `books.cover_max_dimension` px on its longest side (never enlarged)
 * and at most `books.cover_max_bytes`, lowering the quality when needed.
 */
class CoverImageService
{
    private const INITIAL_QUALITY = 85;

    private const QUALITY_STEP = 10;

    private const MINIMUM_QUALITY = 5;

    /**
     * @return array{webp: string, width: int, height: int, compressed: bool}
     *                                                                        `compressed` is true when the quality had to be lowered to fit the size limit.
     */
    public function process(string $sourcePath): array
    {
        $image = @imagecreatefromstring((string) file_get_contents($sourcePath));

        if (! $image instanceof GdImage) {
            throw new InvalidArgumentException('The file is not a readable image.');
        }

        $image = $this->resize($this->applyExifOrientation($image, $sourcePath));
        $maxBytes = (int) config('books.cover_max_bytes');

        $quality = self::INITIAL_QUALITY;
        $webp = $this->encode($image, $quality);
        $compressed = false;

        while (strlen($webp) > $maxBytes && $quality > self::MINIMUM_QUALITY) {
            $quality = max(self::MINIMUM_QUALITY, $quality - self::QUALITY_STEP);
            $webp = $this->encode($image, $quality);
            $compressed = true;
        }

        return ['webp' => $webp, 'width' => imagesx($image), 'height' => imagesy($image), 'compressed' => $compressed];
    }

    /**
     * Phone pictures are often stored rotated with an EXIF orientation flag.
     */
    private function applyExifOrientation(GdImage $image, string $sourcePath): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $orientation = (@exif_read_data($sourcePath) ?: [])['Orientation'] ?? 1;

        return match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }

    private function resize(GdImage $image): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $maxDimension = (int) config('books.cover_max_dimension');

        if (max($width, $height) <= $maxDimension) {
            return $image;
        }

        $scale = $maxDimension / max($width, $height);
        $resized = imagescale($image, (int) round($width * $scale), (int) round($height * $scale), IMG_BICUBIC);

        return $resized instanceof GdImage ? $resized : $image;
    }

    private function encode(GdImage $image, int $quality): string
    {
        ob_start();
        imagewebp($image, null, $quality);

        return (string) ob_get_clean();
    }
}
