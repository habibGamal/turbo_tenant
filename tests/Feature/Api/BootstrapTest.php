<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Core\Plugins\Services\PluginManager;
use App\Models\TenantPlugin;
use App\Plugins\CartEngine\src\Services\Contracts\CartServiceInterface;
use Tests\TestCase;

final class BootstrapTest extends TestCase
{
    public function test_tenant_bootstrap_returns_expected_json_structure(): void
    {
        $response = $this->getJson('/api/tenant/bootstrap');

        $response->assertOk()
            ->assertJsonStructure([
                'tenant' => ['id', 'name', 'theme'],
                'user',
                'settings',
                'active_plugins',
                'cart_count',
            ]);
    }

    public function test_tenant_without_cart_engine_active_gets_cart_count_zero(): void
    {
        $tenant = tenant();

        TenantPlugin::query()
            ->where('tenant_id', (string) $tenant->id)
            ->where('plugin_id', 'cart-engine')
            ->delete();

        app(PluginManager::class)->resetTenant($tenant);

        $mock = $this->mock(CartServiceInterface::class);
        $mock->shouldNotReceive('getCartCount');

        $response = $this->getJson('/api/tenant/bootstrap');

        $response->assertOk()
            ->assertJsonPath('cart_count', 0);
    }

    public function test_tenant_bootstrap_returns_tenant_name_when_set(): void
    {
        $tenant = tenant();
        $tenant->name = 'Pizza Palace';
        $tenant->save();

        $response = $this->getJson('/api/tenant/bootstrap');

        $response->assertOk()
            ->assertJsonPath('tenant.name', 'Pizza Palace')
            ->assertJsonPath('tenant.id', (string) $tenant->id);
    }
}
