<?php

declare(strict_types=1);

namespace App\Domains\Property\Services;

use RuntimeException;

final class ImageSanitizerAndHasherService
{
    /**
     * Strips all EXIF/GPS tags by re-rendering pixel data to a clean WebP file (SRS Section 201).
     */
    public function stripExifAndSaveWebp(string $sourceFilePath, string $destinationFilePath, int $maxWidth = 1600, int $quality = 82): void
    {
        $rawBytes = file_get_contents($sourceFilePath);
        if ($rawBytes === false) {
            throw new RuntimeException('Unable to read uploaded image.');
        }

        $srcImg = @imagecreatefromstring($rawBytes);
        if ($srcImg === false) {
            throw new RuntimeException('Unsupported or corrupted image file.');
        }

        $origWidth = imagesx($srcImg);
        $origHeight = imagesy($srcImg);

        $targetWidth = min($origWidth, $maxWidth);
        $targetHeight = (int) round($origHeight * ($targetWidth / max(1, $origWidth)));

        $cleanCanvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($cleanCanvas, false);
        imagesavealpha($cleanCanvas, true);

        imagecopyresampled(
            $cleanCanvas,
            $srcImg,
            0, 0, 0, 0,
            $targetWidth,
            $targetHeight,
            $origWidth,
            $origHeight
        );

        // Ensure parent directory exists
        $dir = dirname($destinationFilePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Re-encoding from raw pixels discards all embedded EXIF/GPS/MakerNote metadata
        imagewebp($cleanCanvas, $destinationFilePath, $quality);

        imagedestroy($srcImg);
        imagedestroy($cleanCanvas);
    }

    /**
     * Computes a 64-bit difference hash (dHash) as a 16-character hex string for duplicate detection.
     */
    public function computePerceptualHash(string $imageFilePath): string
    {
        $rawBytes = file_get_contents($imageFilePath);
        $img = $rawBytes ? @imagecreatefromstring($rawBytes) : false;
        if ($img === false) {
            return str_repeat('0', 16);
        }

        // Resize to 9x8 grayscale grid to compare 8 horizontal adjacent pixel gradients across 8 rows
        $resized = imagecreatetruecolor(9, 8);
        imagecopyresampled($resized, $img, 0, 0, 0, 0, 9, 8, imagesx($img), imagesy($img));

        $bits = '';
        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $rgbLeft = imagecolorat($resized, $x, $y);
                $rgbRight = imagecolorat($resized, $x + 1, $y);

                $lumLeft = (($rgbLeft >> 16) & 0xFF) * 0.299 + (($rgbLeft >> 8) & 0xFF) * 0.587 + ($rgbLeft & 0xFF) * 0.114;
                $lumRight = (($rgbRight >> 16) & 0xFF) * 0.299 + (($rgbRight >> 8) & 0xFF) * 0.587 + ($rgbRight & 0xFF) * 0.114;

                $bits .= ($lumLeft > $lumRight) ? '1' : '0';
            }
        }

        imagedestroy($img);
        imagedestroy($resized);

        $hex = '';
        foreach (str_split($bits, 4) as $nibble) {
            $hex .= dechex((int) bindec($nibble));
        }

        return str_pad($hex, 16, '0', STR_PAD_LEFT);
    }
}
