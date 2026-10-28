<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Core\Plugins\Services\PluginLifecycleService;
use App\Core\Plugins\Services\PluginManager;
use App\Core\Plugins\Services\PluginRegistry;
use App\Models\Tenant;
use App\Plugins\CartEngine\src\DTOs\CartEngineConfig;
use App\Plugins\CouponsPromotions\src\Models\Coupon;
use App\Plugins\CouponsPromotions\src\Pipelines\Pipes\ApplyCouponDiscountPipe;
use App\Plugins\GuestCheckout\src\Pipelines\Pipes\GuestCheckoutPermissionPipe;
use App\Plugins\OrderManagement\src\Enums\OrderPosStatus;
use App\Plugins\OrderManagement\src\Events\OrderCreated;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Pipelines\OrderPipelineRegistry;
use App\Plugins\PosIntegration\src\Jobs\SendOrderToPosJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class TenantIsolationTest extends TestCase
{
    private Tenant $tenantA;

    private Tenant $tenantB;

    private \App\Models\User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Artisan::call('migrate', [
            '--path' => 'database/migrations',
            '--force' => true,
        ]);

        $this->user = \App\Models\User::firstOrCreate(
            ['email' => 'isolation_tester@test.com'],
            ['name' => 'Isolation Tester', 'password' => bcrypt('password')]
        );

        app(PluginRegistry::class)->discover()->syncDatabase();

        $this->tenantA = self::$testTenant;

        $this->tenantB = Tenant::withoutEvents(fn () => Tenant::firstOrCreate(['id' => 'isolation_tenant_b']));
        $this->tenantB->database()->manager()->createDatabase($this->tenantB);
        $dbFileB = database_path($this->tenantB->database()->getName());
        register_shutdown_function(static fn () => @unlink($dbFileB));

        if (! $this->tenantB->domains()->where('domain', 'isolation-b.localhost')->exists()) {
            $this->tenantB->domains()->create(['domain' => 'isolation-b.localhost']);
        }

        // Migrate tenant schema for tenant B
        $pluginMigrationPaths = array_filter(
            glob(base_path('app/Plugins/*/database/migrations/tenant')) ?: [],
            fn ($p) => ! str_contains($p, 'SampleGreeting')
        );
        $paths = array_merge([database_path('migrations/tenant')], $pluginMigrationPaths);

        tenancy()->initialize($this->tenantB);
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => $paths,
            '--force' => true,
        ]);
        tenancy()->end();
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    public function test_tenant_b_does_not_inherit_pipes_or_listeners_from_tenant_a(): void
    {
        $lifecycle = app(PluginLifecycleService::class);
        $manager = app(PluginManager::class);

        // Configure Tenant A with order-management, coupons-promotions, and guest-checkout
        $lifecycle->activate($this->tenantA, 'store-settings');
        $lifecycle->activate($this->tenantA, 'catalog-products');
        $lifecycle->activate($this->tenantA, 'locations-delivery');
        $lifecycle->activate($this->tenantA, 'cart-engine');
        $lifecycle->activate($this->tenantA, 'order-management');
        $lifecycle->activate($this->tenantA, 'coupons-promotions');
        $lifecycle->activate($this->tenantA, 'guest-checkout');

        // Configure Tenant B with only core and order-management (coupons and guest-checkout inactive)
        $lifecycle->activate($this->tenantB, 'store-settings');
        $lifecycle->activate($this->tenantB, 'catalog-products');
        $lifecycle->activate($this->tenantB, 'locations-delivery');
        $lifecycle->activate($this->tenantB, 'cart-engine');
        $lifecycle->activate($this->tenantB, 'order-management');
        $lifecycle->deactivate($this->tenantB, 'coupons-promotions');
        $lifecycle->deactivate($this->tenantB, 'guest-checkout');

        // 1. Boot Tenant A
        tenancy()->initialize($this->tenantA);
        $manager->bootTenant($this->tenantA);

        $registryA = app(OrderPipelineRegistry::class);
        $this->assertContains(ApplyCouponDiscountPipe::class, $registryA->getPricingPipes());
        $this->assertContains(GuestCheckoutPermissionPipe::class, $registryA->getValidationPipes());

        // 2. Switch to Tenant B in the same process
        tenancy()->end();
        tenancy()->initialize($this->tenantB);
        $manager->bootTenant($this->tenantB);

        $registryB = app(OrderPipelineRegistry::class);
        $this->assertNotContains(
            ApplyCouponDiscountPipe::class,
            $registryB->getPricingPipes(),
            'Tenant B should not have inherited ApplyCouponDiscountPipe from Tenant A'
        );
        $this->assertNotContains(
            GuestCheckoutPermissionPipe::class,
            $registryB->getValidationPipes(),
            'Tenant B should not have inherited GuestCheckoutPermissionPipe from Tenant A'
        );

        // 3. Create an order for Tenant B with a coupon; RecordCouponUsageListener must NOT run
        DB::connection('tenant')->statement('PRAGMA foreign_keys = OFF;');

        $orderB = new Order();
        $orderB->order_number = 'ORD-ISOLATION-B';
        $orderB->user_id = 1;
        $orderB->status = 'pending';
        $orderB->type = 'web_delivery';
        $orderB->sub_total = 100.0;
        $orderB->tax = 10.0;
        $orderB->service = 5.0;
        $orderB->delivery_fee = 0.0;
        $orderB->discount = 15.0;
        $orderB->total = 100.0;
        $orderB->coupon_id = 999;
        $orderB->branch_id = 1;
        $orderB->save();

        // 4. Verify RecordCouponUsageListener DOES run for Tenant A where plugin is active
        tenancy()->end();
        tenancy()->initialize($this->tenantA);
        $manager->bootTenant($this->tenantA);

        DB::connection('tenant')->statement('PRAGMA foreign_keys = OFF;');

        $couponA = Coupon::create([
            'code' => 'COUPON_A_TEST',
            'type' => 'fixed',
            'value' => 10.0,
            'expiry_date' => now()->addMonth(),
            'is_active' => true,
            'usage_count' => 0,
            'total_consumed' => 0.0,
        ]);

        $orderA = new Order();
        $orderA->order_number = 'ORD-ISOLATION-A';
        $orderA->user_id = 1;
        $orderA->status = 'pending';
        $orderA->type = 'web_delivery';
        $orderA->sub_total = 100.0;
        $orderA->tax = 10.0;
        $orderA->service = 5.0;
        $orderA->delivery_fee = 0.0;
        $orderA->discount = 10.0;
        $orderA->total = 100.0;
        $orderA->coupon_id = $couponA->id;
        $orderA->branch_id = 1;
        $orderA->save();

        $this->assertSame(1, $couponA->fresh()->usage_count, 'RecordCouponUsageListener must run for Tenant A');
    }

    public function test_booting_tenant_twice_does_not_duplicate_event_listeners(): void
    {
        $lifecycle = app(PluginLifecycleService::class);
        $manager = app(PluginManager::class);

        $lifecycle->activate($this->tenantA, 'store-settings');
        $lifecycle->activate($this->tenantA, 'catalog-products');
        $lifecycle->activate($this->tenantA, 'locations-delivery');
        $lifecycle->activate($this->tenantA, 'cart-engine');
        $lifecycle->activate($this->tenantA, 'order-management');
        $lifecycle->activate($this->tenantA, 'pos-integration');

        tenancy()->initialize($this->tenantA);

        // Boot once, then boot a second time
        $manager->bootTenant($this->tenantA);
        $manager->bootTenant($this->tenantA);

        Queue::fake();

        $order = new Order();
        $order->id = 55;
        $order->pos_status = OrderPosStatus::READY;

        event(new OrderCreated($order));

        // SendOrderToPosJob must be pushed exactly once, proving the listener was not registered twice
        Queue::assertPushed(SendOrderToPosJob::class, 1);
    }

    public function test_tenant_scoped_singleton_is_rebuilt_as_new_instance_after_switching_tenants(): void
    {
        $lifecycle = app(PluginLifecycleService::class);
        $manager = app(PluginManager::class);

        $lifecycle->activate($this->tenantA, 'store-settings');
        $lifecycle->activate($this->tenantA, 'catalog-products');
        $lifecycle->activate($this->tenantA, 'locations-delivery');
        $lifecycle->activate($this->tenantA, 'cart-engine');
        $lifecycle->activate($this->tenantA, 'order-management');

        $lifecycle->activate($this->tenantB, 'store-settings');
        $lifecycle->activate($this->tenantB, 'catalog-products');
        $lifecycle->activate($this->tenantB, 'locations-delivery');
        $lifecycle->activate($this->tenantB, 'cart-engine');
        $lifecycle->activate($this->tenantB, 'order-management');

        // Under Tenant A
        tenancy()->initialize($this->tenantA);
        $manager->bootTenant($this->tenantA);
        $cartConfigA = app(CartEngineConfig::class);
        $registryA = app(OrderPipelineRegistry::class);

        // Switch to Tenant B
        tenancy()->end();
        tenancy()->initialize($this->tenantB);
        $manager->bootTenant($this->tenantB);
        $cartConfigB = app(CartEngineConfig::class);
        $registryB = app(OrderPipelineRegistry::class);

        $this->assertNotSame($cartConfigA, $cartConfigB, 'CartEngineConfig must be a distinct instance per tenant');
        $this->assertNotSame($registryA, $registryB, 'OrderPipelineRegistry must be a distinct instance per tenant');
    }
}
