<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class MakePluginCommand extends Command
{
    protected $signature = 'make:plugin 
        {slug : The kebab-case plugin identifier (e.g. pos-integration)}
        {--name= : The human-readable display name}
        {--category=addon : Category (foundation, commerce, marketing, communication, storefront, addon)}
        {--filament : Scaffold Filament plugin class}
        {--migrations : Scaffold tenant migrations folder}
        {--frontend : Scaffold frontend widget component}
        {--core : Mark as core plugin}
        {--deps= : Comma-separated dependency plugin IDs}';

    protected $description = 'Scaffold a new standalone plugin for the Microkernel architecture';

    public function handle(): int
    {
        $slug = Str::slug((string) $this->argument('slug'));
        $studly = Str::studly($slug);
        $snake = Str::snake($slug);
        $displayName = (string) ($this->option('name') ?: Str::headline($slug));
        $category = (string) $this->option('category');
        $isCore = (bool) $this->option('core');
        $withFilament = (bool) $this->option('filament');
        $withMigrations = (bool) $this->option('migrations');
        $withFrontend = (bool) $this->option('frontend');

        $deps = array_filter(array_map('trim', explode(',', (string) ($this->option('deps') ?: ''))));

        $targetDir = app_path("Plugins/{$studly}");

        if (File::isDirectory($targetDir)) {
            $this->error("Plugin directory already exists at: {$targetDir}");

            return self::FAILURE;
        }

        File::makeDirectory("{$targetDir}/src/DTOs", 0755, true);
        File::makeDirectory("{$targetDir}/src/Services", 0755, true);
        File::makeDirectory("{$targetDir}/src/Models", 0755, true);

        // 1. plugin.json
        $manifest = [
            'id' => $slug,
            'name' => $displayName,
            'version' => '1.0.0',
            'category' => $category,
            'description' => "Modular {$displayName} plugin for Turbo Tenant",
            'author' => 'Turbo Tenant Team',
            'is_core' => $isCore,
            'dependencies' => array_values($deps),
            'providers' => [
                "App\\Plugins\\{$studly}\\{$studly}ServiceProvider",
            ],
        ];

        if ($withFilament) {
            $manifest['filament'] = "App\\Plugins\\{$studly}\\src\\Filament\\{$studly}FilamentPlugin";
        }

        File::put("{$targetDir}/plugin.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // 2. Config DTO (Pattern 1)
        $dtoContent = <<<PHP
<?php

declare(strict_types=1);

namespace App\Plugins\\{$studly}\\src\\DTOs;

final readonly class {$studly}Config
{
    public function __construct(
        public bool \$enabled = true
        // Add your typed configuration properties here
    ) {}
}

PHP;
        File::put("{$targetDir}/src/DTOs/{$studly}Config.php", $dtoContent);

        // 3. Service Provider (Composition Root)
        $spContent = <<<PHP
<?php

declare(strict_types=1);

namespace App\Plugins\\{$studly};

use App\Plugins\\{$studly}\\src\\DTOs\\{$studly}Config;
use App\Plugins\StoreSettings\src\Services\SettingService;
use Illuminate\Support\ServiceProvider;

final class {$studly}ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Composition Root: Bind typed Config DTO (Pattern 1)
        \$this->app->singleton({$studly}Config::class, function (\$app) {
            \$settings = \$app->make(SettingService::class);

            return new {$studly}Config(
                enabled: (bool) \$settings->get('{$slug}.enabled', true)
            );
        });

        // Bind domain services
    }

    public function boot(): void
    {
        // Register events, routes, or pipeline pipes
    }
}

PHP;
        File::put("{$targetDir}/{$studly}ServiceProvider.php", $spContent);

        // 3. Tenant Plugin Class
        $tpContent = <<<PHP
<?php

declare(strict_types=1);

namespace App\Plugins\\{$studly};

use App\Core\Plugins\AbstractTenantPlugin;
use App\Models\Tenant;

final class {$studly}Plugin extends AbstractTenantPlugin
{
    public function bootForTenant(Tenant \$tenant): void
    {
        // Runtime tenant initialization
    }

    public function onActivate(Tenant \$tenant): void
    {
        // Post-activation setup
    }

    public function onDeactivate(Tenant \$tenant): void
    {
        // Deactivation cleanup
    }

    public function onUninstall(Tenant \$tenant): void
    {
        // Pre-purge cleanup
    }
}

PHP;
        File::put("{$targetDir}/{$studly}Plugin.php", $tpContent);

        // 4. Filament Plugin (if requested)
        if ($withFilament) {
            File::makeDirectory("{$targetDir}/src/Filament/Resources", 0755, true);
            File::makeDirectory("{$targetDir}/src/Filament/Pages", 0755, true);

            $filContent = <<<PHP
<?php

declare(strict_types=1);

namespace App\Plugins\\{$studly}\\src\\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;

final class {$studly}FilamentPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return '{$slug}';
    }

    public function register(Panel \$panel): void
    {
        \$panel
            ->resources([
                // Register Filament Resources here
            ])
            ->pages([
                // Register Filament Pages here
            ]);
    }

    public function boot(Panel \$panel): void
    {
        //
    }
}

PHP;
            File::put("{$targetDir}/src/Filament/{$studly}FilamentPlugin.php", $filContent);
        }

        // 5. Migrations (if requested)
        if ($withMigrations) {
            $migDir = "{$targetDir}/database/migrations/tenant";
            File::makeDirectory($migDir, 0755, true);
            $timestamp = date('Y_m_d_His');
            $migFile = "{$timestamp}_create_{$snake}_table.php";

            $migContent = <<<PHP
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('{$snake}')) {
            return;
        }

        Schema::create('{$snake}', function (Blueprint \$table) {
            \$table->id();
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$snake}');
    }
};

PHP;
            File::put("{$migDir}/{$migFile}", $migContent);
        }

        // 6. Frontend Slot (if requested)
        if ($withFrontend) {
            $feDir = "{$targetDir}/resources/js";
            File::makeDirectory($feDir, 0755, true);

            $widgetContent = <<<TSX
import React from 'react';

interface Props {
    data?: Record<string, unknown>;
}

export const {$studly}Widget: React.FC<Props> = () => {
    return (
        <div className="plugin-{$slug}-container p-4 border rounded-lg bg-card">
            <h3 className="font-semibold text-lg">{$displayName}</h3>
        </div>
    );
};

export default {$studly}Widget;

TSX;
            File::put("{$feDir}/{$studly}Widget.tsx", $widgetContent);
        }

        $this->info("✨ Successfully created plugin '{$slug}' at: {$targetDir}");
        $this->comment("Run 'php artisan plugin:discover' to register it into the central database.");

        return self::SUCCESS;
    }
}
