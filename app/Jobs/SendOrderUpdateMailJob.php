<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\OrderUpdateMail;
use App\Models\Order;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

final class SendOrderUpdateMailJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public Order $order)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            if ($this->order->user) {
                Mail::to($this->order->user)->send(new OrderUpdateMail($this->order));
            }

        } catch (Exception $exception) {
            logger()->error($exception->getMessage());
        }
    }
}
