<?php

declare(strict_types=1);

namespace App\Plugins\MediaOptimizer\src\Jobs;

use App\Models\Tenant;
use App\Plugins\MediaOptimizer\src\DTOs\MediaOptimizerConfig;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Spatie\Image\Image;
use Throwable;

final class GenerateImageVariantsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 5;

    public function __construct(
        public string $masterRelativePath,
        public string $directory,
        public string $key,
        public ?string $tenantId = null
    ) {
        $cleanDir = mb_trim($this->directory, '/');
        if (! str_starts_with($cleanDir, 'images')) {
            $cleanDir = "images/{$cleanDir}";
        }
        $this->directory = $cleanDir;

        $this->tenantId = $tenantId ?? (function_exists('tenant') && tenant() ? tenant('id') : null);
    }

    public function handle(MediaOptimizerConfig $config): void
    {
        if ($this->tenantId && (! function_exists('tenant') || ! tenant() || tenant('id') !== $this->tenantId)) {
            $tenant = Tenant::find($this->tenantId);
            if ($tenant) {
                $tenant->run(fn () => $this->processVariants($config));

                return;
            }
        }

        $this->processVariants($config);
    }

    private function processVariants(MediaOptimizerConfig $config): void
    {
        $masterFullPath = Storage::path($this->masterRelativePath);

        if (! file_exists($masterFullPath)) {
            Log::warning("GenerateImageVariantsJob: Master image not found at {$masterFullPath}");

            return;
        }

        $path480 = Storage::path("{$this->directory}/{$this->key}.480.webp");
        $path20 = Storage::path("{$this->directory}/{$this->key}.20.webp");

        // 1. Generate 480w Mobile / Card variant
        if (! file_exists($path480)) {
            $this->generateVariant($masterFullPath, $path480, $config->mobileWidth, $config->quality);
        }

        // 2. Generate 20w LQIP blur placeholder variant
        if (! file_exists($path20)) {
            $this->generateVariant($masterFullPath, $path20, $config->placeholderWidth, $config->placeholderQuality);
        }
    }

    private function generateVariant(string $sourcePath, string $destinationPath, int $width, int $quality): void
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
            Log::error("GenerateImageVariantsJob failed generating variant [{$destinationPath}]: {$e->getMessage()}");
        }
    }
}
