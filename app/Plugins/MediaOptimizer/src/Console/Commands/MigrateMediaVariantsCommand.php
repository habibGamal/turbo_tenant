<?php

declare(strict_types=1);

namespace App\Plugins\MediaOptimizer\src\Console\Commands;

use App\Models\Tenant;
use App\Plugins\MediaOptimizer\src\Services\ImageOptimizerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class MigrateMediaVariantsCommand extends Command
{
    protected $signature = 'media:migrate-variants
                            {--tenant= : Process a specific tenant ID}
                            {--cleanup-old : Delete old original files after successful conversion}
                            {--dry-run : Preview actions without modifying files or database}';

    protected $description = 'Migrate existing tenant storage images into 1920w, 480w, and 20w WebP variants and update database records.';

    public function handle(ImageOptimizerService $optimizer): int
    {
        $tenantId = $this->option('tenant');
        $cleanupOld = (bool) $this->option('cleanup-old');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('--- DRY RUN MODE: No files or database records will be modified ---');
        }

        $tenants = $tenantId
            ? Tenant::where('id', $tenantId)->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->error('No matching tenants found.');

            return self::FAILURE;
        }

        $this->info("Found {$tenants->count()} tenant(s) to process.");

        foreach ($tenants as $tenant) {
            $this->line('');
            $this->info("Processing Tenant: {$tenant->id}...");

            $tenant->run(function () use ($optimizer, $cleanupOld, $dryRun, $tenant) {
                $this->migrateTenantStorage($optimizer, $cleanupOld, $dryRun, $tenant);
            });
        }

        $this->newLine();
        $this->info('Media migration completed successfully!');

        return self::SUCCESS;
    }

    private function migrateTenantStorage(ImageOptimizerService $optimizer, bool $cleanupOld, bool $dryRun, Tenant $tenant): void
    {
        $appPath = storage_path('app');

        if (! is_dir($appPath)) {
            $this->warn("  Storage app directory does not exist for tenant: {$tenant->id}");

            return;
        }

        $subDirectories = [
            'products',
            'categories',
            'hero-sliders',
            'product-cards',
            'site-images',
            'attachments',
            'pwa-icons',
        ];

        $convertedCount = 0;
        $dbUpdatedCount = 0;

        foreach ($subDirectories as $sub) {
            // Check both old path (e.g. "products") and new path (e.g. "images/products")
            $dirsToCheck = [$sub, "images/{$sub}"];

            foreach ($dirsToCheck as $dir) {
                $fullDir = "{$appPath}/{$dir}";
                if (! is_dir($fullDir)) {
                    continue;
                }

                $files = File::files($fullDir);

                foreach ($files as $file) {
                    $filename = $file->getFilename();

                    // If already under images/ and ends with a variant suffix, it's already complete
                    if (str_starts_with($dir, 'images') && preg_match('/\.(1920|480|20)\.webp$/i', $filename)) {
                        continue;
                    }

                    $extension = mb_strtolower($file->getExtension());
                    if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                        continue;
                    }

                    // Extract base key (strip any .1920, .480, .20)
                    $rawName = pathinfo($filename, PATHINFO_FILENAME);
                    $cleanKey = preg_replace('/\.(1920|480|20)$/i', '', $rawName);

                    $targetDir = "images/{$sub}";
                    $oldRelativePath = "{$dir}/{$filename}";
                    $newRelativePath = "{$targetDir}/{$cleanKey}.1920.webp";

                    // Skip redundant work if source and destination are the exact same 1920w file
                    if ($oldRelativePath === $newRelativePath) {
                        continue;
                    }

                    $this->line("  Migrating [{$oldRelativePath}] -> [{$newRelativePath}]");

                    if (! $dryRun) {
                        try {
                            $optimizer->generateAllVariantsSynchronously($file->getPathname(), $targetDir, $cleanKey);
                            $convertedCount++;

                            // Update database records
                            $updated = $this->updateTenantDatabaseRecords(
                                $newRelativePath,
                                [
                                    $oldRelativePath,
                                    "{$sub}/{$filename}",
                                    "{$sub}/{$cleanKey}.1920.webp",
                                    "{$sub}/{$cleanKey}.jpg",
                                    "{$sub}/{$cleanKey}.jpeg",
                                    "{$sub}/{$cleanKey}.png",
                                ]
                            );
                            $dbUpdatedCount += $updated;

                            if ($cleanupOld && file_exists($file->getPathname()) && realpath($file->getPathname()) !== realpath(storage_path("app/{$newRelativePath}"))) {
                                File::delete($file->getPathname());
                                $this->line("    Deleted original: {$oldRelativePath}");
                            }
                        } catch (Throwable $e) {
                            $this->error("    Failed to convert {$filename}: {$e->getMessage()}");
                        }
                    } else {
                        $convertedCount++;
                    }
                }
            }
        }

        if (! $dryRun) {
            $this->normalizeExistingDatabasePaths();
        }

        $this->info("  Tenant {$tenant->id}: Converted {$convertedCount} file(s), updated {$dbUpdatedCount} database row(s).");
    }

    private function normalizeExistingDatabasePaths(): void
    {
        $tables = [
            'products' => 'image',
            'categories' => 'image',
            'hero_sliders' => 'image',
        ];

        foreach ($tables as $table => $column) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                DB::table($table)
                    ->whereNotNull($column)
                    ->where($column, 'not like', 'images/%')
                    ->where($column, 'not like', '/images/%')
                    ->where($column, 'not like', 'http%')
                    ->where($column, 'not like', 'data:%')
                    ->get(['id', $column])
                    ->each(function ($row) use ($table, $column) {
                        $val = (string) $row->{$column};
                        $clean = mb_ltrim($val, '/');
                        if (str_starts_with($clean, 'storage/')) {
                            $clean = mb_substr($clean, mb_strlen('storage/'));
                        }
                        $normalized = "images/{$clean}";
                        DB::table($table)->where('id', $row->id)->update([$column => $normalized]);
                    });
            }
        }
    }

    private function updateTenantDatabaseRecords(string $newPath, array $oldPaths): int
    {
        $totalUpdated = 0;

        $oldVariants = [];
        foreach ($oldPaths as $p) {
            $trimmed = mb_ltrim($p, '/');
            $oldVariants[] = $trimmed;
            $oldVariants[] = "/{$trimmed}";
            $oldVariants[] = "storage/{$trimmed}";
            $oldVariants[] = "/storage/{$trimmed}";
        }
        $oldVariants = array_unique($oldVariants);

        // 1. Products table
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'image')) {
            $updated = DB::table('products')->whereIn('image', $oldVariants)->update(['image' => $newPath]);
            $totalUpdated += $updated;
        }

        // 2. Categories table
        if (Schema::hasTable('categories') && Schema::hasColumn('categories', 'image')) {
            $updated = DB::table('categories')->whereIn('image', $oldVariants)->update(['image' => $newPath]);
            $totalUpdated += $updated;
        }

        // 3. Hero Sliders table
        if (Schema::hasTable('hero_sliders') && Schema::hasColumn('hero_sliders', 'image')) {
            $updated = DB::table('hero_sliders')->whereIn('image', $oldVariants)->update(['image' => $newPath]);
            $totalUpdated += $updated;
        }

        // 4. Settings table (values)
        if (Schema::hasTable('settings') && Schema::hasColumn('settings', 'value')) {
            $updated = DB::table('settings')->whereIn('value', $oldVariants)->update(['value' => $newPath]);
            $totalUpdated += $updated;
        }

        return $totalUpdated;
    }
}
