<?php

declare(strict_types=1);

namespace App\Plugins\CustomerFavorites\src\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CustomerFavorites\src\DTOs\CustomerFavoritesConfig;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CustomerFavoritesApiController extends Controller
{
    public function __construct(
        private readonly CustomerFavoritesConfig $config
    ) {}

    /**
     * Display a listing of favorite products and configuration.
     */
    public function index(Request $request): JsonResponse
    {
        if (! $this->config->enabled) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'enabled' => false,
                    'count' => 0,
                    'products' => [],
                ],
            ]);
        }

        $rawIds = $request->input('ids', []);
        $ids = [];

        if (is_string($rawIds)) {
            $ids = array_filter(array_map('intval', explode(',', $rawIds)));
        } elseif (is_array($rawIds)) {
            $ids = array_filter(array_map('intval', $rawIds));
        }

        $products = [];
        if (! empty($ids)) {
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
                        'rating' => 4.5,
                        'is_active' => (bool) $product->is_active,
                    ];
                });
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'enabled' => true,
                'count' => count($products),
                'products' => $products,
            ],
        ]);
    }

    /**
     * Sync favorite items.
     */
    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['present', 'array'],
            'ids.*' => ['integer'],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Favorites synced successfully.',
            'data' => [
                'synced_ids' => $validated['ids'],
            ],
        ]);
    }

    /**
     * Store or toggle a favorite item.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer'],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Favorite item processed successfully.',
            'data' => [
                'product_id' => $validated['product_id'],
            ],
        ], 201);
    }
}
