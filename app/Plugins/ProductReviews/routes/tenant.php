<?php

declare(strict_types=1);

use App\Plugins\ProductReviews\src\Http\Controllers\Api\ProductReviewsApiController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    'plugin.active:product-reviews',
])->prefix('api/products/{product}/reviews')->name('reviews.')->group(function () {
    Route::get('/', [ProductReviewsApiController::class, 'index'])->name('index');
    Route::post('/', [ProductReviewsApiController::class, 'store'])->name('store');
});
