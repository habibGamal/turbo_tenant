<?php

declare(strict_types=1);

namespace App\Plugins\ProductReviews\src\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\ProductReviews\src\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Product $product): JsonResponse
    {
        $reviews = Review::query()
            ->where('product_id', $product->id)
            ->where('is_published', true)
            ->with('user:id,name')
            ->latest()
            ->paginate(10);

        return response()->json($reviews);
    }

    public function store(Request $request, Product $product): JsonResponse|RedirectResponse
    {
        $isAuthenticated = auth()->check();

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string|max:2000',
            'guest_name' => $isAuthenticated ? 'nullable|string|max:255' : 'required|string|max:255',
            'guest_email' => 'nullable|email|max:255',
            'images.*' => 'image|max:2048',
        ]);

        $images = [];
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $image) {
                $path = $image->store('reviews', 'public');
                $images[] = "/storage/{$path}";
            }
        }

        $review = Review::query()->create([
            'product_id' => $product->id,
            'user_id' => $isAuthenticated ? auth()->id() : null,
            'guest_name' => $isAuthenticated ? null : ($validated['guest_name'] ?? null),
            'guest_email' => $isAuthenticated ? null : ($validated['guest_email'] ?? null),
            'rating' => (int) $validated['rating'],
            'comment' => $validated['comment'],
            'images' => $images,
            'is_published' => true,
            'is_approved' => true,
        ]);

        if ($request->wantsJson() || $request->isJson()) {
            return response()->json([
                'message' => 'Review submitted successfully!',
                'review' => $review->load('user:id,name'),
            ], 201);
        }

        return back()->with('success', 'Review submitted successfully!');
    }
}
