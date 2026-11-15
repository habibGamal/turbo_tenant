<?php

declare(strict_types=1);

namespace App\Plugins\ComboPackages\src\Catalog;

use App\Plugins\CatalogProducts\src\Contracts\ProductDetailsContributor;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\ComboPackages\src\Models\Package;
use App\Plugins\ComboPackages\src\Services\SellablePackageFilter;

final class ProductComboUpsellContributor implements ProductDetailsContributor
{
    public function __construct(
        private readonly SellablePackageFilter $sellablePackageFilter,
    ) {}

    public function pluginId(): string
    {
        return 'combo-packages';
    }

    /**
     * Contribute upsell combo packages containing this product to product details JSON.
     *
     * @return array{combo_packages: list<array{
     *     id: int,
     *     name: string,
     *     name_ar: string,
     *     price: float,
     *     image: string|null,
     *     original_price: float|null
     * }>}
     */
    public function contribute(Product $product): array
    {
        $packages = Package::query()
            ->whereHas('groups.items', function ($query) use ($product): void {
                $query->where('product_id', $product->id);
            })
            ->sellable()
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->with([
                'groups' => fn ($query) => $query->orderBy('sort_order'),
                'groups.items' => fn ($query) => $query->orderBy('sort_order'),
                'groups.items.product.variants',
                'groups.items.product',
                'groups.items.variant',
            ])
            ->get();

        $sellable = $this->sellablePackageFilter->filterCollection($packages);

        $topPackages = $sellable
            ->sortBy([
                ['is_featured', 'desc'],
                ['sort_order', 'asc'],
            ])
            ->take(3)
            ->values();

        $data = $topPackages->map(fn (Package $pkg): array => [
            'id' => (int) $pkg->id,
            'name' => (string) $pkg->name,
            'name_ar' => (string) ($pkg->name_ar ?? $pkg->name),
            'price' => (float) $pkg->price,
            'image' => $pkg->image,
            'original_price' => $pkg->original_price !== null ? (float) $pkg->original_price : null,
        ])->all();

        return [
            'combo_packages' => $data,
        ];
    }
}
