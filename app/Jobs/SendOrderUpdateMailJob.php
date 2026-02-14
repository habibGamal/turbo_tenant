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
            // Send email to authenticated user
            if ($this->order->user) {
                Mail::to($this->order->user)->send(new OrderUpdateMail($this->order));
            }
            // Send email to guest user if they have an email
            elseif ($this->order->guestUser && $this->order->guestUser->email) {
                Mail::to($this->order->guestUser->email)->send(new OrderUpdateMail($this->order));
            }

        } catch (Exception $exception) {
            logger()->error('Failed to send order update email', [
                'order_id' => $this->order->id,
                'user_id' => $this->order->user_id,
                'guest_user_id' => $this->order->guest_user_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
