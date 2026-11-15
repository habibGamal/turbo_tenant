<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Plugins\Enums\PluginStatus;
use App\Models\TenantPlugin;
use App\Models\User;
use App\Plugins\OrderManagement\src\Enums\OrderStatus;
use App\Plugins\OrderManagement\src\Enums\PaymentStatus;
use App\Plugins\OrderManagement\src\Models\Order;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

final class PaymentProcessingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        TenantPlugin::firstOrCreate([
            'tenant_id' => (string) self::$testTenant->id,
            'plugin_id' => 'payment-processing',
        ], [
            'status' => PluginStatus::ACTIVE,
        ]);
    }

    public function test_payment_status_allowed_for_order_owner_with_envelope_shape(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'payment_status' => PaymentStatus::COMPLETED,
            'status' => OrderStatus::PENDING,
            'total' => 125.50,
        ]);

        $response = $this->getJson("/api/orders/{$order->id}/payment-status");

        $response->assertOk();
        $response->assertJson([
            'status' => 'success',
            'data' => [
                'order' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'payment_status' => 'completed',
                    'total' => 125.50,
                    'currency' => 'EGP',
                ],
                'is_paid' => true,
            ],
        ]);
    }

    public function test_payment_status_forbidden_for_other_user(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser);

        $order = Order::factory()->create([
            'user_id' => $owner->id,
        ]);

        $response = $this->getJson("/api/orders/{$order->id}/payment-status");

        $response->assertStatus(403);
        $response->assertJson([
            'status' => 'error',
            'message' => 'Unauthorized access to order payment status',
        ]);
    }

    public function test_payment_status_allowed_for_guest_with_session(): void
    {
        Auth::logout();
        $order = Order::factory()->create([
            'user_id' => null,
            'payment_status' => PaymentStatus::PENDING,
            'total' => 80.00,
        ]);

        $response = $this->withSession([
            'guest_order_ids' => [$order->id],
        ])->getJson("/api/orders/{$order->id}/payment-status");

        $response->assertOk();
        $response->assertJson([
            'status' => 'success',
            'data' => [
                'order' => [
                    'id' => $order->id,
                ],
                'is_paid' => false,
            ],
        ]);
    }

    public function test_payment_status_forbidden_for_guest_without_session(): void
    {
        Auth::logout();
        $order = Order::factory()->create([
            'user_id' => null,
        ]);

        $response = $this->getJson("/api/orders/{$order->id}/payment-status");

        $response->assertStatus(403);
        $response->assertJson([
            'status' => 'error',
            'message' => 'Unauthorized access to order payment status',
        ]);
    }

    public function test_payment_status_returns_404_for_non_existent_order(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson('/api/orders/999999/payment-status');

        $response->assertStatus(404);
        $response->assertJson([
            'status' => 'error',
            'message' => 'Order not found',
        ]);
    }

    public function test_kashier_config_allowed_for_order_owner_with_envelope_shape(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'total' => 150.00,
        ]);

        $response = $this->getJson("/api/payments/{$order->id}/kashier-config");

        $response->assertOk();
        $response->assertJsonStructure([
            'status',
            'data' => [
                'kashierParams' => [
                    'merchantId',
                    'orderId',
                    'amount',
                    'currency',
                    'hash',
                    'mode',
                    'merchantRedirect',
                    'serverWebhook',
                ],
                'order' => [
                    'id',
                    'order_number',
                    'total',
                ],
            ],
        ]);
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.order.id', $order->id);
    }

    public function test_kashier_config_forbidden_for_other_user(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser);

        $order = Order::factory()->create([
            'user_id' => $owner->id,
        ]);

        $response = $this->getJson("/api/payments/{$order->id}/kashier-config");

        $response->assertStatus(403);
        $response->assertJson([
            'status' => 'error',
            'message' => 'Unauthorized',
        ]);
    }

    public function test_kashier_config_allowed_for_guest_with_session(): void
    {
        Auth::logout();
        $order = Order::factory()->create([
            'user_id' => null,
            'total' => 90.00,
        ]);

        $response = $this->withSession([
            'guest_order_ids' => [$order->id],
        ])->getJson("/api/payments/{$order->id}/kashier-config");

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.order.id', $order->id);
    }

    public function test_kashier_config_forbidden_for_guest_without_session(): void
    {
        Auth::logout();
        $order = Order::factory()->create([
            'user_id' => null,
        ]);

        $response = $this->getJson("/api/payments/{$order->id}/kashier-config");

        $response->assertStatus(403);
        $response->assertJson([
            'status' => 'error',
            'message' => 'Unauthorized',
        ]);
    }

    public function test_kashier_config_returns_envelope_error_on_failure_without_raw_exception(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'total' => 100.00,
        ]);

        Order::saving(function () {
            throw new Exception('Sensitive DB connection details or internal error trace');
        });

        $response = $this->getJson("/api/payments/{$order->id}/kashier-config");

        $response->assertStatus(422);
        $response->assertJson([
            'status' => 'error',
            'message' => 'Failed to initialize payment gateway',
        ]);
        $this->assertStringNotContainsString('Sensitive DB connection', $response->getContent() ?: '');
    }
}
