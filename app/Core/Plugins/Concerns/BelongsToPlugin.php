<?php

declare(strict_types=1);

namespace App\Core\Plugins\Concerns;

use App\Core\Plugins\Services\PluginManager;
use App\Core\Plugins\Services\PluginRegistry;

trait BelongsToPlugin
{
    /**
     * Explicit plugin ID override if needed. Defaults to auto-resolution from class namespace.
     */
    protected static ?string $pluginId = null;

    /**
     * Get the plugin ID that this Resource or Page belongs to.
     */
    public static function getPluginId(): ?string
    {
        if (static::$pluginId !== null) {
            return static::$pluginId;
        }

        return app(PluginRegistry::class)->getPluginIdForClass(static::class);
    }

    /**
     * Determine whether the tenant user can access this Resource or Page.
     */
    public static function canAccess(): bool
    {
        $tenant = function_exists('tenant') ? tenant() : null;

        if ($tenant !== null) {
            $pluginId = static::getPluginId();

            if ($pluginId !== null && ! app(PluginManager::class)->isPluginActive($pluginId, $tenant)) {
                return false;
            }
        }

        return parent::canAccess();
    }

    /**
     * Determine whether this Resource or Page should register in the navigation sidebar.
     */
    public static function shouldRegisterNavigation(): bool
    {
        $tenant = function_exists('tenant') ? tenant() : null;

        if ($tenant !== null) {
            $pluginId = static::getPluginId();

            if ($pluginId !== null && ! app(PluginManager::class)->isPluginActive($pluginId, $tenant)) {
                return false;
            }
        }

        return parent::shouldRegisterNavigation();
    }
}
