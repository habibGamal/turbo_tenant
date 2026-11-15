<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\PosIntegration\src\Services\OrderPOSService;
use Mockery;
use Tests\TestCase;

final class CreateTestOrderCommandTest extends TestCase
{
    public function test_runs_create_test_order_command_and_creates_complete_test_order_with_pos_call(): void
    {
        /** @var Mockery\MockInterface&OrderPOSService $posService */
        $posService = Mockery::mock(OrderPOSService::class);
        /** @var Mockery\Expectation $expectation */
        $expectation = $posService->shouldReceive('placeOrder');
        $expectation->once()
            ->with(Mockery::type(Order::class))
            ->andReturn(['success' => true]);

        $this->app->instance(OrderPOSService::class, $posService);

        $this->artisan('app:create-test-order')
            ->assertSuccessful();

        $this->assertTrue(Branch::query()->where('name', 'Test Branch')->exists());
        $this->assertSame(1, Order::query()->count());

        $order = Order::query()->first();
        $this->assertNotNull($order);
        $this->assertSame(3, $order->items()->count());
        $this->assertStringStartsWith('ORD-', $order->order_number);
        $this->assertGreaterThan(0.0, (float) $order->total);
    }
}
