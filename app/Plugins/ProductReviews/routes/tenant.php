<?php

declare(strict_types=1);

use App\Core\Plugins\Routing\PluginRoutes;
use App\Plugins\ProductReviews\src\Http\Controllers\Api\ProductReviewsApiController;
use Illuminate\Support\Facades\Route;

PluginRoutes::tenant('product-reviews')->prefix('api/products/{product}/reviews')->name('reviews.')->group(function () {
    Route::get('/', [ProductReviewsApiController::class, 'index'])->name('index');
    Route::post('/', [ProductReviewsApiController::class, 'store'])->name('store');
});
