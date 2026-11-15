<?php

declare(strict_types=1);

namespace App\Plugins\ComboPackages\src\Guards;

use App\Plugins\CatalogProducts\src\Contracts\ProductDeletionGuard;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\ComboPackages\src\Models\Package;

final class PackageProductDeletionGuard implements ProductDeletionGuard
{
    public function pluginId(): string
    {
        return 'combo-packages';
    }

    public function blockingReason(Product $product): ?string
    {
        $packages = Package::query()
            ->whereHas('groups.items', function ($query) use ($product): void {
                $query->where('product_id', $product->id);
            })
            ->get(['id', 'name', 'name_ar']);

        if ($packages->isEmpty()) {
            return null;
        }

        $count = $packages->count();
        $names = $packages->map(fn (Package $package): string => $package->name_ar ?: $package->name)->filter()->implode('، ');

        return "لا يمكن حذف المنتج لأنه مستخدم في {$count} من باقات التوفير: {$names}";
    }
}
