<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Order;

use App\Enums\SettingKey;
use App\Plugins\OrderManagement\src\DTOs\OrderManagementConfig;
use App\Plugins\StoreSettings\src\Services\SettingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class StoreHoursTest extends TestCase
{
    use RefreshDatabase;

    private SettingService $settingService;

    private OrderManagementConfig $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settingService = app(SettingService::class);
        $this->config = app(OrderManagementConfig::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_same_day_open_and_closed(): void
    {
        $this->settingService->set(SettingKey::WORK_TIMES, json_encode([
            [
                'day' => 'Wednesday',
                'from' => '09:00',
                'to' => '17:00',
                'closed' => false,
            ],
        ]));

        // Within window: open
        Carbon::setTestNow(Carbon::parse('2026-10-28 12:00:00')); // Wednesday
        $this->assertTrue($this->config->isStoreOpen());

        // Before window: closed
        Carbon::setTestNow(Carbon::parse('2026-10-28 08:59:59'));
        $this->assertFalse($this->config->isStoreOpen());

        // After window: closed
        Carbon::setTestNow(Carbon::parse('2026-10-28 17:00:01'));
        $this->assertFalse($this->config->isStoreOpen());
    }

    public function test_overnight_friday_schedule_across_midnight(): void
    {
        $this->settingService->set(SettingKey::WORK_TIMES, json_encode([
            [
                'day' => 'Friday',
                'from' => '18:00',
                'to' => '02:00',
                'closed' => false,
            ],
            [
                'day' => 'Saturday',
                'from' => '18:00',
                'to' => '02:00',
                'closed' => false,
            ],
        ]));

        // Friday 17:00 (closed)
        Carbon::setTestNow(Carbon::parse('2026-10-30 17:00:00')); // Friday
        $this->assertFalse($this->config->isStoreOpen());

        // Friday 23:30 (open)
        Carbon::setTestNow(Carbon::parse('2026-10-30 23:30:00')); // Friday
        $this->assertTrue($this->config->isStoreOpen());

        // Saturday 01:00 (open - yesterday overnight spill-over)
        Carbon::setTestNow(Carbon::parse('2026-10-31 01:00:00')); // Saturday
        $this->assertTrue($this->config->isStoreOpen());

        // Saturday 03:00 (closed - spill-over ended at 02:00, Saturday evening shift not started)
        Carbon::setTestNow(Carbon::parse('2026-10-31 03:00:00')); // Saturday
        $this->assertFalse($this->config->isStoreOpen());
    }

    public function test_closed_day_is_closed_unless_yesterday_overnight_spills_over(): void
    {
        $this->settingService->set(SettingKey::WORK_TIMES, json_encode([
            [
                'day' => 'Friday',
                'from' => '18:00',
                'to' => '02:00',
                'closed' => false,
            ],
            [
                'day' => 'Saturday',
                'from' => '10:00',
                'to' => '20:00',
                'closed' => true,
            ],
        ]));

        // Saturday 01:00 (open: yesterday's overnight spillover applies even though Saturday is closed)
        Carbon::setTestNow(Carbon::parse('2026-10-31 01:00:00'));
        $this->assertTrue($this->config->isStoreOpen());

        // Saturday 03:00 (closed: spillover expired, Saturday is closed)
        Carbon::setTestNow(Carbon::parse('2026-10-31 03:00:00'));
        $this->assertFalse($this->config->isStoreOpen());

        // Saturday 12:00 (closed: Saturday is closed)
        Carbon::setTestNow(Carbon::parse('2026-10-31 12:00:00'));
        $this->assertFalse($this->config->isStoreOpen());
    }

    public function test_legacy_open_at_close_at_is_closed_keys(): void
    {
        $this->settingService->set(SettingKey::WORK_TIMES, json_encode([
            [
                'day' => 'Monday',
                'open_at' => '10:00',
                'close_at' => '18:00',
                'is_closed' => false,
            ],
            [
                'day' => 'Tuesday',
                'open_at' => '10:00',
                'close_at' => '18:00',
                'is_closed' => true,
            ],
            [
                'day' => 'Thursday',
                'open_at' => '22:00',
                'close_at' => '04:00',
                'is_closed' => false,
            ],
            [
                'day' => 'Friday',
                'open_at' => '10:00',
                'close_at' => '18:00',
                'is_closed' => false,
            ],
        ]));

        // Monday open within hours
        Carbon::setTestNow(Carbon::parse('2026-10-26 14:00:00')); // Monday
        $this->assertTrue($this->config->isStoreOpen());

        // Monday closed outside hours
        Carbon::setTestNow(Carbon::parse('2026-10-26 09:00:00'));
        $this->assertFalse($this->config->isStoreOpen());

        // Tuesday marked is_closed: true
        Carbon::setTestNow(Carbon::parse('2026-10-27 14:00:00')); // Tuesday
        $this->assertFalse($this->config->isStoreOpen());

        // Thursday overnight into Friday: Friday 02:00 open
        Carbon::setTestNow(Carbon::parse('2026-10-30 02:00:00')); // Friday
        $this->assertTrue($this->config->isStoreOpen());

        // Friday 05:00 closed
        Carbon::setTestNow(Carbon::parse('2026-10-30 05:00:00')); // Friday
        $this->assertFalse($this->config->isStoreOpen());
    }

    public function test_empty_work_times_returns_true(): void
    {
        $this->settingService->set(SettingKey::WORK_TIMES, '[]');
        Carbon::setTestNow(Carbon::parse('2026-10-28 12:00:00'));

        $this->assertTrue($this->config->isStoreOpen());
    }

    public function test_missing_day_with_no_schedule_entry_is_open(): void
    {
        $this->settingService->set(SettingKey::WORK_TIMES, json_encode([
            [
                'day' => 'Monday',
                'from' => '09:00',
                'to' => '17:00',
                'closed' => false,
            ],
        ]));

        // Wednesday has no schedule entry and no spill-over from Tuesday -> open
        Carbon::setTestNow(Carbon::parse('2026-10-28 12:00:00')); // Wednesday
        $this->assertTrue($this->config->isStoreOpen());
    }
}
