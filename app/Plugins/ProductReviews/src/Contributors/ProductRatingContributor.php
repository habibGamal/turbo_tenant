<?php

declare(strict_types=1);

namespace App\Plugins\ProductReviews\src\Contributors;

use App\Plugins\CatalogProducts\src\Contracts\ProductDetailsContributor;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\ProductReviews\src\Models\Review;

final class ProductRatingContributor implements ProductDetailsContributor
{
    public function pluginId(): string
    {
        return 'product-reviews';
    }

    /**
     * @return array<string, mixed>
     */
    public function contribute(Product $product): array
    {
        $approvedReviews = Review::query()
            ->where('product_id', $product->id)
            ->where('is_approved', true)
            ->where('is_published', true);

        $reviewsCount = $approvedReviews->count();
        $rating = null;

        if ($reviewsCount > 0) {
            $rating = round((float) $approvedReviews->avg('rating'), 1);
        }

        return [
            'rating' => $rating,
            'reviews_count' => $reviewsCount,
        ];
    }
}
