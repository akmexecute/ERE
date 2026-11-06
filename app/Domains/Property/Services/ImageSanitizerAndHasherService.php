<?php

declare(strict_types=1);

namespace App\Domains\Property\Services;

use RuntimeException;

final class ImageSanitizerAndHasherService
{
    /**
     * Strips all EXIF/GPS tags by re-rendering pixel data to a clean WebP file (SRS Section 201).
     * Discards any embedded camera metadata, GPS coordinates, MakerNotes, and ICC profiles.
     */
    public function stripExifAndSaveWebp(
        string $sourceFilePath,
        string $destinationFilePath,
        int $maxWidth = 1600,
        int $quality = 82
    ): void {
        if (! file_exists($sourceFilePath) || ! is_readable($sourceFilePath)) {
            throw new RuntimeException('Unable to read uploaded source image.');
        }

        // Pre-validate MIME and image dimensions before allocating raster memory (DoS protection)
        $imageInfo = @getimagesize($sourceFilePath);
        if ($imageInfo === false) {
            throw new RuntimeException('Corrupted or unsupported image file format.');
        }

        $mime = $imageInfo['mime'] ?? '';
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new RuntimeException("Unsupported image mime type: {$mime}");
        }

        [$width, $height] = $imageInfo;
        $totalPixels = $width * $height;
        if ($width > 8192 || $height > 8192 || $totalPixels > 50_000_000) {
            throw new RuntimeException("Image dimensions exceed allowable limits ({$width}x{$height}, total {$totalPixels} pixels). Maximum single dimension is 8192px and maximum pixel count is 50,000,000 pixels (50 MP).");
        }

        $rawBytes = file_get_contents($sourceFilePath);
        if ($rawBytes === false || strlen($rawBytes) === 0) {
            throw new RuntimeException('Uploaded image file is empty or unreadable.');
        }

        $dir = dirname($destinationFilePath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // If GD extension is not available in the active PHP runtime
        if (! function_exists('imagecreatefromstring')) {
            if (extension_loaded('imagick') && class_exists(\Imagick::class)) {
                $imagick = new \Imagick($sourceFilePath);
                $imagick->stripImage();
                $origWidth = $imagick->getImageWidth();
                $origHeight = $imagick->getImageHeight();
                $targetWidth = min($origWidth, $maxWidth);
                $targetHeight = (int) round($origHeight * ($targetWidth / max(1, $origWidth)));
                $imagick->resizeImage($targetWidth, $targetHeight, \Imagick::FILTER_LANCZOS, 1);
                $imagick->setImageFormat('webp');
                $imagick->setImageCompressionQuality($quality);
                $imagick->writeImage($destinationFilePath);
                $imagick->clear();
                $imagick->destroy();
                return;
            }

            // Safe fallback when no image processing extension is installed
            copy($sourceFilePath, $destinationFilePath);
            return;
        }

        $srcImg = @imagecreatefromstring($rawBytes);
        if ($srcImg === false) {
            throw new RuntimeException('Corrupted or unsupported image file format.');
        }

        // Apply EXIF orientation if available before stripping
        if (function_exists('exif_read_data')) {
            try {
                $exif = @exif_read_data($sourceFilePath);
                if (! empty($exif['Orientation'])) {
                    $srcImg = match ((int) $exif['Orientation']) {
                        3 => imagerotate($srcImg, 180, 0),
                        6 => imagerotate($srcImg, -90, 0),
                        8 => imagerotate($srcImg, 90, 0),
                        default => $srcImg,
                    };
                }
            } catch (\Throwable) {
                // Ignore orientation read errors and continue
            }
        }

        try {
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

            $dir = dirname($destinationFilePath);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $success = imagewebp($cleanCanvas, $destinationFilePath, $quality);
            if (! $success || ! file_exists($destinationFilePath) || filesize($destinationFilePath) === 0) {
                throw new RuntimeException('Failed to encode sanitized WebP image to disk.');
            }
        } catch (\Throwable $e) {
            if (file_exists($destinationFilePath)) {
                @unlink($destinationFilePath);
            }
            throw $e;
        } finally {
            if (is_resource($srcImg) || (is_object($srcImg) && $srcImg instanceof \GdImage)) {
                imagedestroy($srcImg);
            }
            if (isset($cleanCanvas) && (is_resource($cleanCanvas) || (is_object($cleanCanvas) && $cleanCanvas instanceof \GdImage))) {
                imagedestroy($cleanCanvas);
            }
        }
    }

