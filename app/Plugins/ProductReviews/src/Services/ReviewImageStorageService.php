<?php

declare(strict_types=1);

namespace App\Plugins\ProductReviews\src\Services;

use Illuminate\Http\UploadedFile;

final class ReviewImageStorageService
{
    /**
     * Store uploaded review images and return their public URLs.
     *
     * @param  array<int, UploadedFile>  $images
     * @return array<int, string>
     */
    public function storeImages(array $images): array
    {
        $storedPaths = [];

        foreach ($images as $image) {
            $path = $image->store('reviews', 'public');
            $storedPaths[] = "/storage/{$path}";
        }

        return $storedPaths;
    }
}
