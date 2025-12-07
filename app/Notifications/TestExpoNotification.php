<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

use NotificationChannels\Expo\ExpoMessage;

class TestExpoNotification extends Notification
{
    use Queueable;
    

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['expo'];
    }

    public function toExpo($notifiable): ExpoMessage
    {
        return ExpoMessage::create()
            ->title('Test Notification')
            ->body('This is a test notification from Turbo Tenant.')
            ->priority('high')
            ->playSound();
    }
}
