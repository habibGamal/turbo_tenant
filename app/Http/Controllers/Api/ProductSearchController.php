<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProductSearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = $request->get('q', '');
        $limit = min((int) $request->get('limit', 5), 10);

        if (mb_strlen($query) < 2) {
            return response()->json([]);
        }

        $products = Product::query()
            ->with('category:id,name')
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            })
            ->limit($limit)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'description' => $product->description,
                    'image' => $product->image,
                    'price' => $product->price_after_discount ?? $product->base_price,
                    'category' => $product->category ? [
                        'id' => $product->category->id,
                        'name' => $product->category->name,
                    ] : null,
                ];
            });

        return response()->json($products);
    }
}
