<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Plugins\Services\PluginDependencyResolver;
use App\Core\Plugins\Services\PluginLifecycleService;
use App\Core\Plugins\Services\PluginManager;
use App\Core\Plugins\Services\PluginManifestLoader;
use App\Core\Plugins\Services\PluginRegistry;
use Illuminate\Support\ServiceProvider;

final class PluginServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PluginManifestLoader::class);
        $this->app->singleton(PluginDependencyResolver::class);

        $this->app->singleton(PluginRegistry::class, function ($app) {
            $pluginsPath = app_path('Plugins');

            return new PluginRegistry(
                $app->make(PluginManifestLoader::class),
                $pluginsPath
            );
        });

        $this->app->singleton(PluginManager::class, function ($app) {
            return new PluginManager(
                $app,
                $app->make(PluginRegistry::class),
                $app->make(PluginDependencyResolver::class)
            );
        });

        $this->app->singleton(PluginLifecycleService::class, function ($app) {
            return new PluginLifecycleService(
                $app->make(PluginRegistry::class),
                $app->make(PluginDependencyResolver::class),
                $app->make(PluginManager::class)
            );
        });

        // Auto-register discovered plugin service providers
        $registry = $this->app->make(PluginRegistry::class);
        foreach ($registry->all() as $manifest) {
            foreach ($manifest->providers as $providerClass) {
                if (class_exists($providerClass)) {
                    $this->app->register($providerClass);
                }
            }
        }
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Console\Commands\MakePluginCommand::class,
                \App\Console\Commands\PluginDiscoverCommand::class,
                \App\Console\Commands\TenantPluginActivateCommand::class,
                \App\Console\Commands\TenantPluginDeactivateCommand::class,
                \App\Console\Commands\TenantPluginListCommand::class,
            ]);
        }
    }
}
