<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Enums\OrderStatus;
use App\Models\User;
use App\Plugins\OrderManagement\src\Filament\Resources\Orders\Pages\ListOrders;
use App\Plugins\OrderManagement\src\Http\Middleware\VerifyIntegrationToken;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Models\OrderStatusEvent;
use App\Plugins\OrderManagement\src\Services\IntegrationTokenService;
use App\Plugins\OrderManagement\src\Support\OrderManagementSettings;
use App\Plugins\StoreSettings\src\Services\SettingService;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

final class OrderStatusIntegrationSecurityTest extends TestCase
{
    private SettingService $settingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->settingService = app(SettingService::class);
    }

    public function test_it_rejects_request_with_no_token(): void
    {
        $this->settingService->set(OrderManagementSettings::INTEGRATION_TOKEN_KEY, 'secret-integration-token-123');

        $order = Order::factory()->create([
            'status' => OrderStatus::PENDING,
        ]);

        $response = $this->postJson('/api/order-status', [
            'orderNumber' => $order->order_number,
            'status' => 'processing',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('message', 'Integration token is missing.');
    }

    public function test_it_rejects_request_with_wrong_token(): void
    {
        $this->settingService->set(OrderManagementSettings::INTEGRATION_TOKEN_KEY, 'correct-token-xyz');

        $order = Order::factory()->create([
            'status' => OrderStatus::PENDING,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer wrong-token-xyz')
            ->postJson('/api/order-status', [
                'orderNumber' => $order->order_number,
                'status' => 'processing',
            ]);

        $response->assertStatus(401)
            ->assertJsonPath('message', 'Invalid integration token.');
    }

    public function test_it_rejects_request_when_token_is_not_configured(): void
    {
        $this->settingService->delete(OrderManagementSettings::INTEGRATION_TOKEN_KEY);

        $order = Order::factory()->create([
            'status' => OrderStatus::PENDING,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer any-attempted-token')
            ->postJson('/api/order-status', [
                'orderNumber' => $order->order_number,
                'status' => 'processing',
            ]);

        $response->assertStatus(401)
            ->assertJsonPath('message', 'Integration token is not configured.');
    }

    public function test_it_rejects_customer_session_cookie_without_token(): void
    {
        $this->settingService->set(OrderManagementSettings::INTEGRATION_TOKEN_KEY, 'secret-token-active');

        $customer = User::factory()->create();
        $order = Order::factory()->create([
            'status' => OrderStatus::PENDING,
        ]);

        $response = $this->actingAs($customer)->postJson('/api/order-status', [
            'orderNumber' => $order->order_number,
            'status' => 'processing',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('message', 'Integration token is missing.');
    }

    public function test_it_updates_status_and_records_audit_event_for_allowed_transition(): void
    {
        $token = 'valid-tenant-secret-token-999';
        $this->settingService->set(OrderManagementSettings::INTEGRATION_TOKEN_KEY, $token);

        $order = Order::factory()->create([
            'status' => OrderStatus::PENDING,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/order-status', [
                'orderNumber' => $order->order_number,
                'status' => 'processing',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'تم تحديث حالة الطلب');

        $this->assertSame(OrderStatus::PREPARING, $order->fresh()->status);

        $this->assertDatabaseHas(OrderStatusEvent::class, [
            'order_id' => $order->id,
            'from_status' => OrderStatus::PENDING->value,
            'to_status' => OrderStatus::PREPARING->value,
            'source' => 'integration',
        ]);
    }

    public function test_it_rejects_backwards_transition_with_409_and_makes_no_changes(): void
    {
        $token = 'valid-tenant-secret-token-999';
        $this->settingService->set(OrderManagementSettings::INTEGRATION_TOKEN_KEY, $token);

        $order = Order::factory()->create([
            'status' => OrderStatus::PREPARING,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/order-status', [
                'orderNumber' => $order->order_number,
                'status' => 'pending',
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('from_status', 'processing')
            ->assertJsonPath('to_status', 'pending');

        $this->assertSame(OrderStatus::PREPARING, $order->fresh()->status);

        $this->assertDatabaseMissing(OrderStatusEvent::class, [
            'order_id' => $order->id,
            'to_status' => OrderStatus::PENDING->value,
        ]);
    }

    public function test_it_allows_cancelling_from_pending_or_processing(): void
    {
        $token = 'valid-tenant-secret-token-999';
        $this->settingService->set(OrderManagementSettings::INTEGRATION_TOKEN_KEY, $token);

        $order1 = Order::factory()->create(['status' => OrderStatus::PENDING]);
        $response1 = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/order-status', [
                'orderNumber' => $order1->order_number,
                'status' => 'cancelled',
            ]);
        $response1->assertOk();
        $this->assertSame(OrderStatus::CANCELLED, $order1->fresh()->status);

        $order2 = Order::factory()->create(['status' => OrderStatus::PREPARING]);
        $response2 = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/order-status', [
                'orderNumber' => $order2->order_number,
                'status' => 'cancelled',
            ]);
        $response2->assertOk();
        $this->assertSame(OrderStatus::CANCELLED, $order2->fresh()->status);
    }

    public function test_it_rejects_cancelling_from_out_for_delivery_or_delivered(): void
    {
        $token = 'valid-tenant-secret-token-999';
        $this->settingService->set(OrderManagementSettings::INTEGRATION_TOKEN_KEY, $token);

        $order = Order::factory()->create(['status' => OrderStatus::OUT_FOR_DELIVERY]);
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/order-status', [
                'orderNumber' => $order->order_number,
                'status' => 'cancelled',
            ]);
        $response->assertStatus(409);
        $this->assertSame(OrderStatus::OUT_FOR_DELIVERY, $order->fresh()->status);

        $completedOrder = Order::factory()->create(['status' => OrderStatus::DELIVERED]);
        $responseCompleted = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/order-status', [
                'orderNumber' => $completedOrder->order_number,
                'status' => 'cancelled',
            ]);
        $responseCompleted->assertStatus(409);
        $this->assertSame(OrderStatus::DELIVERED, $completedOrder->fresh()->status);
    }

    public function test_it_generates_new_integration_token_via_header_action_and_invalidates_old_token(): void
    {
        $tokenService = app(IntegrationTokenService::class);
        $oldToken = $tokenService->generate();

        $order = Order::factory()->create(['status' => OrderStatus::PENDING]);

        // Old token works initially
        $response = $this->withHeader('Authorization', "Bearer {$oldToken}")
            ->postJson('/api/order-status', [
                'orderNumber' => $order->order_number,
                'status' => 'processing',
            ]);
        $response->assertOk();

        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);

        // Header action on ListOrders generates a new token
        $component = Livewire::test(ListOrders::class);
        $component->assertOk()
            ->assertActionExists('integration_token')
            ->callAction('integration_token')
            ->assertNotified('تم توليد رمز التكامل بنجاح');

        $newToken = $tokenService->get();
        $this->assertNotEmpty($newToken);
        $this->assertNotSame($oldToken, $newToken);
        $this->assertSame(48, mb_strlen($newToken));
        $this->assertStringContainsString('****', (string) $tokenService->masked());

        // Old token stops working (401 Unauthorized)
        $this->withHeader('Authorization', "Bearer {$oldToken}")
            ->postJson('/api/order-status', [
                'orderNumber' => $order->order_number,
                'status' => 'cancelled',
            ])
            ->assertStatus(401)
            ->assertJsonPath('message', 'Invalid integration token.');

        // New token works
        $this->withHeader('Authorization', "Bearer {$newToken}")
            ->postJson('/api/order-status', [
                'orderNumber' => $order->order_number,
                'status' => 'cancelled',
            ])
            ->assertOk();
    }

    public function test_it_has_throttle_and_token_middleware_on_order_status_route(): void
    {
        $route = collect(Route::getRoutes())->first(fn ($r) => $r->getName() === 'api.order-status.update');
        $this->assertNotNull($route);
        $middleware = $route->gatherMiddleware();
        $this->assertContains(VerifyIntegrationToken::class, $middleware);
        $this->assertContains('throttle:60,1', $middleware);
    }
}
