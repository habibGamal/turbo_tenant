<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use Inertia\Inertia;
use Inertia\Response;

final class HomeController extends Controller
{
    public function index(): Response
    {
        // Get categories with their products
        $categories = Category::query()
            ->orderBy('name')
            ->get(['id', 'name',  'description']);

        // Get featured products (using active products with discounts as featured)
        $featuredProducts = Product::query()
            ->with('category:id,name')
            ->where('is_active', true)
            ->whereNotNull('price_after_discount')
            ->limit(8)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'price' => $product->price_after_discount ?? $product->base_price,
                    'category' => $product->category?->name ?? 'Uncategorized',
                    'rating' => 4.5, // You can add actual rating logic
                ];
            });

        // Get popular products (active products)
        $popularProducts = Product::query()
            ->with('category:id,name')
            ->where('is_active', true)
            ->limit(6)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'price' => $product->price_after_discount ?? $product->base_price,
                    'category' => $product->category?->name ?? 'Uncategorized',
                ];
            });

        return Inertia::render('HomePage', [
            'categories' => $categories,
            'featuredProducts' => $featuredProducts,
            'popularProducts' => $popularProducts,
            'tenant' => [
                'name' => tenant('id'),
                'theme' => 'default', // You can store this in tenant data
            ],
        ]);
    }
}
