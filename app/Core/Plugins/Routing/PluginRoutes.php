<?php

declare(strict_types=1);

namespace App\Core\Plugins\Routing;

use Illuminate\Routing\RouteRegistrar;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

final class PluginRoutes
{
    /**
     * Route registrar for tenant routes gated by the active state of a specific plugin.
     */
    public static function tenant(string $pluginId): RouteRegistrar
    {
        return Route::middleware([
            'web',
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
            "plugin.active:{$pluginId}",
        ]);
    }

    /**
     * Route registrar for tenant routes that must remain reachable regardless of plugin state.
     *
     * Only for webhooks/callbacks that must work regardless of plugin state.
     */
    public static function tenantUngated(): RouteRegistrar
    {
        return Route::middleware([
            'web',
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
        ]);
    }
}
