<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Order;

use App\Plugins\OrderManagement\src\Contracts\OrderPaymentRecorder;
use App\Plugins\OrderManagement\src\Enums\PaymentStatus;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Services\EloquentOrderPaymentRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentOrderPaymentRecorderTest extends TestCase
{
    use RefreshDatabase;

    private OrderPaymentRecorder $recorder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->recorder = new EloquentOrderPaymentRecorder();
    }

    public function test_implements_order_payment_recorder_contract(): void
    {
        $this->assertInstanceOf(OrderPaymentRecorder::class, $this->recorder);
    }

    public function test_records_payment_attributes_on_order(): void
    {
        $order = Order::factory()->create([
            'payment_status' => PaymentStatus::PENDING,
            'transaction_id' => null,
        ]);

        $this->recorder->record($order, [
            'payment_status' => PaymentStatus::COMPLETED->value,
            'transaction_id' => 'TXN-123456',
        ]);

        $fresh = $order->fresh();
        $this->assertSame(PaymentStatus::COMPLETED, $fresh->payment_status);
        $this->assertSame('TXN-123456', $fresh->transaction_id);
    }
}
