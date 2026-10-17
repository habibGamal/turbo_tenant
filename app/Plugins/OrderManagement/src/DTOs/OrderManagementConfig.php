<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\DTOs;

use App\Enums\SettingKey;
use App\Plugins\StoreSettings\src\Services\SettingService;

final readonly class OrderManagementConfig
{
    public function __construct(
        private ?SettingService $settingService = null
    ) {}

    public function getTaxRate(): float
    {
        return (float) config('app.tax_rate', 0.0);
    }

    public function getServiceRate(): float
    {
        return (float) config('app.service_rate', 0.0);
    }

    public function acceptOrdersAfterWorkTimes(): bool
    {
        if (! $this->settingService) {
            return true;
        }

        return filter_var(
            $this->settingService->get(SettingKey::ACCEPT_ORDERS_AFTER_WORK_TIMES, 'true'),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public function isStoreOpen(): bool
    {
        if (! $this->settingService) {
            return true;
        }

        $workTimesJson = $this->settingService->get(SettingKey::WORK_TIMES, '[]');
        $workTimes = is_string($workTimesJson) ? json_decode($workTimesJson, true) ?? [] : (array) $workTimesJson;

        if (empty($workTimes)) {
            return true;
        }

        $now = now();
        $currentDay = $now->format('l');
        $currentTime = $now->format('H:i');

        foreach ($workTimes as $daySchedule) {
            if (($daySchedule['day'] ?? '') === $currentDay) {
                if (! empty($daySchedule['closed'])) {
                    return false;
                }

                $from = $daySchedule['from'] ?? '00:00';
                $to = $daySchedule['to'] ?? '23:59';

                return $currentTime >= $from && $currentTime <= $to;
            }
        }

        return true;
    }
}
