<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Http\Controllers\Api;

use App\Core\Plugins\Services\PluginManager;
use App\Http\Controllers\Controller;
use App\Plugins\CatalogProducts\src\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProductController extends Controller
{
    public function __construct(
        private readonly PluginManager $pluginManager
    ) {}

    public function show(Product $product): JsonResponse
    {
        // Load product with all relationships
        $product->load([
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
        ]);

        $reviewsCount = 0;
        $rating = 0.0;
        if ($this->pluginManager->isPluginActive('product-reviews')) {
            $approvedReviews = $product->reviews()
                ->where('is_approved', true)
                ->where('is_published', true);
            $reviewsCount = $approvedReviews->count();
            if ($reviewsCount > 0) {
                $rating = round((float) $approvedReviews->avg('rating'), 1);
            }
        }
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
            'variants' => $product->variants,
            'extraOption' => $product->extraOption ? [
                'id' => $product->extraOption->id,
                'name' => $product->extraOption->name,
                'name_ar' => $product->extraOption->name_ar,
                'description' => $product->extraOption->description,
                'min_selections' => $product->min_selections ?? $product->extraOption->min_selections,
                'max_selections' => $product->max_selections ?? $product->extraOption->max_selections,
                'allow_multiple' => $product->allow_multiple !== null ? (bool) $product->allow_multiple : (bool) $product->extraOption->allow_multiple,
                'items' => $product->extraOption->items,
            ] : null,
            'min_selections' => $product->min_selections,
            'max_selections' => $product->max_selections,
            'allow_multiple' => $product->allow_multiple,
            'weight_option' => $product->weightOption ? [
                'id' => $product->weightOption->id,
                'name' => $product->weightOption->name,
                'unit' => $product->weightOption->unit,
                'values' => $product->weightOption->values,
            ] : null,
            'rating' => $rating,
            'reviewsCount' => $reviewsCount,
        ];

        return response()->json([
            'product' => $productData,
        ]);
    }

    public function getByIds(Request $request): JsonResponse
    {
        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return response()->json([]);
        }

        $products = Product::whereIn('id', $ids)
            ->where('is_active', true)
            ->with(['category:id,name,name_ar'])
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'name_ar' => $product->name_ar,
                    'description' => $product->description,
                    'image' => $product->image,
                    'price' => $product->price_after_discount ?? $product->base_price,
                    'base_price' => $product->base_price,
                    'price_after_discount' => $product->price_after_discount,
                    'category' => $product->category?->name,
                    'rating' => 4.5, // Placeholder
                ];
            });

        return response()->json($products);
    }
}
