<?php

declare(strict_types=1);

namespace App\Core\Plugins\Services;

use App\Core\Plugins\Contracts\PluginContribution;
use LogicException;

/**
 * Resolves active plugin contributions for an extension-point tag.
 *
 * To contribute to an extension point from a plugin:
 * 1. Implement the extension point contract (which extends PluginContribution).
 * 2. Bind the class in your plugin's ServiceProvider.
 * 3. Tag the class in its ServiceProvider with `$this->app->tag([...], '<tag>')`.
 *
 * Example:
 * StoreSettingsBootstrapContributor implements BootstrapContributor (which extends
 * PluginContribution) and is tagged with 'tenant.bootstrap.contributors' in
 * StoreSettingsServiceProvider.
 */
final class PluginContributions
{
    public function __construct(
        private readonly PluginManager $pluginManager,
    ) {}

    /**
     * @return list<PluginContribution>
     */
    public function active(string $tag): array
    {
        $contributions = [];

        /** @var iterable<mixed> $services */
        $services = app()->tagged($tag);

        foreach ($services as $service) {
            if (! $service instanceof PluginContribution) {
                throw new LogicException(sprintf(
                    'Tagged service [%s] for tag [%s] must implement [%s].',
                    get_debug_type($service),
                    $tag,
                    PluginContribution::class,
                ));
            }

            $contributions[] = $service;
        }

        $tenant = tenant();
        if ($tenant === null) {
            return [];
        }

        return array_values(array_filter(
            $contributions,
            fn (PluginContribution $contribution): bool => $this->pluginManager->isPluginActive($contribution->pluginId(), $tenant),
        ));
    }
}
