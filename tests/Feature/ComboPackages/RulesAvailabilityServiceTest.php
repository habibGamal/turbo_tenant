<?php

declare(strict_types=1);

namespace Tests\Feature\ComboPackages;

use App\Plugins\ComboPackages\src\Models\Package;
use App\Plugins\ComboPackages\src\Models\PackageAvailability;
use App\Plugins\ComboPackages\src\Services\PackageAvailabilityService;
use Carbon\Carbon;

beforeEach(function (): void {
    $this->service = app(PackageAvailabilityService::class);
    $this->package = Package::factory()->create([
        'is_active' => true,
        'valid_from' => null,
        'valid_until' => null,
    ]);
});

it('treats package with no windows as always available', function (): void {
    $sunday = Carbon::parse('2026-11-08 10:00:00'); // Sunday
    $mondayNight = Carbon::parse('2026-11-09 23:59:00'); // Monday night

    expect($this->service->isWithinHours($this->package, $sunday))->toBeTrue()
        ->and($this->service->isWithinHours($this->package, $mondayNight))->toBeTrue()
        ->and($this->service->nextAvailableAt($this->package, $sunday))->toBeNull();
});

it('evaluates inside and outside a same-day window', function (): void {
    // Monday 12:00 to 16:00
    PackageAvailability::create([
        'package_id' => $this->package->id,
        'day_of_week' => 1,
        'starts_at' => '12:00:00',
        'ends_at' => '16:00:00',
    ]);

    $beforeWindow = Carbon::parse('2026-11-09 11:30:00'); // Monday
    $insideWindow = Carbon::parse('2026-11-09 14:15:00'); // Monday
    $afterWindow = Carbon::parse('2026-11-09 16:30:00'); // Monday

    expect($this->service->isWithinHours($this->package, $beforeWindow))->toBeFalse()
        ->and($this->service->isWithinHours($this->package, $insideWindow))->toBeTrue()
        ->and($this->service->isWithinHours($this->package, $afterWindow))->toBeFalse();
});

it('handles overnight window crossing midnight both before and after midnight', function (): void {
    // Sunday overnight: 22:00 Sunday to 02:00 Monday
    PackageAvailability::create([
        'package_id' => $this->package->id,
        'day_of_week' => 0, // Sunday
        'starts_at' => '22:00:00',
        'ends_at' => '02:00:00',
    ]);

    $sundayBefore = Carbon::parse('2026-11-08 21:00:00'); // Sunday before window
    $sundayInside = Carbon::parse('2026-11-08 23:30:00'); // Sunday after 22:00
    $mondayInside = Carbon::parse('2026-11-09 01:15:00'); // Monday before 02:00 (spill-over)
    $mondayAfter = Carbon::parse('2026-11-09 02:30:00');  // Monday after 02:00

    expect($this->service->isWithinHours($this->package, $sundayBefore))->toBeFalse()
        ->and($this->service->isWithinHours($this->package, $sundayInside))->toBeTrue()
        ->and($this->service->isWithinHours($this->package, $mondayInside))->toBeTrue()
        ->and($this->service->isWithinHours($this->package, $mondayAfter))->toBeFalse();
});

it('restricts availability to the configured weekday', function (): void {
    // Wednesday 12:00 to 18:00 (day_of_week = 3)
    PackageAvailability::create([
        'package_id' => $this->package->id,
        'day_of_week' => 3,
        'starts_at' => '12:00:00',
        'ends_at' => '18:00:00',
    ]);

    $mondayTime = Carbon::parse('2026-11-09 14:00:00');    // Monday (day 1)
    $wednesdayTime = Carbon::parse('2026-11-11 14:00:00'); // Wednesday (day 3)

    expect($this->service->isWithinHours($this->package, $mondayTime))->toBeFalse()
        ->and($this->service->isWithinHours($this->package, $wednesdayTime))->toBeTrue();
});

it('calculates nextAvailableAt later today and on a later day', function (): void {
    // Windows: Monday 14:00 - 16:00, and Wednesday 11:00 - 15:00
    PackageAvailability::create([
        'package_id' => $this->package->id,
        'day_of_week' => 1,
        'starts_at' => '14:00:00',
        'ends_at' => '16:00:00',
    ]);
    PackageAvailability::create([
        'package_id' => $this->package->id,
        'day_of_week' => 3,
        'starts_at' => '11:00:00',
        'ends_at' => '15:00:00',
    ]);

    // 1. Later today (Monday 10:00 -> next is Monday 14:00)
    $mondayMorning = Carbon::parse('2026-11-09 10:00:00');
    $nextToday = $this->service->nextAvailableAt($this->package, $mondayMorning);
    expect($nextToday)->not->toBeNull()
        ->and($nextToday->format('Y-m-d H:i:s'))->toBe('2026-11-09 14:00:00');

    // 2. Later day (Monday 18:00 -> next is Wednesday 11:00)
    $mondayEvening = Carbon::parse('2026-11-09 18:00:00');
    $nextLater = $this->service->nextAvailableAt($this->package, $mondayEvening);
    expect($nextLater)->not->toBeNull()
        ->and($nextLater->format('Y-m-d H:i:s'))->toBe('2026-11-11 11:00:00');
});
