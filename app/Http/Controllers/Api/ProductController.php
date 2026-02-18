<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

final class ProductController extends Controller
{
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

        $reviews = $product->reviews()
            ->with('user:id,name')
            ->latest()
            ->get()
            ->map(function ($review) {
                return [
                    'id' => $review->id,
                    'user_name' => $review->user->name,
                    'rating' => $review->rating,
                    'comment' => $review->comment,
                    'images' => $review->images,
                    'created_at' => $review->created_at->toISOString(),
                ];
            });

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
                'min_selections' => $product->extraOption->min_selections,
                'max_selections' => $product->extraOption->max_selections,
                'allow_multiple' => $product->extraOption->allow_multiple,
                'items' => $product->extraOption->items,
            ] : null,
            'weight_option' => $product->weightOption ? [
                'id' => $product->weightOption->id,
                'name' => $product->weightOption->name,
                'unit' => $product->weightOption->unit,
                'values' => $product->weightOption->values,
            ] : null,
            'rating' => $reviews->avg('rating') ?? 0,
            'reviewsCount' => $reviews->count(),
        ];

        return response()->json([
            'product' => $productData,
            'reviews' => $reviews,
        ]);
    }

    public function getByIds(\Illuminate\Http\Request $request): JsonResponse
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
