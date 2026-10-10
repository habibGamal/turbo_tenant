<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Order;

use App\DTOs\Order\PlaceOrderData;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Interfaces\PaymentGatewayInterface;
use App\Models\Order;
use App\Models\User;
use App\Services\Order\Contracts\OrderRepositoryInterface;
use App\Services\Order\Payments\OrderPaymentDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class OrderPaymentDispatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_payment_successfully(): void
    {
        $order = Order::factory()->create([
            'payment_status' => PaymentStatus::PENDING,
        ]);
        $user = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $data = new PlaceOrderData(
            user: $user,
            branchId: 1,
            paymentMethod: PaymentMethod::CARD
        );

        $mockGateway = Mockery::mock(PaymentGatewayInterface::class);
        $mockGateway->shouldReceive('getGatewayId')->andReturn('paymob');
        $mockGateway->shouldReceive('createPaymentIntention')
            ->once()
            ->andReturn([
                'success' => true,
                'checkout_url' => 'https://accept.paymob.com/checkout/999',
                'data' => ['id' => 999],
            ]);

        $dispatcher = new OrderPaymentDispatcher(
            paymentGateway: $mockGateway,
            orderRepository: app(OrderRepositoryInterface::class)
        );

        $result = $dispatcher->dispatchPayment($order, $data);

        $this->assertTrue($result->isSuccessful());
        $this->assertTrue($result->requiresPayment());
        $this->assertSame('https://accept.paymob.com/checkout/999', $result->checkoutUrl);
        $this->assertSame(['id' => 999], $result->paymentData);
    }

    public function test_dispatches_payment_failure_cancels_order(): void
    {
        $order = Order::factory()->create([
            'payment_status' => PaymentStatus::PENDING,
        ]);
        $user = User::factory()->create();

        $data = new PlaceOrderData(
            user: $user,
            branchId: 1,
            paymentMethod: PaymentMethod::CARD
        );

        $mockGateway = Mockery::mock(PaymentGatewayInterface::class);
        $mockGateway->shouldReceive('getGatewayId')->andReturn('paymob');
        $mockGateway->shouldReceive('createPaymentIntention')
            ->once()
            ->andReturn([
                'success' => false,
                'error' => 'Insufficient funds',
            ]);

        $dispatcher = new OrderPaymentDispatcher(
            paymentGateway: $mockGateway,
            orderRepository: app(OrderRepositoryInterface::class)
        );

        $result = $dispatcher->dispatchPayment($order, $data);

        $this->assertFalse($result->isSuccessful());
        $this->assertSame('Insufficient funds', $result->errorMessage);

        $fresh = $order->fresh();
        $this->assertSame('cancelled', $fresh->status->value ?? $fresh->status);
        $this->assertSame(PaymentStatus::FAILED, $fresh->payment_status);
    }
}
