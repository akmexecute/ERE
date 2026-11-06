<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\Property\Models\PropertyImage;
use App\Domains\Property\Services\ImageSanitizerAndHasherService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class ProcessPropertyImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public PropertyImage $propertyImage,
    ) {}

    public function handle(ImageSanitizerAndHasherService $sanitizer): void
    {
        $image = $this->propertyImage->fresh();
        if (! $image) {
            return;
        }

        $disk = $image->disk ?? 'public';
        $fullPath = storage_path("app/{$disk}/{$image->original_sanitized_path}");

        if (! file_exists($fullPath)) {
            Log::warning("PropertyImage #{$image->id} source file not found at: {$fullPath}");

            return;
        }

        try {
            $dir = dirname($fullPath);
            $baseName = pathinfo($fullPath, PATHINFO_FILENAME);

            // Generate responsive WebP and optional AVIF variants
            $variants = $sanitizer->generateVariants($fullPath, $dir, $baseName);

            // Apply non-destructive watermark to public display derivatives (SRS Section 202)
            $sanitizer->applyWatermark($variants['medium'], 'ERE Verified');
            $sanitizer->applyWatermark($variants['large'], 'ERE Verified');

            // Compute 64-bit difference hash
            $dHash = $sanitizer->computePerceptualHash($fullPath);

            $relativeDir = dirname($image->original_sanitized_path);
            $image->update([
                'webp_thumb_path' => "{$relativeDir}/" . basename($variants['thumb']),
                'webp_medium_path' => "{$relativeDir}/" . basename($variants['medium']),
                'webp_large_path' => "{$relativeDir}/" . basename($variants['large']),
                'avif_medium_path' => $variants['avif_medium'] ? "{$relativeDir}/" . basename($variants['avif_medium']) : null,
                'perceptual_hash' => $dHash,
                'exif_stripped' => true,
                'watermarked' => true,
            ]);
        } catch (\Throwable $e) {
            Log::error("Failed processing property image #{$image->id}: {$e->getMessage()}");
            throw $e;
        }
    }
}
