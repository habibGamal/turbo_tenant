<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Services;

use App\Plugins\PosIntegration\src\Exceptions\PosMasterUnavailable;
use App\Plugins\PosIntegration\src\Models\ProductPosMapping;
use App\Plugins\PosIntegration\src\Services\POS\Contracts\PosMasterClient;
use Illuminate\Support\Collection;

final class PosCatalogDiff
{
    public function __construct(
        private readonly PosMasterClient $client
    ) {}

    /**
     * Find new products that don't exist locally.
     * Filters by manufactured/manifactured and consumable types.
     *
     * @return Collection<int, array{id: int, posRef: string, name: string, type: string, categoryId: int|null, categoryName: string|null}>
     *
     * @throws PosMasterUnavailable
     */
    public function findNewProducts(): Collection
    {
        $masterCategories = $this->client->getAllProductReferences();

        $existingRefs = ProductPosMapping::query()
            ->pluck('pos_item_id')
            ->toArray();

        $newProducts = collect();

        foreach ($masterCategories as $item) {
            $products = isset($item['products']) && is_array($item['products'])
                ? $item['products']
                : [$item];

            foreach ($products as $product) {
                $type = mb_strtolower($product['type'] ?? '');
                if (! in_array($type, ['manufactured', 'manifactured', 'consumable'], true)) {
                    continue;
                }

                $posRef = $product['productRef'] ?? $product['posRef'] ?? null;
                if (! $posRef || in_array($posRef, $existingRefs, true)) {
                    continue;
                }

                $newProducts->push([
                    'id' => $product['id'],
                    'posRef' => $posRef,
                    'name' => $product['name'],
                    'type' => $product['type'],
                    'categoryId' => $product['categoryId'] ?? $item['id'] ?? null,
                    'categoryName' => $product['categoryName'] ?? $item['name'] ?? null,
                ]);
            }
        }

        return $newProducts;
    }

    /**
     * Find products with price differences between master and local.
     * Loads all mappings with their products in one query, keyed by pos_item_id.
     *
     * @return Collection<int, array{id: int, name: string, posRef: string, product_id: int, localPrice: float, localPriceAfterDiscount: float, masterPrice: float, masterPriceAfterDiscount: float}>
     *
     * @throws PosMasterUnavailable
     */
    public function findProductsWithPriceChanges(): Collection
    {
        $masterCategories = $this->client->getAllProductPrices();
        $productsWithChanges = collect();

        $masterProducts = [];
        $posRefs = [];

        foreach ($masterCategories as $item) {
            $products = isset($item['products']) && is_array($item['products'])
                ? $item['products']
                : [$item];

            foreach ($products as $masterProduct) {
                $posRef = $masterProduct['productRef'] ?? $masterProduct['posRef'] ?? null;
                if ($posRef) {
                    $masterProducts[] = $masterProduct;
                    $posRefs[] = $posRef;
                }
            }
        }

        if (empty($posRefs)) {
            return $productsWithChanges;
        }

        $mappings = ProductPosMapping::query()
            ->with('product')
            ->whereIn('pos_item_id', array_values(array_unique($posRefs)))
            ->get()
            ->keyBy('pos_item_id');

        foreach ($masterProducts as $masterProduct) {
            $posRef = $masterProduct['productRef'] ?? $masterProduct['posRef'] ?? null;
            if (! $posRef) {
                continue;
            }

            $mapping = $mappings->get($posRef);
            if (! $mapping) {
                continue;
            }

            $product = $mapping->product;
            if (! $product) {
                continue;
            }

            $localBasePrice = (float) $product->base_price;
            $localDiscountPrice = (float) $product->price_after_discount;
            $masterBasePrice = (float) ($masterProduct['price'] ?? $masterProduct['prices'][0]['price'] ?? 0);
            $masterDiscountPrice = (float) ($masterProduct['priceAfterDiscount'] ?? $masterProduct['prices'][0]['priceAfterDiscount'] ?? $masterBasePrice);

            if ($localBasePrice !== $masterBasePrice || $localDiscountPrice !== $masterDiscountPrice) {
                $productsWithChanges->push([
                    'id' => $masterProduct['id'],
                    'name' => $product->name,
                    'posRef' => $posRef,
                    'product_id' => $product->id,
                    'localPrice' => $localBasePrice,
                    'localPriceAfterDiscount' => $localDiscountPrice,
                    'masterPrice' => $masterBasePrice,
                    'masterPriceAfterDiscount' => $masterDiscountPrice,
                ]);
            }
        }

        return $productsWithChanges;
    }
}
