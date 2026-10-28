<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\Section;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CatalogProductsApiController extends Controller
{
    /**
     * Display a listing of products with filtering, search, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', 12), 50);

        $search = (string) $request->get('search', '');
        $categories = $request->get('category', []);
        if (! is_array($categories) && $categories) {
            $categories = [$categories];
        }
        $minPrice = $request->get('min_price');
        $maxPrice = $request->get('max_price');
        $sortBy = (string) $request->get('sort_by', 'name');
        $sortOrder = (string) $request->get('sort_order', 'asc');

        $query = Product::query()
            ->with(['category:id,name,name_ar', 'variants'])
            ->withCount('variants')
            ->where('is_active', true);

        // Text search
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('name_ar', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Category filter
        if (! empty($categories)) {
            $query->whereHas('category', function ($q) use ($categories) {
                $q->whereIn('name', $categories)
                    ->orWhereIn('name_ar', $categories)
                    ->orWhereIn('id', $categories);
            });
        }

        // Price range
        if ($minPrice !== null && $minPrice !== '') {
            $min = (float) $minPrice;
            $query->where(function ($q) use ($min) {
                $q->where('price_after_discount', '>=', $min)
                    ->orWhere(function ($sq) use ($min) {
                        $sq->whereNull('price_after_discount')
                            ->where('base_price', '>=', $min);
                    });
            });
        }

        if ($maxPrice !== null && $maxPrice !== '') {
            $max = (float) $maxPrice;
            $query->where(function ($q) use ($max) {
                $q->where('price_after_discount', '<=', $max)
                    ->orWhere(function ($sq) use ($max) {
                        $sq->whereNull('price_after_discount')
                            ->where('base_price', '<=', $max);
                    });
            });
        }

        // Sorting
        $validSortColumns = ['name', 'base_price', 'created_at', 'id'];
        $sortColumn = in_array($sortBy, $validSortColumns, true) ? $sortBy : 'name';
        $sortDirection = mb_strtolower($sortOrder) === 'desc' ? 'desc' : 'asc';
        $query->orderBy($sortColumn, $sortDirection);

        $paginator = $query->paginate($perPage);

        $items = $paginator->getCollection()->map(function (Product $product) {
            return $this->formatProductSummary($product);
        });

        return response()->json([
            'status' => 'success',
            'data' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'filters' => [
                'search' => $search,
                'category' => $categories,
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder,
            ],
        ]);
    }

    /**
     * Display a specific product with full details and related items.
     */
    public function show(int|string $id): JsonResponse
    {
        $product = Product::query()
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
            return response()->json([
                'status' => 'error',
                'message' => 'Product not found or unavailable.',
            ], 404);
        }

        // Reviews calculation if model relationship exists
        $reviews = collect();
        if (method_exists($product, 'reviews')) {
            $reviews = $product->reviews()
                ->with('user:id,name')
                ->latest()
                ->limit(10)
                ->get()
                ->map(function ($review) {
                    return [
                        'id' => $review->id,
                        'user_name' => $review->user?->name ?? 'Customer',
                        'rating' => $review->rating,
                        'comment' => $review->comment,
                        'images' => $review->images,
                        'created_at' => $review->created_at?->toISOString(),
                    ];
                });
        }

        // Related products in same category
        $relatedProducts = Product::query()
            ->with('category:id,name,name_ar')
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('is_active', true)
            ->limit(4)
            ->get()
            ->map(fn (Product $p) => $this->formatProductSummary($p));

        // Promotional products
        $promotionalProducts = Product::query()
            ->with('category:id,name,name_ar')
            ->where('is_active', true)
            ->whereNotNull('price_after_discount')
            ->where('id', '!=', $product->id)
            ->limit(4)
            ->get()
            ->map(fn (Product $p) => $this->formatProductSummary($p));

        $productData = [
            'id' => $product->id,
            'name' => $product->name,
            'name_ar' => $product->name_ar,
            'description' => $product->description,
            'image' => $product->image,
            'base_price' => $product->base_price,
            'price_after_discount' => $product->price_after_discount,
            'price' => $product->price_after_discount ?? $product->base_price,
            'is_active' => $product->is_active,
            'sell_by_weight' => $product->sell_by_weight,
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'name_ar' => $product->category->name_ar,
                'description' => $product->category->description,
            ] : null,
            'variants' => $product->variants->map(fn ($variant) => [
                'id' => $variant->id,
                'name' => $variant->name,
                'name_ar' => $variant->name_ar,
                'price' => $variant->price !== null ? (float) $variant->price : null,
                'is_available' => (bool) $variant->is_available,
                'sort_order' => $variant->sort_order,
            ]),
            'extra_option' => $product->extraOption ? [
                'id' => $product->extraOption->id,
                'name' => $product->extraOption->name,
                'name_ar' => $product->extraOption->name_ar,
                'description' => $product->extraOption->description,
                'min_selections' => $product->min_selections ?? $product->extraOption->min_selections,
                'max_selections' => $product->max_selections ?? $product->extraOption->max_selections,
                'allow_multiple' => $product->allow_multiple !== null ? (bool) $product->allow_multiple : (bool) $product->extraOption->allow_multiple,
                'items' => $product->extraOption->items->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'name_ar' => $item->name_ar,
                    'price' => (float) $item->price,
                    'is_default' => (bool) $item->is_default,
                    'sort_order' => $item->sort_order,
                    'allow_quantity' => (bool) $item->allow_quantity,
                ]),
            ] : null,
            'min_selections' => $product->min_selections,
            'max_selections' => $product->max_selections,
            'allow_multiple' => $product->allow_multiple,
            'weight_option' => $product->weightOption ? [
                'id' => $product->weightOption->id,
                'name' => $product->weightOption->name,
                'unit' => $product->weightOption->unit,
                'values' => $product->weightOption->values->map(fn ($val) => [
                    'id' => $val->id,
                    'value' => (float) $val->value,
                    'label' => $val->label,
                    'sort_order' => $val->sort_order,
                ]),
            ] : null,
            'rating' => $reviews->isNotEmpty() ? round((float) $reviews->avg('rating'), 1) : 4.5,
            'reviews_count' => $reviews->count(),
        ];

        return response()->json([
            'status' => 'success',
            'data' => $productData,
            'product' => $productData,
            'reviews' => $reviews,
            'related_products' => $relatedProducts,
            'promotional_products' => $promotionalProducts,
        ]);
    }

    /**
     * Display active storefront sections with their assigned products.
     */
    public function sections(Request $request): JsonResponse
    {
        $sections = Section::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with(['products' => function ($query) {
                $query->where('is_active', true)
                    ->with(['category:id,name,name_ar', 'variants'])
                    ->withCount('variants');
            }])
            ->get()
            ->map(function (Section $section) {
                return [
                    'id' => $section->id,
                    'title' => $section->title,
                    'title_ar' => $section->title_ar,
                    'location' => $section->location,
                    'sort_order' => $section->sort_order,
                    'products' => $section->products->map(fn (Product $p) => $this->formatProductSummary($p)),
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $sections,
        ]);
    }

    /**
     * Display active categories list.
     */
    public function categories(Request $request): JsonResponse
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'name_ar', 'description', 'image', 'sort_order']);

        return response()->json([
            'status' => 'success',
            'data' => $categories,
        ]);
    }

    /**
     * Search suggestions for fast autocompletion.
     */
    public function suggestions(Request $request): JsonResponse
    {
        $query = (string) $request->get('q', $request->get('query', ''));
        $limit = min((int) $request->get('limit', 5), 10);

        if (mb_strlen($query) < 2) {
            return response()->json([
                'status' => 'success',
                'data' => [],
            ]);
        }

        $suggestions = Product::query()
            ->with('category:id,name,name_ar')
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('name_ar', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            })
            ->limit($limit)
            ->get()
            ->map(fn (Product $p) => $this->formatProductSummary($p));

        return response()->json([
            'status' => 'success',
            'data' => $suggestions,
        ]);
    }

    /**
     * Retrieve products by a list of IDs.
     */
    public function byIds(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);
        if (empty($ids) || ! is_array($ids)) {
            return response()->json([
                'status' => 'success',
                'data' => [],
            ]);
        }

        $products = Product::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->with(['category:id,name,name_ar'])
            ->get()
            ->map(fn (Product $p) => $this->formatProductSummary($p));

        return response()->json([
            'status' => 'success',
            'data' => $products,
        ]);
    }

    /**
     * Standardized summary format for a product card / list item.
     */
    private function formatProductSummary(Product $product): array
    {
        $hasVariants = isset($product->variants_count)
            ? $product->variants_count > 0
            : ($product->relationLoaded('variants') ? $product->variants->isNotEmpty() : $product->variants()->exists());

        $hasExtras = ! empty($product->extra_option_id);

        return [
            'id' => $product->id,
            'name' => $product->name,
            'name_ar' => $product->name_ar,
            'description' => $product->description,
            'image' => $product->image,
            'base_price' => (float) $product->base_price,
            'price_after_discount' => $product->price_after_discount !== null ? (float) $product->price_after_discount : null,
            'price' => (float) ($product->price_after_discount ?? $product->base_price),
            'is_active' => (bool) $product->is_active,
            'sell_by_weight' => (bool) $product->sell_by_weight,
            'has_variants' => (bool) $hasVariants,
            'has_extras' => (bool) $hasExtras,
            'has_options' => (bool) ($hasVariants || $hasExtras || $product->sell_by_weight),
            'category' => $product->category ? [
                'id' => $product->category->id,
                'name' => $product->category->name,
                'name_ar' => $product->category->name_ar,
            ] : null,
            'rating' => 4.5,
        ];
    }
}
