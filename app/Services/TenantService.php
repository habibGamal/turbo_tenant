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
     * Check if a tenant's database already exists.
     */
    public function databaseExists(string $tenantId): bool
    {
        $databaseName = 'tenant'.$tenantId;
        $result = DB::selectOne('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?', [$databaseName]);

        return $result !== null;
    }

    /**
     * Setup a tenant with all required resources (storage, domain, manifest).
     */
    public function setupTenant(Tenant $tenant, string $domain): void
    {
        $tenantId = $tenant->id;

        $this->createStorageDirectories($tenantId);
        $this->createDomain($tenant, $domain);
        $this->createManifestFile($tenantId);
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
