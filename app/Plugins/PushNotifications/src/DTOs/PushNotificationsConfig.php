<?php

declare(strict_types=1);

namespace App\Plugins\PushNotifications\src\DTOs;

final readonly class PushNotificationsConfig
{
    public function __construct(
        public bool $enabled = true
        // Add your typed configuration properties here
    ) {}
}
