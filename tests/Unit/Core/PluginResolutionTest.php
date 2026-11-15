<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Plugins\AbstractTenantPlugin;
use App\Core\Plugins\Contracts\TenantPluginInterface;
use App\Core\Plugins\Services\PluginManager;
use App\Plugins\ComboPackages\ComboPackagesPlugin;
use App\Plugins\CouponsPromotions\CouponsPromotionsPlugin;
use App\Plugins\GuestCheckout\GuestCheckoutPlugin;
use App\Plugins\OrderManagement\OrderManagementPlugin;
use App\Plugins\PaymentProcessing\PaymentProcessingPlugin;
use App\Plugins\SampleGreeting\SampleGreetingPlugin;
use ReflectionClass;
use RuntimeException;
use stdClass;
use Tests\TestCase;

final class PluginResolutionTest extends TestCase
{
    public function test_plugins_without_custom_class_resolve_to_null(): void
    {
        $manager = app(PluginManager::class);

        $pluginsWithoutClasses = [
            'cart-engine',
            'catalog-products',
            'cms-pages',
            'customer-favorites',
            'hero-banners',
            'locations-delivery',
            'media-optimizer',
            'pos-integration',
            'product-reviews',
            'push-notifications',
            'store-settings',
        ];

        foreach ($pluginsWithoutClasses as $pluginId) {
            $instance = $manager->resolvePluginInstanceById($pluginId);
            $this->assertNull(
                $instance,
                "Plugin '{$pluginId}' should not have a concrete plugin class and should resolve to null."
            );
        }
    }

    public function test_remaining_plugins_resolve_to_tenant_plugin_interface(): void
    {
        $manager = app(PluginManager::class);

        $remainingPlugins = [
            'combo-packages',
            'coupons-promotions',
            'guest-checkout',
            'order-management',
            'payment-processing',
            'sample-greeting',
        ];

        foreach ($remainingPlugins as $pluginId) {
            $instance = $manager->resolvePluginInstanceById($pluginId);
            $this->assertInstanceOf(
                TenantPluginInterface::class,
                $instance,
                "Plugin '{$pluginId}' should resolve to a TenantPluginInterface instance."
            );
        }
    }

    public function test_remaining_plugin_classes_do_not_override_empty_lifecycle_methods(): void
    {
        $remainingClasses = [
            ComboPackagesPlugin::class,
            CouponsPromotionsPlugin::class,
            GuestCheckoutPlugin::class,
            OrderManagementPlugin::class,
            PaymentProcessingPlugin::class,
            SampleGreetingPlugin::class,
        ];

        $lifecycleMethods = ['onActivate', 'onDeactivate', 'onUninstall'];

        foreach ($remainingClasses as $class) {
            $reflection = new ReflectionClass($class);

            foreach ($lifecycleMethods as $method) {
                if ($reflection->hasMethod($method)) {
                    $declaringClass = $reflection->getMethod($method)->getDeclaringClass()->getName();
                    $this->assertSame(
                        AbstractTenantPlugin::class,
                        $declaringClass,
                        "Class {$class} should inherit {$method}() from AbstractTenantPlugin without empty override."
                    );
                }
            }
        }
    }

    public function test_resolve_plugin_instance_throws_when_class_does_not_exist(): void
    {
        $manager = app(PluginManager::class);

        $manifest = new \App\Core\Plugins\DTOs\PluginManifest(
            id: 'invalid-plugin',
            name: 'Invalid Plugin',
            version: '1.0.0',
            category: \App\Core\Plugins\Enums\PluginCategory::ADDON,
            basePath: '/dummy/path',
            pluginClass: 'App\\Plugins\\NonExistent\\NonExistentPlugin'
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Plugin class 'App\\Plugins\\NonExistent\\NonExistentPlugin' specified in manifest for 'invalid-plugin' does not exist.");

        $manager->resolvePluginInstance($manifest);
    }

    public function test_resolve_plugin_instance_throws_when_class_does_not_implement_interface(): void
    {
        $manager = app(PluginManager::class);

        $manifest = new \App\Core\Plugins\DTOs\PluginManifest(
            id: 'not-implementing-plugin',
            name: 'Not Implementing Plugin',
            version: '1.0.0',
            category: \App\Core\Plugins\Enums\PluginCategory::ADDON,
            basePath: '/dummy/path',
            pluginClass: stdClass::class
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Plugin class 'stdClass' specified in manifest for 'not-implementing-plugin' must implement ".TenantPluginInterface::class.'.');

        $manager->resolvePluginInstance($manifest);
    }
}
