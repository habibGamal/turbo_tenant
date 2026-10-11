<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Plugins\CatalogProducts\src\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function show(Product $product): Response
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
        // Get related products from the same category
        $relatedProducts = Product::query()
            ->with('category:id,name,name_ar')
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('is_active', true)
            ->limit(4)
            ->get()
            ->map(function ($relatedProduct) {
                return [
                    'id' => $relatedProduct->id,
                    'name' => $relatedProduct->name,
                    'name_ar' => $relatedProduct->name_ar,
                    'description' => $relatedProduct->description,
                    'image' => $relatedProduct->image,
                    'price' => $relatedProduct->price_after_discount ?? $relatedProduct->base_price,
                    'base_price' => $relatedProduct->base_price,
                    'price_after_discount' => $relatedProduct->price_after_discount,
                    'category' => $relatedProduct->category,
                    'rating' => 4.5,
                ];
            });

        // Get promotional products (products with discounts)
        $promotionalProducts = Product::query()
            ->with('category:id,name,name_ar')
            ->where('is_active', true)
            ->whereNotNull('price_after_discount')
            ->where('id', '!=', $product->id)
            ->limit(4)
            ->get()
            ->map(function ($promoProduct) {
                return [
                    'id' => $promoProduct->id,
                    'name' => $promoProduct->name,
                    'name_ar' => $promoProduct->name_ar,
                    'description' => $promoProduct->description,
                    'image' => $promoProduct->image,
                    'price' => $promoProduct->price_after_discount ?? $promoProduct->base_price,
                    'base_price' => $promoProduct->base_price,
                    'price_after_discount' => $promoProduct->price_after_discount,
                    'category' => $promoProduct->category,
                    'rating' => 4.5,
                ];
            });

        // Format product data
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
            'variants' => $product->variants->map(function ($variant) {
                return [
                    'id' => $variant->id,
                    'name' => $variant->name,
                    'name_ar' => $variant->name_ar,
                    'price' => $variant->price,
                    'is_available' => $variant->is_available,
                    'sort_order' => $variant->sort_order,
                ];
            }),
            'extra_option' => $product->extraOption ? [
                'id' => $product->extraOption->id,
                'name' => $product->extraOption->name,
                'name_ar' => $product->extraOption->name_ar,
                'description' => $product->extraOption->description,
                'min_selections' => $product->extraOption->min_selections,
                'max_selections' => $product->extraOption->max_selections,
                'allow_multiple' => $product->extraOption->allow_multiple,
                'items' => $product->extraOption->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'name_ar' => $item->name_ar,
                        'price' => $item->price,
                        'is_default' => $item->is_default,
                        'sort_order' => $item->sort_order,
                        'allow_quantity' => $item->allow_quantity,
                    ];
                }),
            ] : null,
            'weight_option' => $product->weightOption ? [
                'id' => $product->weightOption->id,
                'name' => $product->weightOption->name,
                'unit' => $product->weightOption->unit,
                'values' => $product->weightOption->values->map(function ($value) {
                    return [
                        'id' => $value->id,
                        'value' => (float) $value->value,
                        'label' => $value->label,
                        'sort_order' => $value->sort_order,
                    ];
                }),
            ] : null,
        ];

        return Inertia::render('ProductShow', [
            'product' => $productData,
            'relatedProducts' => $relatedProducts,
            'promotionalProducts' => $promotionalProducts,
        ]);
    }
}
