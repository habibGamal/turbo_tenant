<?php

declare(strict_types=1);

namespace App\Plugins\ComboPackages\src\Services;

use App\Plugins\ComboPackages\src\Models\Package;
use App\Plugins\OrderManagement\src\Enums\OrderStatus;
use App\Plugins\OrderManagement\src\Enums\PaymentStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class PackageAvailabilityService
{
    /** @var array<string, array<int, int>> */
    private array $memoizedSoldCounts = [];

    public function __construct(
        private readonly SellablePackageFilter $sellableFilter
    ) {}

    public function isWithinHours(Package $package, ?CarbonInterface $at = null): bool
    {
        $at = $at ? $at->copy() : now();

        $availabilities = $package->relationLoaded('availabilities')
            ? $package->availabilities
            : $package->availabilities()->get();

        if ($availabilities->isEmpty()) {
            return true;
        }

        $currentTimeSeconds = self::parseSeconds($at->format('H:i:s'));

        // 1. Yesterday's spill-over check for overnight windows
        $yesterdayDay = ($at->dayOfWeek + 6) % 7;
        $yesterdayWindows = $availabilities->filter(fn ($w) => (int) $w->day_of_week === $yesterdayDay);

        foreach ($yesterdayWindows as $window) {
            $startSeconds = self::parseSeconds((string) $window->starts_at);
            $endSeconds = self::parseSeconds((string) $window->ends_at);

            // Overnight window
            if ($endSeconds < $startSeconds && $currentTimeSeconds <= $endSeconds) {
                return true;
            }
        }

        // 2. Today's windows
        $todayDay = $at->dayOfWeek;
        $todayWindows = $availabilities->filter(fn ($w) => (int) $w->day_of_week === $todayDay);

        foreach ($todayWindows as $window) {
            $startSeconds = self::parseSeconds((string) $window->starts_at);
            $endSeconds = self::parseSeconds((string) $window->ends_at);

            if ($endSeconds < $startSeconds) {
                // Overnight window starting today
                if ($currentTimeSeconds >= $startSeconds) {
                    return true;
                }
            } else {
                // Normal window within the same day
                if ($currentTimeSeconds >= $startSeconds && $currentTimeSeconds <= $endSeconds) {
                    return true;
                }
            }
        }

        return false;
    }

    public function nextAvailableAt(Package $package, ?CarbonInterface $at = null): ?CarbonInterface
    {
        $at = $at ? $at->copy() : now();

        if ($this->isWithinHours($package, $at)) {
            return null;
        }

        $availabilities = $package->relationLoaded('availabilities')
            ? $package->availabilities
            : $package->availabilities()->get();

        if ($availabilities->isEmpty()) {
            return null;
        }

        /** @var CarbonInterface|null $earliest */
        $earliest = null;

        for ($offset = 0; $offset <= 7; $offset++) {
            $targetDay = ($at->dayOfWeek + $offset) % 7;
            $dayWindows = $availabilities->filter(fn ($w) => (int) $w->day_of_week === $targetDay);

            foreach ($dayWindows as $window) {
                $parts = array_map('intval', explode(':', (string) $window->starts_at));
                $candidate = $at->copy()->addDays($offset)->setTime($parts[0], $parts[1] ?? 0, $parts[2] ?? 0);

                if ($candidate->isAfter($at)) {
                    if ($earliest === null || $candidate->isBefore($earliest)) {
                        $earliest = $candidate;
                    }
                }
            }
        }

        return $earliest;
    }

    public function preloadSoldToday(array $packageIds, ?CarbonInterface $day = null): void
    {
        $this->soldOnMany($packageIds, $day);
    }

    /**
     * @param  array<int>  $packageIds
     * @return array<int, int>
     */
    public function soldOnMany(array $packageIds, ?CarbonInterface $day = null): array
    {
        $day = $day ? $day->copy() : now();
        $dateKey = $day->format('Y-m-d');

        $missingIds = [];
        $result = [];

        foreach ($packageIds as $id) {
            $id = (int) $id;
            if (isset($this->memoizedSoldCounts[$dateKey][$id])) {
                $result[$id] = $this->memoizedSoldCounts[$dateKey][$id];
            } else {
                $missingIds[] = $id;
            }
        }

        if (! empty($missingIds)) {
            $startOfDay = $day->copy()->startOfDay();
            $endOfDay = $day->copy()->endOfDay();

            $query = DB::table('order_items')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('order_items.line_type', 'package')
                ->where('orders.status', '!=', OrderStatus::CANCELLED->value)
                ->where(function ($q): void {
                    $q->whereNull('orders.payment_status')
                        ->orWhere('orders.payment_status', '!=', PaymentStatus::FAILED->value);
                })
                ->whereBetween('orders.created_at', [$startOfDay, $endOfDay])
                ->groupBy('order_items.line_reference_id')
                ->selectRaw('order_items.line_reference_id as package_id, SUM(order_items.quantity) as total_sold');

            if (count($missingIds) < 100) {
                $query->whereIn('order_items.line_reference_id', $missingIds);
            }

            $counts = $query->pluck('total_sold', 'package_id')->all();

            foreach ($missingIds as $id) {
                $sold = isset($counts[$id]) ? (int) $counts[$id] : 0;
                $this->memoizedSoldCounts[$dateKey][$id] = $sold;
                $result[$id] = $sold;
            }
        }

        return $result;
    }

    public function soldOn(Package|int $package, ?CarbonInterface $day = null): int
    {
        $packageId = $package instanceof Package ? (int) $package->id : (int) $package;
        $counts = $this->soldOnMany([$packageId], $day);

        return $counts[$packageId] ?? 0;
    }

    public function remainingToday(Package $package, ?CarbonInterface $at = null): ?int
    {
        if ($package->daily_limit === null) {
            return null;
        }

        $sold = $this->soldOn($package, $at ?? now());

        return max(0, (int) $package->daily_limit - $sold);
    }

    public function availableNow(Package $package, ?CarbonInterface $at = null): bool
    {
        $at = $at ? $at->copy() : now();

        if (! $this->sellableFilter->isSellable($package, $at)) {
            return false;
        }

        if (! $this->isWithinHours($package, $at)) {
            return false;
        }

        $remaining = $this->remainingToday($package, $at);
        if ($remaining !== null && $remaining <= 0) {
            return false;
        }

        return true;
    }

    public function unavailableReason(Package $package, ?CarbonInterface $at = null): ?string
    {
        $at = $at ? $at->copy() : now();

        if ($this->availableNow($package, $at)) {
            return null;
        }

        $remaining = $this->remainingToday($package, $at);
        if ($remaining !== null && $remaining <= 0) {
            return 'sold_out_today';
        }

        if (! $this->isWithinHours($package, $at)) {
            return 'outside_hours';
        }

        return null;
    }

    public function determineHealthStatus(Package $package, ?CarbonInterface $at = null): string
    {
        $at = $at ? $at->copy() : now();

        // 1. "غير نشطة"
        if (! (bool) $package->is_active) {
            return 'غير نشطة';
        }

        // 2. "منتهية"
        if ($package->valid_until !== null && $package->valid_until->isBefore($at)) {
            return 'منتهية';
        }

        // 3. "مجدولة"
        if ($package->valid_from !== null && $package->valid_from->isAfter($at)) {
            return 'مجدولة';
        }

        // 4. "غير مكتملة" (unfulfillable: the storefront hides it because options are unavailable)
        if ($this->sellableFilter->filterOne($package, $at) === null) {
            return 'غير مكتملة';
        }

        // 5. "نفدت اليوم"
        $remaining = $this->remainingToday($package, $at);
        if ($remaining !== null && $remaining <= 0) {
            return 'نفدت اليوم';
        }

        // 6. "خارج أوقات التوفر"
        if (! $this->isWithinHours($package, $at)) {
            return 'خارج أوقات التوفر';
        }

        // 7. "متاحة"
        return 'متاحة';
    }

    private static function parseSeconds(string $time): int
    {
        $parts = array_map('intval', explode(':', mb_trim($time)));
        $hours = $parts[0];
        $minutes = $parts[1] ?? 0;
        $seconds = $parts[2] ?? 0;

        return ($hours * 3600) + ($minutes * 60) + $seconds;
    }
}
