<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Order;
use App\Notifications\OrderStatusNotification;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class SendOrderStatusNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Order $order
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            if ($this->order->user && $this->order->user->expo_token) {
                $this->order->user->notify(new OrderStatusNotification($this->order));
            }
        } catch (Exception $exception) {
            logger()->error('Failed to send order status notification', [
                'order_id' => $this->order->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
