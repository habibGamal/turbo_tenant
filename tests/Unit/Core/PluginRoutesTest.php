<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Plugins\Routing\PluginRoutes;
use Illuminate\Routing\RouteRegistrar;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Tests\TestCase;

final class PluginRoutesTest extends TestCase
{
    public function test_tenant_applies_the_four_middleware_in_order(): void
    {
        $registrar = PluginRoutes::tenant('cart-engine');

        $this->assertInstanceOf(RouteRegistrar::class, $registrar);

        PluginRoutes::tenant('cart-engine')->group(function (): void {
            Route::get('/plugin-routes-test/tenant-gated', static fn (): string => 'ok')
                ->name('plugin.routes.test.gated');
        });

        Route::getRoutes()->refreshNameLookups();
        $route = Route::getRoutes()->getByName('plugin.routes.test.gated');
        $this->assertNotNull($route);

        $expected = [
            'web',
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
            'plugin.active:cart-engine',
        ];

        $this->assertSame($expected, $route->gatherMiddleware());
    }

    public function test_tenant_ungated_applies_the_first_three_middleware_in_order(): void
    {
        $registrar = PluginRoutes::tenantUngated();

        $this->assertInstanceOf(RouteRegistrar::class, $registrar);

        PluginRoutes::tenantUngated()->group(function (): void {
            Route::post('/plugin-routes-test/tenant-ungated', static fn (): string => 'ok')
                ->name('plugin.routes.test.ungated');
        });

        Route::getRoutes()->refreshNameLookups();
        $route = Route::getRoutes()->getByName('plugin.routes.test.ungated');
        $this->assertNotNull($route);

        $expected = [
            'web',
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
        ];

        $this->assertSame($expected, $route->gatherMiddleware());
    }

    public function test_callers_can_chain_prefix_name_and_group(): void
    {
        PluginRoutes::tenant('sample-plugin')
            ->prefix('api/sample')
            ->name('api.sample.')
            ->group(function (): void {
                Route::get('/endpoint', static fn (): string => 'ok')
                    ->name('endpoint');

                Route::middleware('auth')->group(function (): void {
                    Route::get('/protected', static fn (): string => 'ok')
                        ->name('protected');
                });
            });

        Route::getRoutes()->refreshNameLookups();

        $endpoint = Route::getRoutes()->getByName('api.sample.endpoint');
        $this->assertNotNull($endpoint);
        $this->assertSame('api/sample/endpoint', $endpoint->uri());
        $this->assertSame([
            'web',
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
            'plugin.active:sample-plugin',
        ], $endpoint->gatherMiddleware());

        $protected = Route::getRoutes()->getByName('api.sample.protected');
        $this->assertNotNull($protected);
        $this->assertSame('api/sample/protected', $protected->uri());
        $this->assertSame([
            'web',
            InitializeTenancyByDomain::class,
            PreventAccessFromCentralDomains::class,
            'plugin.active:sample-plugin',
            'auth',
        ], $protected->gatherMiddleware());
    }
}
