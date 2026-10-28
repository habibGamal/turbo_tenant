<?php

declare(strict_types=1);

use App\Plugins\MediaOptimizer\src\Jobs\GenerateImageVariantsJob;
use App\Plugins\MediaOptimizer\src\Services\ImageOptimizerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('converts upload to 1920w webp and dispatches variants job', function () {
    Queue::fake([GenerateImageVariantsJob::class]);

    $service = app(ImageOptimizerService::class);
    $file = UploadedFile::fake()->image('test_product.jpg', 2400, 1600);

    $savedPath = $service->processUpload($file, 'products');

    expect($savedPath)->toStartWith('images/products/')
        ->and($savedPath)->toEndWith('.1920.webp')
        ->and(Storage::exists($savedPath))->toBeTrue();

    Queue::assertPushed(GenerateImageVariantsJob::class, function ($job) use ($savedPath) {
        return $job->masterRelativePath === $savedPath && $job->directory === 'images/products';
    });

    Storage::delete($savedPath);

    // Test categories directory
    $catFile = UploadedFile::fake()->image('test_category.png', 1200, 800);
    $catSavedPath = $service->processUpload($catFile, 'categories');

    expect($catSavedPath)->toStartWith('images/categories/')
        ->and($catSavedPath)->toEndWith('.1920.webp')
        ->and(Storage::exists($catSavedPath))->toBeTrue();

    Queue::assertPushed(GenerateImageVariantsJob::class, function ($job) use ($catSavedPath) {
        return $job->masterRelativePath === $catSavedPath && $job->directory === 'images/categories';
    });

    Storage::delete($catSavedPath);
});

it('generates all variants synchronously', function () {
    $service = app(ImageOptimizerService::class);
    $file = UploadedFile::fake()->image('sample.png', 800, 600);
    $tempPath = $file->getRealPath();

    $variants = $service->generateAllVariantsSynchronously($tempPath, 'products', 'unit_test_sample');

    expect($variants['master'])->toBe('images/products/unit_test_sample.1920.webp')
        ->and($variants['mobile'])->toBe('images/products/unit_test_sample.480.webp')
        ->and($variants['placeholder'])->toBe('images/products/unit_test_sample.20.webp')
        ->and(Storage::exists($variants['master']))->toBeTrue()
        ->and(Storage::exists($variants['mobile']))->toBeTrue()
        ->and(Storage::exists($variants['placeholder']))->toBeTrue();

    Storage::delete([$variants['master'], $variants['mobile'], $variants['placeholder']]);
});

it('executes media:migrate-variants command in dry-run mode successfully', function () {
    $this->artisan('media:migrate-variants', ['--dry-run' => true])
        ->assertSuccessful();
});
