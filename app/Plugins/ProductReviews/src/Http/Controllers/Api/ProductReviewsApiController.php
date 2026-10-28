<?php

declare(strict_types=1);

namespace App\Plugins\ProductReviews\src\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\ProductReviews\src\Models\Review;
use App\Plugins\StoreSettings\src\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProductReviewsApiController extends Controller
{
    public function __construct(
        private readonly SettingService $settingService
    ) {}

    /**
     * Display a paginated listing of approved reviews for a product.
     */
    public function index(Product $product): JsonResponse
    {
        $reviews = Review::query()
            ->where('product_id', $product->id)
            ->where('is_approved', true)
            ->where('is_published', true)
            ->with('user:id,name')
            ->latest()
            ->paginate(10);

        return response()->json([
            'status' => 'success',
            'data' => $reviews->items(),
            'total' => $reviews->total(),
            'current_page' => $reviews->currentPage(),
            'last_page' => $reviews->lastPage(),
        ]);
    }

    /**
     * Store a newly created review in storage.
     */
    public function store(Request $request, Product $product): JsonResponse
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

        $autoApproveSetting = $this->settingService->get(Review::SETTING_AUTO_APPROVE, false);
        $autoApprove = filter_var($autoApproveSetting, FILTER_VALIDATE_BOOLEAN);

        $review = Review::query()->create([
            'product_id' => $product->id,
            'user_id' => $isAuthenticated ? auth()->id() : null,
            'guest_name' => $isAuthenticated ? null : ($validated['guest_name'] ?? null),
            'guest_email' => $isAuthenticated ? null : ($validated['guest_email'] ?? null),
            'rating' => (int) $validated['rating'],
            'comment' => $validated['comment'],
            'images' => $images,
            'is_published' => $autoApprove,
            'is_approved' => $autoApprove,
        ]);

        $message = $autoApprove
            ? 'Review submitted successfully!'
            : 'Review submitted successfully and is awaiting approval.';

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'review' => $review->load('user:id,name'),
        ], 201);
    }
}
