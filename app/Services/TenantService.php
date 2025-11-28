<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;
use DB;

final class TenantService
{
    /**
     * Create storage directories for a tenant.
     */
    public function createStorageDirectories(string $tenantId): void
    {
        $tenantStorage = storage_path('tenant'.$tenantId);

        $paths = [
            $tenantStorage,
            $tenantStorage.'/app',
            $tenantStorage.'/framework',
            $tenantStorage.'/framework/cache',
            $tenantStorage.'/logs',
        ];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                mkdir($path, 0777, true);
            }
        }
    }

    /**
     * Create or get a domain for a tenant.
     */
    public function createDomain(Tenant $tenant, string $domain): void
    {
        $tenant->domains()->firstOrCreate(['domain' => $domain]);
    }

    /**
     * Create the manifest.json file for a tenant.
     */
    public function createManifestFile(string $tenantId): void
    {
        $tenantStorage = storage_path('tenant'.$tenantId);
        $manifestPath = "{$tenantStorage}/app/manifest.json";

        // Only create if it doesn't exist
        if (file_exists($manifestPath)) {
            return;
        }

        $manifest = [
            'name' => ucfirst($tenantId).' Restaurant',
            'short_name' => ucfirst($tenantId),
            'theme_color' => '#893c25',
            'background_color' => '#ffffff',
            'id' => 'com.'.mb_strtolower(str_replace(' ', '', $tenantId)),
            'start_url' => '.',
            'display' => 'standalone',
            'orientation' => 'natural',
            'icons' => [
                [
                    'src' => 'favicon.ico',
                    'sizes' => '64x64',
                    'type' => 'image/x-icon',
                ],
                [
                    'src' => 'android-chrome-192x192.png',
                    'type' => 'image/png',
                    'sizes' => '192x192',
                ],
                [
                    'src' => 'android-chrome-512x512.png',
                    'type' => 'image/png',
                    'sizes' => '512x512',
                ],
            ],
        ];

        file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Create the theme.css file for a tenant.
     */
    public function createThemeFile(string $tenantId): void
    {
        $tenantStorage = storage_path('tenant'.$tenantId);
        $themePath = "{$tenantStorage}/app/theme.css";

        // Only create if it doesn't exist
        if (file_exists($themePath)) {
            return;
        }

        $css = <<<'CSS'
:root {
    --radius: 0.75rem;
    --background: oklch(1 0 0);
    --foreground: oklch(0.145 0 0);
    --card: oklch(1 0 0);
    --card-foreground: oklch(0.145 0 0);
    --popover: oklch(1 0 0);
    --popover-foreground: oklch(0.145 0 0);
    --primary: oklch(0.205 0 0);
    --primary-foreground: oklch(0.985 0 0);
    --secondary: oklch(0.97 0 0);
    --secondary-foreground: oklch(0.205 0 0);
    --muted: oklch(0.97 0 0);
    --muted-foreground: oklch(0.556 0 0);
    --accent: oklch(0.97 0 0);
    --accent-foreground: oklch(0.205 0 0);
    --destructive: oklch(0.577 0.245 27.325);
    --border: oklch(0.922 0 0);
    --input: oklch(0.922 0 0);
    --ring: oklch(0.708 0 0);
    --chart-1: oklch(0.646 0.222 41.116);
    --chart-2: oklch(0.6 0.118 184.704);
    --chart-3: oklch(0.398 0.07 227.392);
    --chart-4: oklch(0.828 0.189 84.429);
    --chart-5: oklch(0.769 0.188 70.08);
    --sidebar: oklch(0.985 0 0);
    --sidebar-foreground: oklch(0.145 0 0);
    --sidebar-primary: oklch(0.205 0 0);
    --sidebar-primary-foreground: oklch(0.985 0 0);
    --sidebar-accent: oklch(0.97 0 0);
    --sidebar-accent-foreground: oklch(0.205 0 0);
    --sidebar-border: oklch(0.922 0 0);
    --sidebar-ring: oklch(0.708 0 0);
}

.dark {
    --background: oklch(0.145 0 0);
    --foreground: oklch(0.985 0 0);
    --card: oklch(0.205 0 0);
    --card-foreground: oklch(0.985 0 0);
    --popover: oklch(0.205 0 0);
    --popover-foreground: oklch(0.985 0 0);
    --primary: oklch(0.922 0 0);
    --primary-foreground: oklch(0.205 0 0);
    --secondary: oklch(0.269 0 0);
    --secondary-foreground: oklch(0.985 0 0);
    --muted: oklch(0.269 0 0);
    --muted-foreground: oklch(0.708 0 0);
    --accent: oklch(0.269 0 0);
    --accent-foreground: oklch(0.985 0 0);
    --destructive: oklch(0.704 0.191 22.216);
    --border: oklch(1 0 0 / 10%);
    --input: oklch(1 0 0 / 15%);
    --ring: oklch(0.556 0 0);
    --chart-1: oklch(0.488 0.243 264.376);
    --chart-2: oklch(0.696 0.17 162.48);
    --chart-3: oklch(0.769 0.188 70.08);
    --chart-4: oklch(0.627 0.265 303.9);
    --chart-5: oklch(0.645 0.246 16.439);
    --sidebar: oklch(0.205 0 0);
    --sidebar-foreground: oklch(0.985 0 0);
    --sidebar-primary: oklch(0.488 0.243 264.376);
    --sidebar-primary-foreground: oklch(0.985 0 0);
    --sidebar-accent: oklch(0.269 0 0);
    --sidebar-accent-foreground: oklch(0.985 0 0);
    --sidebar-border: oklch(1 0 0 / 10%);
    --sidebar-ring: oklch(0.556 0 0);
}
CSS;

        file_put_contents($themePath, $css);
    }

    /**
     * Check if a tenant's database already exists.
     */
    public function databaseExists(string $tenantId): bool
    {
        $databaseName = 'tenant'.$tenantId;
        $result = DB::selectOne('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?', [$databaseName]);

        return $result !== null;
    }

    /**
     * Setup a tenant with all required resources (storage, domain, manifest, theme).
     */
    public function setupTenant(Tenant $tenant, string $domain): void
    {
        $tenantId = $tenant->id;

        $this->createStorageDirectories($tenantId);
        $this->createDomain($tenant, $domain);
        $this->createManifestFile($tenantId);
        $this->createThemeFile($tenantId);
    }

    /**
     * Create a tenant record, handling the case where the database might already exist.
     */
    public function createTenant(string $tenantId): Tenant
    {
        if ($this->databaseExists($tenantId)) {
            return Tenant::withoutEvents(function () use ($tenantId) {
                return Tenant::query()->updateOrCreate(['id' => $tenantId], []);
            });
        }

        return Tenant::create(['id' => $tenantId]);
    }
}
