<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

final class ProductController extends Controller
{
    public function show(Product $product): Response
    {
        // Load product with all relationships
        $product->load([
            'category:id,name,description',
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

        // Get related products from the same category
        $relatedProducts = Product::query()
            ->with('category:id,name')
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('is_active', true)
            ->limit(4)
            ->get()
            ->map(function ($relatedProduct) {
                return [
                    'id' => $relatedProduct->id,
                    'name' => $relatedProduct->name,
                    'description' => $relatedProduct->description,
                    'image' => $relatedProduct->image,
                    'price' => $relatedProduct->price_after_discount ?? $relatedProduct->base_price,
                    'base_price' => $relatedProduct->base_price,
                    'price_after_discount' => $relatedProduct->price_after_discount,
                    'category' => $relatedProduct->category?->name,
                    'rating' => 4.5, // TODO: Add actual rating from reviews
                ];
            });

        // Get promotional products (products with discounts)
        $promotionalProducts = Product::query()
            ->with('category:id,name')
            ->where('is_active', true)
            ->whereNotNull('price_after_discount')
            ->where('id', '!=', $product->id)
            ->limit(4)
            ->get()
            ->map(function ($promoProduct) {
                return [
                    'id' => $promoProduct->id,
                    'name' => $promoProduct->name,
                    'description' => $promoProduct->description,
                    'image' => $promoProduct->image,
                    'price' => $promoProduct->price_after_discount,
                    'base_price' => $promoProduct->base_price,
                    'price_after_discount' => $promoProduct->price_after_discount,
                    'category' => $promoProduct->category?->name,
                    'rating' => 4.5,
                ];
            });

        // Mock reviews data - TODO: Implement actual reviews model
        $reviews = [
            [
                'id' => 1,
                'user_name' => 'John Doe',
                'rating' => 5,
                'comment' => 'Absolutely delicious! Best I\'ve ever had.',
                'created_at' => now()->subDays(2)->toISOString(),
            ],
            [
                'id' => 2,
                'user_name' => 'Jane Smith',
                'rating' => 4,
                'comment' => 'Very good, will order again.',
                'created_at' => now()->subDays(5)->toISOString(),
            ],
            [
                'id' => 3,
                'user_name' => 'Ahmed Ali',
                'rating' => 5,
                'comment' => 'Perfect taste and presentation!',
                'created_at' => now()->subDays(7)->toISOString(),
            ],
        ];

        $productData = [
            'id' => $product->id,
            'name' => $product->name,
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
                'description' => $product->category->description,
            ] : null,
            'variants' => $product->variants,
            'extraOption' => $product->extraOption ? [
                'id' => $product->extraOption->id,
                'name' => $product->extraOption->name,
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
            'rating' => 4.7,
            'reviewsCount' => count($reviews),
        ];

        return Inertia::render('ProductShow', [
            'product' => $productData,
            'reviews' => $reviews,
            'relatedProducts' => $relatedProducts,
            'promotionalProducts' => $promotionalProducts,
        ]);
    }
}
