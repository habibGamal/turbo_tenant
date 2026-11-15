<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\TimeWindow;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

final class TimeWindowTest extends TestCase
{
    public function test_same_day_window_contains_time_within_range(): void
    {
        $window = TimeWindow::fromStrings('09:00', '17:00');

        $this->assertTrue($window->contains(Carbon::parse('12:00:00')));
        $this->assertTrue($window->contains(Carbon::parse('09:00:00')));
        $this->assertTrue($window->contains(Carbon::parse('17:00:00')));

        $this->assertFalse($window->contains(Carbon::parse('08:59:59')));
        $this->assertFalse($window->contains(Carbon::parse('17:00:01')));
        $this->assertFalse($window->contains(Carbon::parse('23:00:00')));
    }

    public function test_overnight_window_contains_time_across_midnight(): void
    {
        $window = TimeWindow::fromStrings('22:00', '02:00');

        $this->assertTrue($window->contains(Carbon::parse('22:00:00')));
        $this->assertTrue($window->contains(Carbon::parse('23:30:00')));
        $this->assertTrue($window->contains(Carbon::parse('00:00:00')));
        $this->assertTrue($window->contains(Carbon::parse('01:00:00')));
        $this->assertTrue($window->contains(Carbon::parse('02:00:00')));

        $this->assertFalse($window->contains(Carbon::parse('21:59:59')));
        $this->assertFalse($window->contains(Carbon::parse('02:00:01')));
        $this->assertFalse($window->contains(Carbon::parse('12:00:00')));
    }

    public function test_handles_seconds_input(): void
    {
        $sameDay = TimeWindow::fromStrings('09:15:30', '17:45:15');

        $this->assertTrue($sameDay->contains(Carbon::parse('09:15:30')));
        $this->assertTrue($sameDay->contains(Carbon::parse('17:45:15')));
        $this->assertTrue($sameDay->contains(Carbon::parse('12:00:00')));
        $this->assertFalse($sameDay->contains(Carbon::parse('09:15:29')));
        $this->assertFalse($sameDay->contains(Carbon::parse('17:45:16')));

        $overnight = TimeWindow::fromStrings('22:30:45', '03:15:20');

        $this->assertTrue($overnight->contains(Carbon::parse('22:30:45')));
        $this->assertTrue($overnight->contains(Carbon::parse('03:15:20')));
        $this->assertTrue($overnight->contains(Carbon::parse('23:45:00')));
        $this->assertTrue($overnight->contains(Carbon::parse('01:30:00')));
        $this->assertFalse($overnight->contains(Carbon::parse('22:30:44')));
        $this->assertFalse($overnight->contains(Carbon::parse('03:15:21')));
    }

    public function test_is_overnight_identifies_overnight_windows(): void
    {
        $overnight = TimeWindow::fromStrings('18:00', '02:00');
        $this->assertTrue($overnight->isOvernight());

        $sameDay = TimeWindow::fromStrings('09:00', '17:00');
        $this->assertFalse($sameDay->isOvernight());

        $equal = TimeWindow::fromStrings('12:00', '12:00');
        $this->assertFalse($equal->isOvernight());
    }

    public function test_contains_before_midnight(): void
    {
        $overnight = TimeWindow::fromStrings('18:00', '02:00');

        $this->assertTrue($overnight->containsBeforeMidnight(Carbon::parse('18:00:00')));
        $this->assertTrue($overnight->containsBeforeMidnight(Carbon::parse('23:30:00')));
        $this->assertTrue($overnight->containsBeforeMidnight(Carbon::parse('23:59:59')));

        $this->assertFalse($overnight->containsBeforeMidnight(Carbon::parse('17:59:59')));
        $this->assertFalse($overnight->containsBeforeMidnight(Carbon::parse('01:00:00')));

        // Non-overnight window always returns false
        $sameDay = TimeWindow::fromStrings('09:00', '17:00');
        $this->assertFalse($sameDay->containsBeforeMidnight(Carbon::parse('12:00:00')));
        $this->assertFalse($sameDay->containsBeforeMidnight(Carbon::parse('23:00:00')));
    }

    public function test_contains_after_midnight(): void
    {
        $overnight = TimeWindow::fromStrings('18:00', '02:00');

        $this->assertTrue($overnight->containsAfterMidnight(Carbon::parse('00:00:00')));
        $this->assertTrue($overnight->containsAfterMidnight(Carbon::parse('01:00:00')));
        $this->assertTrue($overnight->containsAfterMidnight(Carbon::parse('02:00:00')));

        $this->assertFalse($overnight->containsAfterMidnight(Carbon::parse('02:00:01')));
        $this->assertFalse($overnight->containsAfterMidnight(Carbon::parse('03:00:00')));
        $this->assertFalse($overnight->containsAfterMidnight(Carbon::parse('23:30:00')));

        // Non-overnight window always returns false
        $sameDay = TimeWindow::fromStrings('09:00', '17:00');
        $this->assertFalse($sameDay->containsAfterMidnight(Carbon::parse('01:00:00')));
        $this->assertFalse($sameDay->containsAfterMidnight(Carbon::parse('12:00:00')));
    }
}
