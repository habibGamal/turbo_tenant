<?php

declare(strict_types=1);

namespace App\Plugins\ProductReviews\src\Http\Controllers\Api;

use App\Core\Contracts\SettingsRepository;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\ProductReviews\src\Http\Requests\StoreReviewRequest;
use App\Plugins\ProductReviews\src\Http\Resources\ReviewResource;
use App\Plugins\ProductReviews\src\Models\Review;
use App\Plugins\ProductReviews\src\Services\ReviewImageStorageService;
use Illuminate\Http\JsonResponse;

final class ProductReviewsApiController extends Controller
{
    public function __construct(
        private readonly SettingsRepository $settingService,
        private readonly ReviewImageStorageService $imageStorageService,
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

        return ApiResponse::paginated($reviews, ReviewResource::class);
    }

    /**
     * Store a newly created review in storage.
     */
    public function store(StoreReviewRequest $request, Product $product): JsonResponse
    {
        $isAuthenticated = $request->user() !== null;
        $validated = $request->validated();

        $images = $request->hasFile('images')
            ? $this->imageStorageService->storeImages($request->file('images'))
            : [];

        $autoApproveSetting = $this->settingService->get(Review::SETTING_AUTO_APPROVE, false);
        $autoApprove = filter_var($autoApproveSetting, FILTER_VALIDATE_BOOLEAN);

        $review = Review::query()->create([
            'product_id' => $product->id,
            'user_id' => $isAuthenticated ? $request->user()->id : null,
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

        $review->load('user:id,name');

        return ApiResponse::success(new ReviewResource($review), $message, 201);
    }
}
