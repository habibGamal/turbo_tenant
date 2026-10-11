<?php

declare(strict_types=1);

namespace App\Core\Plugins;

use App\Core\Plugins\Contracts\TenantPluginInterface;
use App\Core\Plugins\DTOs\PluginManifest;
use App\Models\Tenant;

abstract class AbstractTenantPlugin implements TenantPluginInterface
{
    public function __construct(
        protected readonly PluginManifest $manifest
    ) {}

    public function getId(): string
    {
        return $this->manifest->id;
    }

    public function getName(): string
    {
        return $this->manifest->name;
    }

    public function getManifest(): PluginManifest
    {
        return $this->manifest;
    }

    public function bootForTenant(Tenant $tenant): void
    {
        // Default: no-op, override in concrete plugin
    }

    public function onActivate(Tenant $tenant): void
    {
        // Default: no-op, override in concrete plugin
    }

    public function onDeactivate(Tenant $tenant): void
    {
        // Default: no-op, override in concrete plugin
    }

    public function onUninstall(Tenant $tenant): void
    {
        // Default: no-op, override in concrete plugin
    }
}
