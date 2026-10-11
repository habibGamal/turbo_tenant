<?php

declare(strict_types=1);

namespace App\Core\Plugins\Listeners;

use App\Core\Plugins\Services\PluginManager;
use Stancl\Tenancy\Events\TenancyInitialized;

final class BootTenantPluginsListener
{
    public function __construct(
        private readonly PluginManager $pluginManager
    ) {}

    public function handle(TenancyInitialized $event): void
    {
        $this->pluginManager->bootTenant($event->tenancy->tenant);
    }
}
