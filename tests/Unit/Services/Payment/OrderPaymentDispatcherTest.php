<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Payment;

use App\Models\User;
use App\Plugins\LocationsDelivery\src\Contracts\AddressBook;
use App\Plugins\OrderManagement\src\Contracts\PaymentInitiator;
use App\Plugins\OrderManagement\src\Contracts\PaymentIntention;
use App\Plugins\OrderManagement\src\DTOs\PlaceOrderData;
use App\Plugins\OrderManagement\src\Enums\PaymentMethod;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Services\Contracts\OrderRepositoryInterface;
use App\Plugins\OrderManagement\src\Services\Payments\OrderPaymentDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class OrderPaymentDispatcherTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatcher_accepts_payment_initiator_and_dispatches_successfully(): void
    {
        $mockInitiator = Mockery::mock(PaymentInitiator::class);
        $mockInitiator->shouldReceive('getGatewayId')->once()->andReturn('custom_gateway');
        $mockInitiator->shouldReceive('createPaymentIntention')
            ->once()
            ->andReturn(PaymentIntention::created(
                checkoutUrl: 'https://checkout.example.com',
                data: ['token' => 'tok_123'],
            ));

        $mockRepo = Mockery::mock(OrderRepositoryInterface::class);
        $mockAddressBook = Mockery::mock(AddressBook::class);

        $dispatcher = new OrderPaymentDispatcher($mockInitiator, $mockRepo, $mockAddressBook);

        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        $data = new PlaceOrderData(
            user: $user,
            branchId: 1,
            paymentMethod: PaymentMethod::CARD,
            type: 'web_takeaway',
            billingData: [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john@example.com',
                'phone' => '+201000000000',
            ]
        );

        $result = $dispatcher->dispatchPayment($order, $data);

        $this->assertTrue($result->isSuccessful());
        $this->assertTrue($result->requiresPayment());
        $this->assertSame('https://checkout.example.com', $result->checkoutUrl);
        $this->assertSame(['token' => 'tok_123'], $result->paymentData);
    }

    public function test_dispatcher_marks_order_failed_when_initiator_fails(): void
    {
        $mockInitiator = Mockery::mock(PaymentInitiator::class);
        $mockInitiator->shouldReceive('getGatewayId')->once()->andReturn('custom_gateway');
        $mockInitiator->shouldReceive('createPaymentIntention')
            ->once()
            ->andReturn(PaymentIntention::failed('Payment refused'));

        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id]);

        $mockRepo = Mockery::mock(OrderRepositoryInterface::class);
        $mockRepo->shouldReceive('markOrderAsPaymentFailed')->once()->with($order);
        $mockAddressBook = Mockery::mock(AddressBook::class);

        $dispatcher = new OrderPaymentDispatcher($mockInitiator, $mockRepo, $mockAddressBook);

        $data = new PlaceOrderData(
            user: $user,
            branchId: 1,
            paymentMethod: PaymentMethod::CARD,
            type: 'web_takeaway',
            billingData: []
        );

        $result = $dispatcher->dispatchPayment($order, $data);

        $this->assertFalse($result->isSuccessful());
        $this->assertSame('Payment refused', $result->errorMessage);
    }
}
