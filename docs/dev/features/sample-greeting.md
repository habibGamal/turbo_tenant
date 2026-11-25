# Sample greeting (developer reference)

## Overview

A minimal plugin that shows the plugin contract: manifest, service provider, plugin class with a tenant boot hook, a tenant-scoped config DTO and a tenant migration (`sample_greetings`). It has no routes, admin screens or storefront UI.

**Status:** demo, not for production stores · **Plugin:** `sample-greeting` ([plugin.json](../../../app/Plugins/SampleGreeting/plugin.json)) · **Related:** [Development guide › Adding a feature](../development.md#adding-a-feature)

## How to use

Not applicable for store users. Developers: read the four files in [app/Plugins/SampleGreeting](../../../app/Plugins/SampleGreeting) as a starting template, or scaffold a new plugin with `php artisan make:plugin`.

## How it works

1. [`SampleGreetingServiceProvider`](../../../app/Plugins/SampleGreeting/SampleGreetingServiceProvider.php) is registered at boot like every plugin provider.
2. [`SampleGreetingPlugin::bootForTenant`](../../../app/Plugins/SampleGreeting/SampleGreetingPlugin.php) runs when a tenant with the plugin active is initialized.
3. `tenant_scoped` lists [`SampleGreetingConfig`](../../../app/Plugins/SampleGreeting/src/DTOs/SampleGreetingConfig.php) and the `sample-greeting.message` binding, which are forgotten from the container on every tenant switch.

## Configuration

Not applicable: no settings.

## Testing

Covered indirectly by plugin engine tests ([PluginEngineTest](../../../tests/Feature/PluginEngineTest.php)).

## Troubleshooting

Not applicable.

## History

- 2026-10: added with the plugin microkernel.
