<?php

declare(strict_types=1);

namespace App\Plugins\MediaOptimizer;

use App\Plugins\MediaOptimizer\src\Console\Commands\MigrateMediaVariantsCommand;
use App\Plugins\MediaOptimizer\src\DTOs\MediaOptimizerConfig;
use App\Plugins\MediaOptimizer\src\Services\ImageOptimizerService;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\ServiceProvider;

final class MediaOptimizerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 1. Register Config DTO (Pattern 1)
        $this->app->singleton(MediaOptimizerConfig::class, function () {
            return new MediaOptimizerConfig(
                maxWidth: 1920,
                mobileWidth: 480,
                placeholderWidth: 20,
                quality: 80,
                placeholderQuality: 30
            );
        });

        // 2. Register ImageOptimizerService
        $this->app->singleton(ImageOptimizerService::class, function ($app) {
            return new ImageOptimizerService($app->make(MediaOptimizerConfig::class));
        });

        // 3. Register Artisan Commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                MigrateMediaVariantsCommand::class,
            ]);
        }
    }

    public function boot(): void
    {
        // Register Filament FileUpload Macro for all plugins
        if (class_exists(FileUpload::class)) {
            FileUpload::macro('optimizeForWeb', function (?string $directory = null) {
                /** @var FileUpload $this */
                $targetDir = $directory ? mb_trim($directory, '/') : 'uploads';
                if (! str_starts_with($targetDir, 'images')) {
                    $targetDir = "images/{$targetDir}";
                }

                $this->directory($targetDir)
                    ->image()
                    ->saveUploadedFileUsing(function ($file, $get, $set, $component) use ($targetDir) {
                        /** @var ImageOptimizerService $service */
                        $service = app(ImageOptimizerService::class);

                        return $service->processUpload($file, $targetDir);
                    });

                return $this;
            });
        }
    }
}
