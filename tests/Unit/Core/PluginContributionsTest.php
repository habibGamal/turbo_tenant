<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Plugins\Contracts\PluginContribution;
use App\Core\Plugins\Enums\PluginStatus;
use App\Core\Plugins\Services\PluginContributions;
use App\Core\Plugins\Services\PluginManager;
use App\Models\TenantPlugin;
use LogicException;
use stdClass;
use Tests\TestCase;

final class TestActiveContribution implements PluginContribution
{
    public function pluginId(): string
    {
        return 'store-settings';
    }
}

final class TestInactiveContribution implements PluginContribution
{
    public function pluginId(): string
    {
        return 'sample-greeting';
    }
}

final class PluginContributionsTest extends TestCase
{
    public function test_active_returns_only_contributions_of_active_plugins(): void
    {
        $tenant = tenant();
        $manager = app(PluginManager::class);

        TenantPlugin::query()->updateOrCreate(
            ['tenant_id' => (string) $tenant->id, 'plugin_id' => 'store-settings'],
            ['status' => PluginStatus::ACTIVE]
        );
        TenantPlugin::query()->updateOrCreate(
            ['tenant_id' => (string) $tenant->id, 'plugin_id' => 'sample-greeting'],
            ['status' => PluginStatus::DEACTIVATED]
        );

        $manager->resetTenant($tenant);

        $activeContribution = new TestActiveContribution();
        $inactiveContribution = new TestInactiveContribution();

        app()->tag([
            TestActiveContribution::class,
            TestInactiveContribution::class,
        ], 'test.extension.tag');

        app()->instance(TestActiveContribution::class, $activeContribution);
        app()->instance(TestInactiveContribution::class, $inactiveContribution);

        $service = new PluginContributions($manager);
        $result = $service->active('test.extension.tag');

        $this->assertCount(1, $result);
        $this->assertSame($activeContribution, $result[0]);
    }

    public function test_active_returns_empty_array_without_a_tenant(): void
    {
        $manager = app(PluginManager::class);

        app()->tag([TestActiveContribution::class], 'test.extension.no_tenant');
        app()->instance(TestActiveContribution::class, new TestActiveContribution());

        $service = new PluginContributions($manager);

        tenancy()->end();
        $this->assertNull(tenant());

        try {
            $result = $service->active('test.extension.no_tenant');
            $this->assertSame([], $result);
        } finally {
            $this->initializeTenancy();
        }
    }

    public function test_active_throws_logic_exception_for_tagged_non_contribution(): void
    {
        $manager = app(PluginManager::class);

        app()->tag([stdClass::class], 'test.extension.invalid');
        app()->instance(stdClass::class, new stdClass());

        $service = new PluginContributions($manager);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/stdClass.*test\.extension\.invalid.*PluginContribution/');

        $service->active('test.extension.invalid');
    }
}
