<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Expo\ExpoMessage;

final class OrderStatusNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public Order $order
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['expo'];
    }

    /**
     * Get the expo representation of the notification.
     */
    public function toExpo(object $notifiable): ExpoMessage
    {
        $title = $this->getTitle();
        $body = $this->getBody();
        $data = [
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'status' => $this->order->status->value,
        ];

        // Store notification in database
        \App\Models\UserNotification::create([
            'user_id' => $notifiable->id,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'type' => 'order',
        ]);

        return ExpoMessage::create()
            ->title($title)
            ->body($body)
            ->data($data)
            ->priority('high')
            ->playSound();
    }

    /**
     * Get the notification title based on order status.
     */
    protected function getTitle(): string
    {
        return match ($this->order->status) {
            \App\Enums\OrderStatus::PENDING => 'تم استلام طلبك',
            \App\Enums\OrderStatus::PREPARING => 'جاري تحضير طلبك',
            \App\Enums\OrderStatus::OUT_FOR_DELIVERY => 'طلبك في الطريق',
            \App\Enums\OrderStatus::DELIVERED => 'تم توصيل طلبك',
            \App\Enums\OrderStatus::CANCELLED => 'تم إلغاء طلبك',
        };
    }

    /**
     * Get the notification body based on order status.
     */
    protected function getBody(): string
    {
        return match ($this->order->status) {
            \App\Enums\OrderStatus::PENDING => "طلب رقم {$this->order->order_number} قيد الانتظار",
            \App\Enums\OrderStatus::PREPARING => "جاري تحضير طلبك رقم {$this->order->order_number}",
            \App\Enums\OrderStatus::OUT_FOR_DELIVERY => "طلبك رقم {$this->order->order_number} في الطريق إليك",
            \App\Enums\OrderStatus::DELIVERED => "تم توصيل طلبك رقم {$this->order->order_number} بنجاح",
            \App\Enums\OrderStatus::CANCELLED => "تم إلغاء طلبك رقم {$this->order->order_number}",
        };
    }
}
