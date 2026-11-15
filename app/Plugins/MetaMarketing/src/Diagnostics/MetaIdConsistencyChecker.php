<?php

declare(strict_types=1);

namespace App\Plugins\MetaMarketing\src\Diagnostics;

use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\MetaMarketing\src\Capi\OrderPurchaseContents;
use App\Plugins\MetaMarketing\src\Catalog\CatalogItemBuilder;
use App\Plugins\OrderManagement\src\Enums\OrderType;
use App\Plugins\OrderManagement\src\Models\Order;

final class MetaIdConsistencyChecker
{
    public function __construct(
        private readonly CatalogItemBuilder $catalogItemBuilder,
    ) {}

    /**
     * @param  iterable<Order>|null  $orders
     */
    public function check(int $limit = 50, ?iterable $orders = null): IdConsistencyReport
    {
        if ($orders === null) {
            $orders = Order::query()
                ->where(function ($query): void {
                    $query->whereNull('type')
                        ->orWhereNotIn('type', [OrderType::POS, OrderType::POS->value]);
                })
                ->with('items')
                ->latest('id')
                ->limit($limit)
                ->get();
        }

        $catalogIds = $this->buildCatalogIdSet();

        $totalOrdersChecked = 0;
        $totalContentIdsChecked = 0;
        $matchedCount = 0;
        $missingIds = [];

        foreach ($orders as $order) {
            $totalOrdersChecked++;
            $purchase = OrderPurchaseContents::fromOrder($order);

            foreach ($purchase->contentIds as $contentId) {
                $totalContentIdsChecked++;

                if (isset($catalogIds[$contentId])) {
                    $matchedCount++;
                } else {
                    $missingIds[] = $contentId;
                }
            }
        }

        $uniqueMissingIds = array_values(array_unique($missingIds));
        $matchPercentage = $totalContentIdsChecked > 0
            ? round(($matchedCount / $totalContentIdsChecked) * 100, 1)
            : 100.0;

        return new IdConsistencyReport(
            totalOrdersChecked: $totalOrdersChecked,
            totalContentIdsChecked: $totalContentIdsChecked,
            matchedCount: $matchedCount,
            matchPercentage: $matchPercentage,
            missingIds: $uniqueMissingIds,
        );
    }

    /**
     * @return array<string, bool>
     */
    public function buildCatalogIdSet(): array
    {
        /** @var array<string, bool> $catalogIds */
        $catalogIds = [];

        Product::query()
            ->with(['variants', 'category'])
            ->chunkById(100, function ($products) use (&$catalogIds): void {
                foreach ($products as $product) {
                    foreach ($this->catalogItemBuilder->buildForProduct($product) as $item) {
                        $catalogIds[$item->id] = true;
                    }
                }
            });

        return $catalogIds;
    }
}
