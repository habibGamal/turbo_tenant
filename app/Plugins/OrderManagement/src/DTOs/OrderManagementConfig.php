<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\DTOs;

use App\Core\Contracts\SettingsRepository;
use App\Enums\SettingKey;
use App\Plugins\OrderManagement\src\Settings\OrderSettingKey;
use App\Support\TimeWindow;

final readonly class OrderManagementConfig
{
    public function __construct(
        private ?SettingsRepository $settingService = null
    ) {}

    public function getTaxRate(): float
    {
        if (! $this->settingService) {
            return 0.0;
        }

        $taxRate = $this->settingService->get(OrderSettingKey::TAX_RATE, 0.0);
        $rate = is_numeric($taxRate) ? (float) $taxRate : 0.0;

        if ($rate <= 0.0) {
            return 0.0;
        }

        return $rate / 100.0;
    }

    public function getServiceRate(): float
    {
        return (float) config('app.service_rate', 0.0);
    }

    public function getFreeShippingThreshold(): float
    {
        if (! $this->settingService) {
            return 0.0;
        }

        $threshold = $this->settingService->get(OrderSettingKey::FREE_SHIPPING_THRESHOLD, 0.0);

        return is_numeric($threshold) && (float) $threshold > 0.0 ? (float) $threshold : 0.0;
    }

    public function isSimplifiedAddressEnabled(): bool
    {
        if (! $this->settingService) {
            return false;
        }

        return (bool) $this->settingService->get(OrderSettingKey::SIMPLIFIED_ADDRESS_ENABLED, false);
    }

    public function acceptOrdersAfterWorkTimes(): bool
    {
        if (! $this->settingService) {
            return true;
        }

        return (bool) $this->settingService->get(SettingKey::ACCEPT_ORDERS_AFTER_WORK_TIMES, true);
    }

    public function isStoreOpen(): bool
    {
        if (! $this->settingService) {
            return true;
        }

        $workTimes = $this->settingService->get(SettingKey::WORK_TIMES, []);

        if (! is_array($workTimes) || empty($workTimes)) {
            return true;
        }

        $now = now();
        $yesterdayDay = $now->copy()->subDay()->format('l');
        $todayDay = $now->format('l');

        // Yesterday's overnight spillover keeps the store open after midnight until its 'to' time
        foreach ($workTimes as $schedule) {
            if (! is_array($schedule)) {
                continue;
            }

            if (strcasecmp((string) ($schedule['day'] ?? ''), $yesterdayDay) !== 0) {
                continue;
            }

            $isClosed = filter_var($schedule['closed'] ?? $schedule['is_closed'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($isClosed) {
                continue;
            }

            $from = (string) ($schedule['from'] ?? $schedule['open_at'] ?? '00:00');
            $to = (string) ($schedule['to'] ?? $schedule['close_at'] ?? '23:59');

            $window = TimeWindow::fromStrings($from, $to);
            if ($window->isOvernight() && $window->containsAfterMidnight($now)) {
                return true;
            }
        }

        // Today's schedule: if no schedule entry exists for today, the store is open
        $todaySchedules = [];
        foreach ($workTimes as $schedule) {
            if (is_array($schedule) && strcasecmp((string) ($schedule['day'] ?? ''), $todayDay) === 0) {
                $todaySchedules[] = $schedule;
            }
        }

        if (empty($todaySchedules)) {
            return true;
        }

        foreach ($todaySchedules as $schedule) {
            $isClosed = filter_var($schedule['closed'] ?? $schedule['is_closed'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($isClosed) {
                continue;
            }

            $from = (string) ($schedule['from'] ?? $schedule['open_at'] ?? '00:00');
            $to = (string) ($schedule['to'] ?? $schedule['close_at'] ?? '23:59');

            $window = TimeWindow::fromStrings($from, $to);
            if ($window->isOvernight()) {
                if ($window->containsBeforeMidnight($now)) {
                    return true;
                }

                continue;
            }

            if ($window->contains($now)) {
                return true;
            }
        }

        return false;
    }
}
