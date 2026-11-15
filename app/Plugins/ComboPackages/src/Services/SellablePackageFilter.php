<?php

declare(strict_types=1);

namespace App\Plugins\ComboPackages\src\Services;

use App\Plugins\ComboPackages\src\Models\Package;
use App\Plugins\ComboPackages\src\Models\PackageItem;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

final class SellablePackageFilter
{
    /**
     * @param  Collection<int, Package>  $packages
     * @return Collection<int, Package>
     */
    public function filterCollection(Collection $packages, ?CarbonInterface $at = null): Collection
    {
        $packages->loadMissing([
            'groups' => fn ($query) => $query->orderBy('sort_order'),
            'groups.items' => fn ($query) => $query->orderBy('sort_order'),
            'groups.items.product.variants',
            'groups.items.product',
            'groups.items.variant',
        ]);

        $filtered = $packages
            ->map(fn (Package $package): ?Package => $this->filterOne($package, $at))
            ->filter()
            ->values();

        return new Collection($filtered->all());
    }

    /**
     * Alias for filterCollection.
     *
     * @param  Collection<int, Package>  $packages
     * @return Collection<int, Package>
     */
    public function filter(Collection $packages, ?CarbonInterface $at = null): Collection
    {
        return $this->filterCollection($packages, $at);
    }

    /**
     * Filter a single package. Returns null if not sellable.
     */
    public function filterOne(Package $package, ?CarbonInterface $at = null): ?Package
    {
        if (! $this->isSellable($package, $at)) {
            return null;
        }

        $package->loadMissing([
            'groups' => fn ($query) => $query->orderBy('sort_order'),
            'groups.items' => fn ($query) => $query->orderBy('sort_order'),
            'groups.items.product.variants',
            'groups.items.product',
            'groups.items.variant',
        ]);

        if ($package->groups->isEmpty()) {
            return null;
        }

        $filteredGroups = new Collection();
        $totalAvailableItems = 0;

        foreach ($package->groups as $group) {
            $originalCount = $group->items->count();

            $availableItems = $group->items->filter(function (PackageItem $item): bool {
                $product = $item->product;
                if ($product === null || ! (bool) $product->is_active) {
                    return false;
                }

                if ($item->variant_mode === 'customer_choice') {
                    $availableVariants = $product->variants ? $product->variants->where('is_available', true) : collect();
                    if ($availableVariants->isEmpty()) {
                        return false;
                    }
                } elseif ($item->variant_id !== null) {
                    $variant = $item->variant;
                    if ($variant === null || ! (bool) $variant->is_available) {
                        return false;
                    }
                }

                return true;
            })->values();

            $availableCount = $availableItems->count();
            $totalAvailableItems += $availableCount;

            if ($group->selection_type === 'all') {
                if ($availableCount < $originalCount || $availableCount === 0) {
                    return null;
                }
            } elseif ($group->selection_type === 'choose_one') {
                if ($availableCount < 1) {
                    return null;
                }
            } elseif ($group->selection_type === 'choose_multiple') {
                $minSelections = $group->min_selections ?? 0;
                if ($availableCount < $minSelections) {
                    return null;
                }
            }

            $group->setRelation('items', $availableItems);
            $filteredGroups->push($group);
        }

        if ($totalAvailableItems === 0) {
            return null;
        }

        $package->setRelation('groups', $filteredGroups);

        return $package;
    }

    public function isSellable(Package $package, ?CarbonInterface $at = null): bool
    {
        if ($package->trashed()) {
            return false;
        }

        $now = $at ?? now();

        if (! (bool) $package->is_active) {
            return false;
        }

        if ($package->valid_from !== null && $package->valid_from->isAfter($now)) {
            return false;
        }

        if ($package->valid_until !== null && $package->valid_until->isBefore($now)) {
            return false;
        }

        return true;
    }
}
