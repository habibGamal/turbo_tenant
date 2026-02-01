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

final class SendOrderToPosJob implements ShouldQueue
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
                throw new Exception('POS system is not accepting orders at this time.');
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

            // Re-throw to allow job retry mechanism
            throw $e;
        }
    }
}
