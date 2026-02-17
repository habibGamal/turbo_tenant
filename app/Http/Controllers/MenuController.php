<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class MenuController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = 12;
        $page = (int) $request->get('page', 1);

        // Get filters
        $search = $request->get('search', '');
        $searchQuery = $request->get('search_query', '');
        $getSuggestions = $request->boolean('get_suggestions', false);
        $categories = $request->get('category', []);
        if (! is_array($categories) && $categories) {
            $categories = [$categories];
        }
        $minPrice = $request->get('min_price');
        $maxPrice = $request->get('max_price');
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');

        // Build query
        $query = Product::query()
            ->with('category:id,name,name_ar')
            ->where('is_active', true);

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('name_ar', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Apply category filter
        if (! empty($categories)) {
            $query->whereHas('category', function ($q) use ($categories) {
                $q->whereIn('name', $categories)
                    ->orWhereIn('name_ar', $categories);
            });
        }

        // Apply price filters
        if ($minPrice !== null) {
            $query->where(function ($q) use ($minPrice) {
                $q->where('price_after_discount', '>=', $minPrice)
                    ->orWhere(function ($sq) use ($minPrice) {
                        $sq->whereNull('price_after_discount')
                            ->where('base_price', '>=', $minPrice);
                    });
            });
        }

        if ($maxPrice !== null) {
            $query->where(function ($q) use ($maxPrice) {
                $q->where('price_after_discount', '<=', $maxPrice)
                    ->orWhere(function ($sq) use ($maxPrice) {
                        $sq->whereNull('price_after_discount')
                            ->where('base_price', '<=', $maxPrice);
                    });
            });
        }

        // Apply sorting
        $validSortColumns = ['name', 'base_price', 'created_at'];
        $sortColumn = in_array($sortBy, $validSortColumns) ? $sortBy : 'name';
        $sortDirection = in_array($sortOrder, ['asc', 'desc']) ? $sortOrder : 'asc';
        $query->orderBy($sortColumn, $sortDirection);

        // Paginate results
        $products = $query->paginate($perPage);

        // Get all categories for filter
        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'name_ar', 'image']);

        // Transform products for frontend
        $products->getCollection()->transform(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'nameAr' => $product->name_ar,
                'description' => $product->description,
                'image' => $product->image,
                'price' => $product->price_after_discount ?? $product->base_price,
                'base_price' => $product->base_price,
                'price_after_discount' => $product->price_after_discount,
                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                    'nameAr' => $product->category->name_ar,
                ] : null,
                'sell_by_weight' => $product->sell_by_weight,
            ];
        });

        // Handle search suggestions for partial reload
        $searchSuggestions = [];
        if ($getSuggestions && mb_strlen($searchQuery) >= 2) {
            $searchSuggestions = Product::query()
                ->with('category:id,name,name_ar')
                ->where('is_active', true)
                ->where(function ($q) use ($searchQuery) {
                    $q->where('name', 'like', "%{$searchQuery}%")
                        ->orWhere('name_ar', 'like', "%{$searchQuery}%")
                        ->orWhere('description', 'like', "%{$searchQuery}%");
                })
                ->limit(5)
                ->get()
                ->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->name,
                        'nameAr' => $product->name_ar,
                        'description' => $product->description,
                        'image' => $product->image,
                        'price' => $product->price_after_discount ?? $product->base_price,
                        'category' => $product->category ? [
                            'id' => $product->category->id,
                            'name' => $product->category->name,
                            'nameAr' => $product->category->name_ar,
                        ] : null,
                    ];
                });
        }

        return Inertia::render('MenuPage', [
            'products' => Inertia::scroll($products),
            'categories' => $categories,
            'searchSuggestions' => $searchSuggestions,
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
}
