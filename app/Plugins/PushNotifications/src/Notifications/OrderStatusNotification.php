<?php

declare(strict_types=1);

namespace App\Plugins\PushNotifications\src\Notifications;

use App\Enums\OrderStatus;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\PushNotifications\src\Models\UserNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Expo\ExpoMessage;

class OrderStatusNotification extends Notification implements ShouldQueue
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
            'status' => $this->order->status instanceof OrderStatus ? $this->order->status->value : (string) $this->order->status,
        ];

        // Store notification in database
        UserNotification::create([
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
        $status = $this->order->status instanceof OrderStatus ? $this->order->status : OrderStatus::tryFrom((string) $this->order->status);

        return match ($status) {
            OrderStatus::PENDING => 'تم استلام طلبك',
            OrderStatus::PREPARING => 'جاري تحضير طلبك',
            OrderStatus::OUT_FOR_DELIVERY => 'طلبك في الطريق',
            OrderStatus::DELIVERED => 'تم توصيل طلبك',
            OrderStatus::CANCELLED => 'تم إلغاء طلبك',
            default => 'تحديث حالة الطلب',
        };
    }

    /**
     * Get the notification body based on order status.
     */
    protected function getBody(): string
    {
        $status = $this->order->status instanceof OrderStatus ? $this->order->status : OrderStatus::tryFrom((string) $this->order->status);

        return match ($status) {
            OrderStatus::PENDING => "طلب رقم {$this->order->order_number} قيد الانتظار",
            OrderStatus::PREPARING => "جاري تحضير طلبك رقم {$this->order->order_number}",
            OrderStatus::OUT_FOR_DELIVERY => "طلبك رقم {$this->order->order_number} في الطريق إليك",
            OrderStatus::DELIVERED => "تم توصيل طلبك رقم {$this->order->order_number} بنجاح",
            OrderStatus::CANCELLED => "تم إلغاء طلبك رقم {$this->order->order_number}",
            default => "تم تحديث حالة طلبك رقم {$this->order->order_number}",
        };
    }
}
