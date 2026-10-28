<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Core\Plugins\Enums\PluginStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\TenantPlugin;
use App\Plugins\OrderManagement\src\Enums\OrderPosStatus;
use App\Plugins\OrderManagement\src\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PaymentCallbackSecurityTest extends TestCase
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

    public function test_unsigned_paymob_callback_with_success_true_leaves_order_pending(): void
    {
        $order = Order::factory()->create([
            'payment_status' => PaymentStatus::PENDING,
            'status' => OrderStatus::PENDING,
        ]);

        $response = $this->get("/orders/{$order->id}/payment/callback?".http_build_query([
            'id' => '999888',
            'success' => 'true',
            'pending' => 'false',
            'amount_cents' => '10000',
        ]));

        $response->assertRedirect("/orders/{$order->id}/payment/callback?status=pending");

        $fresh = $order->fresh();
        $this->assertSame(PaymentStatus::PENDING, $fresh->payment_status);
        $this->assertSame(OrderStatus::PENDING, $fresh->status);
        $this->assertNotSame(OrderPosStatus::READY, $fresh->pos_status);
    }

    public function test_kashier_callback_with_merchant_order_id_and_no_signature_leaves_it_pending(): void
    {
        $order = Order::factory()->create([
            'merchant_order_id' => 'KASH-ORD-123',
            'payment_status' => PaymentStatus::PENDING,
            'status' => OrderStatus::PENDING,
        ]);

        $response = $this->get("/orders/{$order->id}/payment/callback?".http_build_query([
            'merchantOrderId' => 'KASH-ORD-123',
            'orderReference' => 'REF-999',
            'paymentStatus' => 'SUCCESS',
            'amount' => '100.00',
        ]));

        $response->assertRedirect("/orders/{$order->id}/payment/callback?status=pending");

        $fresh = $order->fresh();
        $this->assertSame(PaymentStatus::PENDING, $fresh->payment_status);
        $this->assertSame(OrderStatus::PENDING, $fresh->status);
        $this->assertNotSame(OrderPosStatus::READY, $fresh->pos_status);
    }

    public function test_success_false_callback_does_not_cancel_order(): void
    {
        $order = Order::factory()->create([
            'payment_status' => PaymentStatus::PENDING,
            'status' => OrderStatus::PENDING,
        ]);

        $response = $this->get("/orders/{$order->id}/payment/callback?".http_build_query([
            'id' => '999888',
            'success' => 'false',
            'pending' => 'false',
            'data_message' => 'Declined by bank',
        ]));

        $response->assertRedirect("/orders/{$order->id}/payment/callback?status=pending");

        $fresh = $order->fresh();
        $this->assertSame(PaymentStatus::PENDING, $fresh->payment_status);
        $this->assertSame(OrderStatus::PENDING, $fresh->status);
    }

    public function test_callback_redirects_to_success_when_order_already_completed(): void
    {
        $order = Order::factory()->create([
            'payment_status' => PaymentStatus::COMPLETED,
            'status' => OrderStatus::PENDING,
        ]);

        $response = $this->get("/orders/{$order->id}/payment/callback");

        $response->assertRedirect("/orders/{$order->id}/payment/callback?status=success");

        $fresh = $order->fresh();
        $this->assertSame(PaymentStatus::COMPLETED, $fresh->payment_status);
    }

    public function test_callback_with_status_query_param_renders_spa_view(): void
    {
        $order = Order::factory()->create();

        $response = $this->get("/orders/{$order->id}/payment/callback?status=success");

        $response->assertOk();
        $response->assertViewIs('app');
    }
}
