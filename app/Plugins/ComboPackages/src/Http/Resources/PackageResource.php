<?php

declare(strict_types=1);

namespace App\Plugins\ComboPackages\src\Http\Resources;

use App\Plugins\ComboPackages\src\Services\PackageAvailabilityService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $name
 * @property string|null $name_ar
 * @property string|null $description
 * @property string|null $description_ar
 * @property string|null $image
 * @property float|int|string $price
 * @property float|int|string|null $original_price
 * @property int|null $discount_percentage
 * @property string|null $badge
 * @property string|null $badge_ar
 * @property string $icon
 * @property string $gradient
 * @property bool $is_active
 * @property bool $is_featured
 * @property int $sort_order
 * @property \Illuminate\Support\Carbon|null $valid_from
 * @property \Illuminate\Support\Carbon|null $valid_until
 * @property \Illuminate\Database\Eloquent\Collection<int, \App\Plugins\ComboPackages\src\Models\PackageGroup> $groups
 */
final class PackageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $price = (float) $this->price;
        $originalPrice = $this->original_price !== null ? (float) $this->original_price : null;
        $savingsAmount = null;

        if ($originalPrice !== null && $originalPrice > $price) {
            $savingsAmount = round($originalPrice - $price, 2);
        }

        $availabilityService = app(PackageAvailabilityService::class);
        $availableNow = $availabilityService->availableNow($this->resource);
        $unavailableReason = $availabilityService->unavailableReason($this->resource);
        $nextAvailableAt = ($unavailableReason === 'outside_hours')
            ? $availabilityService->nextAvailableAt($this->resource)?->toIso8601String()
            : null;

        $availability = $this->availabilities
            ? $this->availabilities->map(fn ($a) => [
                'day_of_week' => (int) $a->day_of_week,
                'starts_at' => mb_substr((string) $a->starts_at, 0, 5),
                'ends_at' => mb_substr((string) $a->ends_at, 0, 5),
            ])->values()->all()
            : [];

        $branchIds = $this->branches
            ? $this->branches->pluck('id')->map(fn ($id) => (int) $id)->values()->all()
            : [];

        $orderTypes = is_array($this->allowed_order_types) && ! empty($this->allowed_order_types)
            ? array_values($this->allowed_order_types)
            : [];

        $remainingToday = $availabilityService->remainingToday($this->resource);

        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            'name_ar' => $this->name_ar !== null ? (string) $this->name_ar : null,
            'description' => $this->description !== null ? (string) $this->description : null,
            'description_ar' => $this->description_ar !== null ? (string) $this->description_ar : null,
            'image' => $this->image !== null ? (string) $this->image : null,
            'price' => $price,
            'allow_coupons' => (bool) ($this->allow_coupons ?? true),
            'original_price' => $originalPrice,
            'discount_percentage' => $this->discount_percentage !== null ? (int) $this->discount_percentage : null,
            'savings_amount' => $savingsAmount,
            'badge' => $this->badge !== null ? (string) $this->badge : null,
            'badge_ar' => $this->badge_ar !== null ? (string) $this->badge_ar : null,
            'icon' => (string) ($this->icon ?? ''),
            'gradient' => (string) ($this->gradient ?? ''),
            'is_active' => (bool) $this->is_active,
            'is_featured' => (bool) $this->is_featured,
            'sort_order' => (int) $this->sort_order,
            'valid_from' => $this->valid_from?->toISOString() ?? null,
            'valid_until' => $this->valid_until?->toISOString() ?? null,
            'available_now' => $availableNow,
            'unavailable_reason' => $unavailableReason,
            'next_available_at' => $nextAvailableAt,
            'availability' => $availability,
            'branch_ids' => $branchIds,
            'order_types' => $orderTypes,
            'max_per_order' => $this->max_per_order !== null ? (int) $this->max_per_order : null,
            'remaining_today' => $remainingToday,
            'groups' => PackageGroupResource::collection($this->whenLoaded('groups')),
        ];
    }
}
