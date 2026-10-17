<?php

declare(strict_types=1);

use App\Plugins\ProductReviews\src\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/products/{product}/reviews', [ReviewController::class, 'index'])->name('reviews.index');
    Route::post('/products/{product}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
});
