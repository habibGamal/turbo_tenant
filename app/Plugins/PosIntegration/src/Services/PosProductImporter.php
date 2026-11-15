<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Services;

use App\Plugins\CatalogProducts\src\Contracts\ImportedProduct;
use App\Plugins\CatalogProducts\src\Contracts\ProductImporter;
use App\Plugins\CatalogProducts\src\Contracts\ProductPriceUpdater;
use App\Plugins\PosIntegration\src\DTOs\PosImportResult;
use App\Plugins\PosIntegration\src\Exceptions\PosMasterUnavailable;
use App\Plugins\PosIntegration\src\Models\ProductPosMapping;
use App\Plugins\PosIntegration\src\Services\POS\Contracts\PosMasterClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class PosProductImporter
{
    public function __construct(
        private readonly PosMasterClient $client,
        private readonly ProductImporter $productImporter,
        private readonly ProductPriceUpdater $productPriceUpdater,
    ) {}

    /**
     * Import new products from master repository.
     * Each product import runs inside ONE DB::transaction.
     *
     * @param  array<int>  $productIds
     *
     * @throws PosMasterUnavailable
     */
    public function importProducts(array $productIds): PosImportResult
    {
        $masterProducts = $this->client->getProductsByIds($productIds);

        $imported = 0;
        $failed = 0;
        $errors = [];

        foreach ($masterProducts as $masterProduct) {
            try {
                DB::transaction(function () use ($masterProduct): void {
                    $categoryData = $masterProduct['category'];

                    $product = $this->productImporter->importProduct(new ImportedProduct(
                        name: $masterProduct['name'],
                        basePrice: (float) $masterProduct['price'],
                        priceAfterDiscount: isset($masterProduct['priceAfterDiscount']) ? (float) $masterProduct['priceAfterDiscount'] : null,
                        categoryName: $categoryData['name'],
                        categoryImage: $categoryData['image'] ?? null,
                        image: null,
                    ));

                    ProductPosMapping::query()->updateOrCreate([
                        'product_id' => $product->id,
                        'variant_id' => null,
                        'extra_option_item_id' => null,
                        'branch_id' => null,
                    ], [
                        'pos_item_id' => $masterProduct['productRef'] ?? $masterProduct['posRef'],
                    ]);
                });

                $imported++;
            } catch (Throwable $e) {
                $failed++;
                $productName = $masterProduct['name'] ?? 'Unknown';
                $errors[] = "Failed to import product {$productName}: {$e->getMessage()}";
                Log::error('Failed to import product', [
                    'product' => $masterProduct,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return new PosImportResult($imported, $failed, $errors);
    }

    /**
     * Update product prices from master repository.
     *
     * @param  array<int>  $productIds
     *
     * @throws PosMasterUnavailable
     */
    public function updateProductPrices(array $productIds): PosImportResult
    {
        $masterProducts = $this->client->getProductPricesByIds($productIds);

        $updated = 0;
        $failed = 0;
        $errors = [];

        foreach ($masterProducts as $masterProduct) {
            try {
                $posRef = $masterProduct['productRef'] ?? $masterProduct['posRef'];

                $mapping = ProductPosMapping::query()
                    ->where('pos_item_id', $posRef)
                    ->first();

                if (! $mapping) {
                    $failed++;
                    $errors[] = "Product with POS ref {$posRef} not found locally";

                    continue;
                }

                $product = $mapping->product;

                if (! $product) {
                    $failed++;
                    $errors[] = "Product mapping exists but product not found for POS ref {$posRef}";

                    continue;
                }

                $rawBase = $masterProduct['price'] ?? $masterProduct['prices'][0]['price'] ?? null;
                $rawDiscount = $masterProduct['priceAfterDiscount'] ?? $masterProduct['prices'][0]['priceAfterDiscount'] ?? null;

                $basePrice = (float) ($rawBase ?? 0.0);
                $discountPrice = $rawDiscount !== null ? (float) $rawDiscount : null;

                $this->productPriceUpdater->updatePrices($product->id, $basePrice, $discountPrice);

                $updated++;
            } catch (Throwable $e) {
                $failed++;
                $errors[] = "Failed to update prices for product: {$e->getMessage()}";
                Log::error('Failed to update product prices', [
                    'product' => $masterProduct,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return new PosImportResult($updated, $failed, $errors);
    }
}
