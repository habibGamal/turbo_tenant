<?php

declare(strict_types=1);

namespace App\Core\Plugins\Contracts;

use App\Core\Plugins\DTOs\PluginManifest;
use App\Models\Tenant;

interface TenantPluginInterface
{
    /**
     * Get the unique plugin identifier.
     */
    public function getId(): string;

    /**
     * Get the display name of the plugin.
     */
    public function getName(): string;

    /**
     * Get the parsed manifest DTO.
     */
    public function getManifest(): PluginManifest;

    /**
     * Boot the plugin runtime in active tenant context.
     */
    public function bootForTenant(Tenant $tenant): void;

    /**
     * Hook called after the plugin is successfully activated for a tenant.
     */
    public function onActivate(Tenant $tenant): void;

    /**
     * Hook called after the plugin is deactivated for a tenant.
     */
    public function onDeactivate(Tenant $tenant): void;

    /**
     * Hook called before the plugin is uninstalled and purged for a tenant.
     */
    public function onUninstall(Tenant $tenant): void;
}
