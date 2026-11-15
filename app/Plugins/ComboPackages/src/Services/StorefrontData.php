<?php

declare(strict_types=1);

namespace App\Plugins\ComboPackages\src\Services;

use App\Plugins\ComboPackages\src\Http\Resources\PackageResource;
use App\Plugins\ComboPackages\src\Models\Package;

final class StorefrontData
{
    public function __construct(
        private readonly SellablePackageFilter $sellablePackageFilter,
        private readonly PackageAvailabilityService $availabilityService,
    ) {}

    /**
     * @return array<int, mixed>
     */
    public function index(?int $productId = null): array
    {
        $query = Package::query()
            ->sellable()
            ->with([
                'availabilities',
                'branches',
                'groups' => fn ($query) => $query->orderBy('sort_order'),
                'groups.items' => fn ($query) => $query->orderBy('sort_order'),
                'groups.items.product.variants',
                'groups.items.product.extraOption.items',
                'groups.items.product',
                'groups.items.variant',
            ])
            ->orderBy('sort_order');

        if ($productId !== null) {
            $query->whereHas('items', fn ($q) => $q->where('package_items.product_id', $productId));
        }

        $packages = $query->get();

        $sellablePackages = $this->sellablePackageFilter->filterCollection($packages);

        $this->availabilityService->preloadSoldToday($sellablePackages->pluck('id')->all());

        return PackageResource::collection($sellablePackages)->resolve();
    }
}
