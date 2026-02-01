<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\BulkNotification;
use App\Models\UserNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Expo\ExpoMessage;

final class BulkUserNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public BulkNotification $bulkNotification
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
        // Store notification in database
        UserNotification::create([
            'user_id' => $notifiable->id,
            'title' => $this->bulkNotification->title,
            'body' => $this->bulkNotification->body,
            'data' => $this->bulkNotification->data,
            'type' => 'bulk',
        ]);

        $message = ExpoMessage::create()
            ->title($this->bulkNotification->title)
            ->body($this->bulkNotification->body)
            ->priority('high')
            ->playSound();

        if ($this->bulkNotification->data) {
            $message->data($this->bulkNotification->data);
        }

        return $message;
    }
}
