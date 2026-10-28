<?php

declare(strict_types=1);

namespace App\Plugins\MediaOptimizer\src\Services;

use App\Plugins\MediaOptimizer\src\DTOs\MediaOptimizerConfig;
use App\Plugins\MediaOptimizer\src\Jobs\GenerateImageVariantsJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Image\Image;
use Throwable;

final class ImageOptimizerService
{
    public function __construct(
        private readonly MediaOptimizerConfig $config
    ) {}

    public function normalizeDirectory(string $directory): string
    {
        $directory = mb_trim($directory, '/');
        if (! str_starts_with($directory, 'images')) {
            $directory = "images/{$directory}";
        }

        return $directory;
    }

    /**
     * Process an incoming upload:
     * 1. Synchronously creates the 1920w WebP master image.
     * 2. Dispatches a background job for 480w and 20w variants.
     * 3. Returns the master relative path to be stored in the database.
     */
    public function processUpload(UploadedFile|string $file, string $directory): string
    {
        $directory = $this->normalizeDirectory($directory);
        Storage::makeDirectory($directory);

        $sourcePath = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $extension = mb_strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));

        // Fallback for UploadedFile original extension if temp file has no extension
        if ($file instanceof UploadedFile && empty($extension)) {
            $extension = mb_strtolower($file->getClientOriginalExtension());
        }

        // Bypass non-convertible files (SVG, ICO, etc.)
        if (in_array($extension, $this->config->nonConvertibleExtensions, true)) {
            if ($file instanceof UploadedFile) {
                return $file->store($directory);
            }
            $targetName = Str::ulid().'.'.$extension;
            $targetPath = "{$directory}/{$targetName}";
            Storage::put($targetPath, file_get_contents($sourcePath));

            return $targetPath;
        }

        $key = mb_strtolower((string) Str::ulid());
        $masterRelativePath = "{$directory}/{$key}.1920.webp";
        $masterFullPath = Storage::path($masterRelativePath);

        // 1. Synchronous 1920w WebP generation
        $this->createWebpVariant($sourcePath, $masterFullPath, $this->config->maxWidth, $this->config->quality);

        // 2. Dispatch background job for 480w and 20w variants
        GenerateImageVariantsJob::dispatch($masterRelativePath, $directory, $key);

        return $masterRelativePath;
    }

    /**
     * Generate all variants (1920w, 480w, 20w) synchronously (ideal for CLI migrations / seeders).
     *
     * @return array{master: string, mobile: string, placeholder: string}
     */
    public function generateAllVariantsSynchronously(string $sourcePath, string $directory, string $key): array
    {
        $directory = $this->normalizeDirectory($directory);
        Storage::makeDirectory($directory);

        $masterRelative = "{$directory}/{$key}.1920.webp";
        $mobileRelative = "{$directory}/{$key}.480.webp";
        $placeholderRelative = "{$directory}/{$key}.20.webp";

        $masterFull = Storage::path($masterRelative);
        $mobileFull = Storage::path($mobileRelative);
        $placeholderFull = Storage::path($placeholderRelative);

        // 1920w
        $this->createWebpVariant($sourcePath, $masterFull, $this->config->maxWidth, $this->config->quality);

        // 480w
        $this->createWebpVariant($masterFull, $mobileFull, $this->config->mobileWidth, $this->config->quality);

        // 20w
        $this->createWebpVariant($masterFull, $placeholderFull, $this->config->placeholderWidth, $this->config->placeholderQuality);

        return [
            'master' => $masterRelative,
            'mobile' => $mobileRelative,
            'placeholder' => $placeholderRelative,
        ];
    }

    private function createWebpVariant(string $sourcePath, string $destinationPath, int $width, int $quality): void
    {
        try {
            $image = Image::load($sourcePath);

            $currentWidth = $image->getWidth();
            if ($currentWidth > $width) {
                $image->width($width);
            }

            try {
                $image->quality($quality)->optimize()->save($destinationPath);
            } catch (Throwable) {
                $image->quality($quality)->save($destinationPath);
            }
        } catch (Throwable $e) {
            Log::error("ImageOptimizerService failed creating WebP [{$destinationPath}]: {$e->getMessage()}");
            throw $e;
        }
    }
}
