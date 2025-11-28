<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\OrderPosStatus;
use App\Models\Order;
use App\Services\OrderPOSService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendOrderToPosJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Order $order
    ) {
    }

    public function handle(OrderPOSService $orderPOSService): void
    {
        try {
            $this->order->update([
                'pos_status' => OrderPosStatus::SENDING,
            ]);

            if (!$orderPOSService->canAcceptOrder($this->order->branch)) {
                $this->release(900); // 15 minutes

                return;
            }

            $orderPOSService->placeOrder($this->order);

            $this->order->update([
                'pos_status' => OrderPosStatus::SENT,
                'pos_failure_reason' => null,
            ]);
        } catch (Exception $e) {
            $this->order->update([
                'pos_status' => OrderPosStatus::FAILED,
                'pos_failure_reason' => $e->getMessage(),
            ]);

            // Re-throw exception to fail the job if needed, or just log it.
            // For now, we just track the status as failed.
            // throw $e; 
        }
    }
}
