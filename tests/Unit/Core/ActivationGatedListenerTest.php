<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Plugins\Listeners\ActivationGatedListener;
use App\Core\Plugins\Services\PluginManager;
use App\Models\TenantPlugin;
use stdClass;
use Tests\TestCase;

final class ActivationGatedListenerTest extends TestCase
{
    public function test_listener_is_not_called_when_plugin_is_inactive(): void
    {
        $manager = app(PluginManager::class);
        $tenant = tenant();

        TenantPlugin::query()
            ->where('tenant_id', (string) $tenant->id)
            ->where('plugin_id', 'inactive-plugin')
            ->delete();

        $manager->resetTenant($tenant);

        $targetListener = new class
        {
            public int $called = 0;

            public function handle(object $event): void
            {
                $this->called++;
            }
        };

        app()->instance('MockInactiveTargetListener', $targetListener);

        $gated = new ActivationGatedListener('inactive-plugin', 'MockInactiveTargetListener', $manager);
        $result = $gated((object) ['id' => 1]);

        $this->assertNull($result);
        $this->assertSame(0, $targetListener->called);
    }

    public function test_listener_is_called_once_with_event_when_plugin_is_active(): void
    {
        $manager = app(PluginManager::class);
        $tenant = tenant();

        $event = (object) ['id' => 42, 'status' => 'completed'];

        $targetListener = new class
        {
            public int $called = 0;

            public ?object $receivedEvent = null;

            public function handle(object $event): string
            {
                $this->called++;
                $this->receivedEvent = $event;

                return 'success';
            }
        };

        app()->instance('MockActiveTargetListener', $targetListener);

        $gated = new ActivationGatedListener('store-settings', 'MockActiveTargetListener', $manager);

        $result = $gated($event);

        $this->assertSame('success', $result);
        $this->assertSame(1, $targetListener->called);
        $this->assertSame($event, $targetListener->receivedEvent);
    }

    public function test_class_at_method_syntax_calls_specified_method(): void
    {
        $manager = app(PluginManager::class);
        $tenant = tenant();

        $event = (object) ['name' => 'order.updated'];

        $targetListener = new class
        {
            public int $customCalled = 0;

            public int $handleCalled = 0;

            public ?object $receivedEvent = null;

            public function handleCustomAction(object $event): string
            {
                $this->customCalled++;
                $this->receivedEvent = $event;

                return 'custom_result';
            }

            public function handle(object $event): void
            {
                $this->handleCalled++;
            }
        };

        app()->instance('MockCustomTargetListener', $targetListener);

        $gated = new ActivationGatedListener(
            'store-settings',
            'MockCustomTargetListener@handleCustomAction',
            $manager
        );

        $this->assertSame('MockCustomTargetListener', $gated->getListenerClass());
        $this->assertSame('handleCustomAction', $gated->getMethod());

        $result = $gated($event);

        $this->assertSame('custom_result', $result);
        $this->assertSame(1, $targetListener->customCalled);
        $this->assertSame(0, $targetListener->handleCalled);
        $this->assertSame($event, $targetListener->receivedEvent);
    }

    public function test_listener_is_not_called_when_tenancy_is_not_initialized(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $targetListener = new class
        {
            public int $called = 0;

            public function handle(object $event): void
            {
                $this->called++;
            }
        };

        app()->instance('MockNoTenancyTargetListener', $targetListener);

        $gated = new ActivationGatedListener('store-settings', 'MockNoTenancyTargetListener');

        $result = $gated(new stdClass());

        $this->assertNull($result);
        $this->assertSame(0, $targetListener->called);
    }
}
