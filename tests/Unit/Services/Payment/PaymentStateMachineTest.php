<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Payment;

use App\Plugins\OrderManagement\src\Contracts\OrderPaymentRecorder;
use App\Plugins\OrderManagement\src\Enums\PaymentStatus;
use App\Plugins\OrderManagement\src\Events\OrderPaymentCompleted;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\PaymentProcessing\src\Services\PaymentStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

final class PaymentStateMachineTest extends TestCase
{
    use RefreshDatabase;

    public function test_transition_uses_injected_recorder_and_dispatches_order_payment_completed_on_completed(): void
    {
        Event::fake([OrderPaymentCompleted::class]);

        $order = Order::factory()->create([
            'payment_status' => PaymentStatus::PENDING,
        ]);

        $mockRecorder = $this->createMock(OrderPaymentRecorder::class);
        $mockRecorder->expects($this->once())
            ->method('record')
            ->with(
                $this->identicalTo($order),
                $this->callback(function (array $attributes): bool {
                    return $attributes['payment_status'] === PaymentStatus::COMPLETED->value
                        && ! array_key_exists('pos_status', $attributes)
                        && $attributes['transaction_id'] === 'TXN-999';
                })
            );

        $stateMachine = new PaymentStateMachine($mockRecorder);

        $result = $stateMachine->transition(
            $order,
            PaymentStatus::COMPLETED,
            ['transaction_id' => 'TXN-999']
        );

        $this->assertTrue($result);
        Event::assertDispatched(OrderPaymentCompleted::class, function (OrderPaymentCompleted $event) use ($order): bool {
            return $event->order->id === $order->id;
        });
    }

    public function test_non_completed_transition_does_not_dispatch_order_payment_completed(): void
    {
        Event::fake([OrderPaymentCompleted::class]);

        $order = Order::factory()->create([
            'payment_status' => PaymentStatus::PENDING,
        ]);

        $mockRecorder = $this->createMock(OrderPaymentRecorder::class);
        $mockRecorder->expects($this->once())
            ->method('record')
            ->with(
                $this->identicalTo($order),
                $this->callback(function (array $attributes): bool {
                    return $attributes['payment_status'] === PaymentStatus::PROCESSING->value;
                })
            );

        $stateMachine = new PaymentStateMachine($mockRecorder);

        $result = $stateMachine->transition($order, PaymentStatus::PROCESSING);

        $this->assertTrue($result);
        Event::assertNotDispatched(OrderPaymentCompleted::class);
    }

    public function test_disallowed_transition_returns_false_and_does_not_call_recorder(): void
    {
        $order = Order::factory()->create([
            'payment_status' => PaymentStatus::REFUNDED,
        ]);

        $mockRecorder = $this->createMock(OrderPaymentRecorder::class);
        $mockRecorder->expects($this->never())
            ->method('record');

        $stateMachine = new PaymentStateMachine($mockRecorder);

        $result = $stateMachine->transition(
            $order,
            PaymentStatus::COMPLETED
        );

        $this->assertFalse($result);
    }
}
