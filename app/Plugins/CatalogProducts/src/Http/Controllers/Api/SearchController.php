<?php

declare(strict_types=1);

namespace App\Plugins\CatalogProducts\src\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Plugins\CatalogProducts\src\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SearchController extends Controller
{
    public function suggestions(Request $request): JsonResponse
    {
        $query = (string) $request->get('query', '');

        if (mb_strlen($query) < 2) {
            return response()->json([]);
        }

        $suggestions = Product::query()
            ->with('category:id,name,name_ar')
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('name_ar', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            })
            ->limit(5)
            ->get();

        return response()->json($suggestions);
    }
}
