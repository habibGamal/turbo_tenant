<?php

declare(strict_types=1);

namespace App\Core\Plugins\Listeners;

use App\Core\Plugins\Services\PluginManager;
use Stancl\Tenancy\Events\TenancyEnded;

final class ResetTenantPluginsListener
{
    public function __construct(
        private readonly PluginManager $pluginManager
    ) {}

    public function handle(TenancyEnded $event): void
    {
        $this->pluginManager->onTenancyEnded();
    }
}
