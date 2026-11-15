<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Http\Controllers\Api;

use App\Core\Plugins\Services\PluginContributions;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Plugins\CatalogProducts\src\Http\Requests\ProductIndexRequest;
use App\Plugins\CatalogProducts\src\Http\Requests\ProductsByIdsRequest;
use App\Plugins\CatalogProducts\src\Http\Requests\ProductSuggestionsRequest;
use App\Plugins\CatalogProducts\src\Http\Resources\ProductResource;
use App\Plugins\CatalogProducts\src\Http\Resources\ProductSummaryResource;
use App\Plugins\CatalogProducts\src\Models\Product;
use Illuminate\Http\JsonResponse;

final class CatalogProductsApiController extends Controller
{
    public const int PRODUCTS_PER_SECTION = \App\Plugins\CatalogProducts\src\Services\StorefrontData::PRODUCTS_PER_SECTION;

    public function __construct(
        private readonly PluginContributions $pluginContributions
    ) {}

    /**
     * Display a listing of products with filtering, search, and pagination.
     */
    public function index(ProductIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 12);
        $sortBy = (string) ($validated['sort_by'] ?? 'name');
        $sortOrder = mb_strtolower((string) ($validated['sort_order'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        $query = Product::query()
            ->withCount('variants')
            ->with(['category:id,name,name_ar'])
            ->where('is_active', true)
            ->search($validated['search'] ?? null)
            ->filter($validated);

        if ($sortBy === 'price') {
            $query->orderByRaw('COALESCE(price_after_discount, base_price) '.$sortOrder);
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        $paginator = $query->paginate($perPage);

        return ApiResponse::paginated($paginator, ProductSummaryResource::class);
    }

    /**
     * Display a specific product with full details and related items.
     */
    public function show(int|string $id): JsonResponse
    {
        $product = Product::query()
            ->withCount('variants')
            ->with([
                'category:id,name,name_ar,description',
                'variants' => function ($query) {
                    $query->where('is_available', true)->orderBy('sort_order');
                },
                'extraOption.items' => function ($query) {
                    $query->orderBy('sort_order');
                },
                'weightOption.values' => function ($query) {
                    $query->orderBy('sort_order');
                },
            ])
            ->where('is_active', true)
            ->where('id', $id)
            ->first();

        if (! $product) {
            abort(404, 'Product not found or unavailable.');
        }

        $relatedProducts = Product::query()
            ->withCount('variants')
            ->with('category:id,name,name_ar')
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('is_active', true)
            ->limit(4)
            ->get();

        $promotionalProducts = Product::query()
            ->withCount('variants')
            ->with('category:id,name,name_ar')
            ->where('is_active', true)
            ->whereNotNull('price_after_discount')
            ->where('id', '!=', $product->id)
            ->orderByDesc('updated_at')
            ->limit(4)
            ->get();

        $productData = (new ProductResource($product, $this->pluginContributions))->resolve();
        $productData['related_products'] = ProductSummaryResource::collection($relatedProducts)->resolve();
        $productData['promotional_products'] = ProductSummaryResource::collection($promotionalProducts)->resolve();

        return ApiResponse::success($productData);
    }

    /**
     * Display active storefront sections with their assigned products.
     */
    public function sections(): JsonResponse
    {
        return ApiResponse::success(app(\App\Plugins\CatalogProducts\src\Services\StorefrontData::class)->sections());
    }

    /**
     * Display active categories list.
     */
    public function categories(): JsonResponse
    {
        return ApiResponse::success(app(\App\Plugins\CatalogProducts\src\Services\StorefrontData::class)->categories());
    }

    /**
     * Search suggestions for fast autocompletion.
     */
    public function suggestions(ProductSuggestionsRequest $request): JsonResponse
    {
        $query = (string) $request->validated('q');
        $limit = (int) $request->validated('limit', 5);

        if (mb_strlen($query) < 2) {
            return ApiResponse::success([]);
        }

        $suggestions = Product::query()
            ->withCount('variants')
            ->with('category:id,name,name_ar')
            ->where('is_active', true)
            ->search($query)
            ->limit($limit)
            ->get();

        return ApiResponse::success(ProductSummaryResource::collection($suggestions));
    }

    /**
     * Retrieve products by a list of IDs.
     */
    public function byIds(ProductsByIdsRequest $request): JsonResponse
    {
        $ids = $request->validated('ids');

        $products = Product::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->withCount('variants')
            ->with(['category:id,name,name_ar'])
            ->get();

        return ApiResponse::success(ProductSummaryResource::collection($products));
    }
}
