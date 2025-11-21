<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SettingKey;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPosMapping;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class ProductPOSImporterService
{
    public function __construct(
        private readonly SettingService $settingService
    ) {}

    /**
     * Get products by their IDs from the master repository
     *
     * @param  array<int>  $ids
     * @return array<array{id: int, productRef: string, name: string, price: float, priceAfterDiscount: float, category: array{id: int, name: string, image: string}}>
     *
     * @throws Exception
     */
    public function getProductsByIds(array $ids): array
    {
        $baseUrl = $this->getMasterRepoUrl();

        if (empty($baseUrl)) {
            throw new Exception('لا يمكن الاتصال بالنقطة الرئيسية');
        }

        $idsString = ','.implode(',', $ids);
        $url = $baseUrl.'/api/get-products-master?ids='.$idsString;

        try {
            $response = Http::timeout(30)->get($url);

            if (! $response->successful()) {
                throw new Exception('لا يمكن الاتصال بالنقطة الرئيسية');
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('Failed to get products by IDs from master', [
                'ids' => $ids,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('لا يمكن الاتصال بالنقطة الرئيسية');
        }
    }

    /**
     * Get all product references from the master repository
     *
     * @return array<array{id: int, posRef: string, name: string, type: string, prices: array<array{id: int, price: float, priceAfterDiscount: float}>}>
     *
     * @throws Exception
     */
    public function getAllProductReferences(): array
    {
        $baseUrl = $this->getMasterRepoUrl();

        if (empty($baseUrl)) {
            throw new Exception('لا يمكن الاتصال بالنقطة الرئيسية');
        }

        $url = $baseUrl.'/api/all-products-refs-master';

        try {
            $response = Http::timeout(30)->get($url);

            if (! $response->successful()) {
                throw new Exception('لا يمكن الاتصال بالنقطة الرئيسية');
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('Failed to get all product references from master', [
                'error' => $e->getMessage(),
            ]);

            throw new Exception('لا يمكن الاتصال بالنقطة الرئيسية');
        }
    }

    /**
     * Get all product prices from the master repository
     *
     * @return array<array{id: int, posRef: string, name: string, prices: array<array{id: int, price: float, priceAfterDiscount: float}>}>
     *
     * @throws Exception
     */
    public function getAllProductPrices(): array
    {
        $baseUrl = $this->getMasterRepoUrl();

        if (empty($baseUrl)) {
            throw new Exception('لا يمكن الاتصال بالنقطة الرئيسية');
        }

        $url = $baseUrl.'/api/all-products-prices-master';

        try {
            $response = Http::timeout(30)->get($url);

            if (! $response->successful()) {
                throw new Exception('لا يمكن الاتصال بالنقطة الرئيسية');
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('Failed to get all product prices from master', [
                'error' => $e->getMessage(),
            ]);

            throw new Exception('لا يمكن الاتصال بالنقطة الرئيسية');
        }
    }

    /**
     * Get product prices by their IDs from the master repository
     *
     * @param  array<int>  $ids
     * @return array<array{id: int, posRef: string, name: string, prices: array<array{id: int, price: float, priceAfterDiscount: float}>}>
     *
     * @throws Exception
     */
    public function getProductPricesByIds(array $ids): array
    {
        $baseUrl = $this->getMasterRepoUrl();

        if (empty($baseUrl)) {
            throw new Exception('لا يمكن الاتصال بالنقطة الرئيسية');
        }

        $idsString = ','.implode(',', $ids);
        $url = $baseUrl.'/api/get-products-prices-master?ids='.$idsString;

        try {
            $response = Http::timeout(30)->get($url);

            if (! $response->successful()) {
                throw new Exception('لا يمكن الاتصال بالنقطة الرئيسية');
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('Failed to get product prices by IDs from master', [
                'ids' => $ids,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('لا يمكن الاتصال بالنقطة الرئيسية');
        }
    }

    /**
     * Get products by their references from the master repository
     *
     * @param  array<string>  $refs
     * @return array<array{id: int, posRef: string, name: string, price: float, priceAfterDiscount: float, category: array{id: int, name: string, image: string}}>
     *
     * @throws Exception
     */
    public function getProductsByReferences(array $refs): array
    {
        $baseUrl = $this->getMasterRepoUrl();

        if (empty($baseUrl)) {
            throw new Exception('لا يمكن الاتصال بالنقطة الرئيسية');
        }

        $refsString = ','.implode(',', $refs);
        $url = $baseUrl.'/api/get-products-master-by-refs?refs='.$refsString;

        try {
            $response = Http::timeout(30)->get($url);

            if (! $response->successful()) {
                throw new Exception('لا يمكن الاتصال بالنقطة الرئيسية');
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('Failed to get products by references from master', [
                'refs' => $refs,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('لا يمكن الاتصال بالنقطة الرئيسية');
        }
    }

    /**
     * Find new products that don't exist locally
     * Filters by manufactured/manifactured and consumable types
     * Returns products grouped by category
     *
     * @return Collection<int, array{id: int, posRef: string, name: string, type: string, categoryId: int, categoryName: string}>
     */
    public function findNewProducts(): Collection
    {
        $masterCategories = $this->getAllProductReferences();

        // Get existing POS references from local database
        $existingRefs = ProductPosMapping::query()
            ->pluck('pos_item_id')
            ->toArray();

        $newProducts = collect();

        // Process each category and its products
        foreach ($masterCategories as $category) {
            foreach ($category['products'] as $product) {
                // Filter by product type
                $type = mb_strtolower($product['type'] ?? '');
                if (! in_array($type, ['manufactured', 'manifactured', 'consumable'], true)) {
                    continue;
                }

                // Check if product already exists locally
                $posRef = $product['productRef'];
                if (in_array($posRef, $existingRefs, true)) {
                    continue;
                }

                // Add product with category information
                $newProducts->push([
                    'id' => $product['id'],
                    'posRef' => $posRef,
                    'name' => $product['name'],
                    'type' => $product['type'],
                    'categoryId' => $category['id'],
                    'categoryName' => $category['name'],
                ]);
            }
        }

        return $newProducts;
    }

    /**
     * Find products with price differences between master and local
     *
     * @return Collection<int, array{id: int, posRef: string, localPrice: float, masterPrice: float}>
     */
    public function findProductsWithPriceChanges(): Collection
    {
        $masterCategories = $this->getAllProductPrices();
        $productsWithChanges = collect();

        // Process each category and its products
        foreach ($masterCategories as $category) {
            foreach ($category['products'] as $masterProduct) {
                $posRef = $masterProduct['productRef'];

                // Find local product by POS reference
                $mapping = ProductPosMapping::query()
                    ->where('pos_item_id', $posRef)
                    ->first();

                if (! $mapping) {
                    continue;
                }

                $product = $mapping->product;

                if (! $product) {
                    continue;
                }

                // Compare prices
                $localBasePrice = (float) $product->base_price;
                $localDiscountPrice = (float) $product->price_after_discount;
                $masterBasePrice = (float) $masterProduct['price'];
                $masterDiscountPrice = (float) ($masterProduct['priceAfterDiscount'] ?? $masterProduct['price']);
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
        }

        return $productsWithChanges;
    }

    /**
     * Import new products from master repository
     *
     * @param  array<int>  $productIds
     * @return array{imported: int, failed: int, errors: array<string>}
     */
    public function importProducts(array $productIds): array
    {
        $masterProducts = $this->getProductsByIds($productIds);

        $imported = 0;
        $failed = 0;
        $errors = [];

        foreach ($masterProducts as $masterProduct) {
            try {
                // Create or find category
                $categoryData = $masterProduct['category'];
                $category = Category::query()->firstOrCreate(
                    ['name' => $categoryData['name']],
                    [
                        'description' => null,
                        'image' => $categoryData['image'] ?? null,
                        'is_active' => true,
                    ]
                );

                // Create product
                $product = Product::query()->create([
                    'name' => $masterProduct['name'],
                    'description' => null,
                    'image' => '/images/default-product.png',
                    'base_price' => $masterProduct['price'],
                    'price_after_discount' => $masterProduct['priceAfterDiscount'] ?? $masterProduct['price'],
                    'category_id' => $category->id,
                    'is_active' => true,
                    'sell_by_weight' => false,
                ]);

                // Create POS mapping (without branch_id for now)
                ProductPosMapping::query()->create([
                    'product_id' => $product->id,
                    'pos_item_id' => $masterProduct['productRef'] ?? $masterProduct['posRef'],
                    'branch_id' => null,
                ]);

                $imported++;
            } catch (Exception $e) {
                $failed++;
                $errors[] = "Failed to import product {$masterProduct['name']}: {$e->getMessage()}";
                Log::error('Failed to import product', [
                    'product' => $masterProduct,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'imported' => $imported,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * Update product prices from master repository
     *
     * @param  array<int>  $productIds
     * @return array{updated: int, failed: int, errors: array<string>}
     */
    public function updateProductPrices(array $productIds): array
    {
        $masterProducts = $this->getProductPricesByIds($productIds);

        $updated = 0;
        $failed = 0;
        $errors = [];

        foreach ($masterProducts as $masterProduct) {
            try {
                $posRef = $masterProduct['productRef'] ?? $masterProduct['posRef'];

                // Find local product by POS reference
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

                // Update product prices
                $product->update([
                    'base_price' => $masterProduct['price'],
                    'price_after_discount' => $masterProduct['priceAfterDiscount'] ?? $masterProduct['price'],
                ]);

                $updated++;
            } catch (Exception $e) {
                $failed++;
                $errors[] = "Failed to update prices for product: {$e->getMessage()}";
                Log::error('Failed to update product prices', [
                    'product' => $masterProduct,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'updated' => $updated,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * Get the master products repository base URL
     */
    private function getMasterRepoUrl(): string
    {
        $url = $this->settingService->get(SettingKey::from('products_repo_link'));

        if (empty($url)) {
            throw new Exception('لا يمكن الاتصال بالنقطة الرئيسية');
        }

        return $url;
    }
}
