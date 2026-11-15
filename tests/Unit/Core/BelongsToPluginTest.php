<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Plugins\Concerns\BelongsToPlugin;
use App\Core\Plugins\Enums\PluginStatus;
use App\Core\Plugins\Services\PluginManager;
use App\Models\TenantPlugin;
use Tests\TestCase;

/**
 * Stands in for a Filament Resource/Page. Methods are protected so the trait's public
 * overrides can widen them (Pint would otherwise force them final, blocking the override).
 */
abstract class DummyParentPage
{
    protected static function canAccess(): bool
    {
        return true;
    }

    protected static function shouldRegisterNavigation(): bool
    {
        return true;
    }
}

final class DummyPluginPage extends DummyParentPage
{
    use BelongsToPlugin;

    public static function getPluginId(): ?string
    {
        return 'sample-greeting';
    }
}

final class BelongsToPluginTest extends TestCase
{
    public function test_can_access_and_navigation_return_false_when_plugin_is_inactive(): void
    {
        $this->setPluginStatus(PluginStatus::DEACTIVATED);

        $this->assertFalse(DummyPluginPage::canAccess());
        $this->assertFalse(DummyPluginPage::shouldRegisterNavigation());
    }

    public function test_can_access_and_navigation_defer_to_parent_when_plugin_is_active(): void
    {
        $this->setPluginStatus(PluginStatus::ACTIVE);

        $this->assertTrue(DummyPluginPage::canAccess());
        $this->assertTrue(DummyPluginPage::shouldRegisterNavigation());
    }

    private function setPluginStatus(PluginStatus $status): void
    {
        $tenant = tenant();

        TenantPlugin::query()->updateOrCreate(
            ['tenant_id' => (string) $tenant->id, 'plugin_id' => 'sample-greeting'],
            ['status' => $status]
        );

        app(PluginManager::class)->resetTenant($tenant);
    }
}