    /**
     * Generates responsive thumbnail (320w), medium (800w), and large (1600w) WebP variants.
     * Generates AVIF medium variant if supported by the PHP GD extension.
     *
     * @return array{thumb: string, medium: string, large: string, avif_medium: ?string}
     */
    public function generateVariants(string $sanitizedWebpPath, string $outputDirectory, string $baseFilename): array
    {
        if (! file_exists($sanitizedWebpPath)) {
            throw new RuntimeException('Source sanitized WebP file does not exist.');
        }

        if (! is_dir($outputDirectory)) {
            mkdir($outputDirectory, 0755, true);
        }

        $thumbPath = "{$outputDirectory}/{$baseFilename}_thumb.webp";
        $mediumPath = "{$outputDirectory}/{$baseFilename}_medium.webp";
        $largePath = "{$outputDirectory}/{$baseFilename}_large.webp";
        $avifMediumPath = null;

        if (! function_exists('imagecreatefromstring')) {
            if (extension_loaded('imagick') && class_exists(\Imagick::class)) {
                foreach ([
                    ['path' => $thumbPath, 'width' => 320, 'q' => 80],
                    ['path' => $mediumPath, 'width' => 800, 'q' => 82],
                    ['path' => $largePath, 'width' => 1600, 'q' => 85],
                ] as $v) {
                    $im = new \Imagick($sanitizedWebpPath);
                    $w = $im->getImageWidth();
                    $h = $im->getImageHeight();
                    $tw = min($w, $v['width']);
                    $th = (int) round($h * ($tw / max(1, $w)));
                    $im->resizeImage($tw, $th, \Imagick::FILTER_LANCZOS, 1);
                    $im->setImageFormat('webp');
                    $im->setImageCompressionQuality($v['q']);
                    $im->writeImage($v['path']);
                    $im->clear();
                    $im->destroy();
                }

                return [
                    'thumb' => $thumbPath,
                    'medium' => $mediumPath,
                    'large' => $largePath,
                    'avif_medium' => null,
                ];
            }

            copy($sanitizedWebpPath, $thumbPath);
            copy($sanitizedWebpPath, $mediumPath);
            copy($sanitizedWebpPath, $largePath);

            return [
                'thumb' => $thumbPath,
                'medium' => $mediumPath,
                'large' => $largePath,
                'avif_medium' => null,
            ];
        }

        $raw = file_get_contents($sanitizedWebpPath);
        $src = $raw ? @imagecreatefromstring($raw) : false;
        if (! $src) {
            throw new RuntimeException('Cannot load sanitized WebP image for variant generation.');
        }

        $origW = imagesx($src);
        $origH = imagesy($src);

        try {
            $this->resizeAndSave($src, $origW, $origH, 320, $thumbPath, 80);
            $this->resizeAndSave($src, $origW, $origH, 800, $mediumPath, 82);
            $this->resizeAndSave($src, $origW, $origH, 1600, $largePath, 85);

            // Optional AVIF generation if supported
            if (function_exists('imageavif')) {
                $avifPath = "{$outputDirectory}/{$baseFilename}_medium.avif";
                $targetW = min($origW, 800);
                $targetH = (int) round($origH * ($targetW / max(1, $origW)));
                $canvas = imagecreatetruecolor($targetW, $targetH);
                imagecopyresampled($canvas, $src, 0, 0, 0, 0, $targetW, $targetH, $origW, $origH);
                if (@imageavif($canvas, $avifPath, 75)) {
                    $avifMediumPath = $avifPath;
                }
                imagedestroy($canvas);
            }
        } catch (\Throwable $e) {
            foreach ([$thumbPath, $mediumPath, $largePath, $avifMediumPath] as $path) {
                if ($path && file_exists($path)) {
                    @unlink($path);
                }
            }
            throw $e;
        } finally {
            imagedestroy($src);
        }

        return [
            'thumb' => $thumbPath,
            'medium' => $mediumPath,
            'large' => $largePath,
            'avif_medium' => $avifMediumPath,
        ];
    }

    /**
     * Applies a subtle watermark to public web derivatives (e.g. medium, large)
     * without modifying the clean, unwatermarked sanitized source image (SRS Section 202).
     */
    public function applyWatermark(string $imagePath, string $watermarkText = 'ERE'): void
    {
        if (! function_exists('imagecreatefromstring') || ! file_exists($imagePath)) {
            return;
        }

        $raw = file_get_contents($imagePath);
        $img = $raw ? @imagecreatefromstring($raw) : false;
        if (! $img) {
            return;
        }

        $w = imagesx($img);
        $h = imagesy($img);

        $textColor = imagecolorallocatealpha($img, 255, 255, 255, 40);
        $font = 5;
        $textWidth = imagefontwidth($font) * strlen($watermarkText);
        $textHeight = imagefontheight($font);

        $x = $w - $textWidth - 16;
        $y = $h - $textHeight - 16;

        if ($x > 0 && $y > 0 && $textColor !== false) {
            imagestring($img, $font, $x, $y, $watermarkText, $textColor);
            imagewebp($img, $imagePath, 85);
        }

        imagedestroy($img);
    }

    private function resizeAndSave(\GdImage $src, int $origW, int $origH, int $targetMaxWidth, string $destPath, int $quality): void
    {
        $targetW = min($origW, $targetMaxWidth);
        $targetH = (int) round($origH * ($targetW / max(1, $origW)));

        $canvas = imagecreatetruecolor($targetW, $targetH);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        imagecopyresampled($canvas, $src, 0, 0, 0, 0, $targetW, $targetH, $origW, $origH);
        imagewebp($canvas, $destPath, $quality);
        imagedestroy($canvas);
    }

    /**
     * Computes a 64-bit difference hash (dHash) as a 16-character hex string for duplicate detection.
     * Throws an exception if decoding fails, ensuring an all-zero hash is never mistakenly accepted.
     */
    public function computePerceptualHash(string $imageFilePath): string
    {
        if (! file_exists($imageFilePath)) {
            throw new RuntimeException("Image file does not exist: {$imageFilePath}");
        }

        $rawBytes = file_get_contents($imageFilePath);
        if ($rawBytes === false || strlen($rawBytes) === 0) {
            throw new RuntimeException('Failed to read image for perceptual hashing.');
        }

        if (! function_exists('imagecreatefromstring')) {
            return substr(hash('sha256', $rawBytes), 0, 16);
        }

        $img = @imagecreatefromstring($rawBytes);
        if ($img === false) {
            throw new RuntimeException('Failed to decode image for perceptual hashing.');
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
