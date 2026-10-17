<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Jobs;

use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\PosIntegration\src\Enums\OrderPosStatus;
use App\Plugins\PosIntegration\src\Services\OrderPOSService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendOrderToPosJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 3;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 5;

    public function __construct(
        public Order $order
    ) {}

    public function handle(OrderPOSService $orderPOSService): void
    {
        try {
            $this->order->update([
                'pos_status' => OrderPosStatus::SENDING,
            ]);

            if (! $orderPOSService->canAcceptOrder($this->order->branch)) {
                $this->release(3);

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

            throw $e;
        }
    }
}
