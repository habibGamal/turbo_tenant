<?php

declare(strict_types=1);

use App\Core\Plugins\Routing\PluginRoutes;
use App\Plugins\HeroBanners\src\Http\Controllers\Api\HeroBannersApiController;
use Illuminate\Support\Facades\Route;

PluginRoutes::tenant('hero-banners')->prefix('api')->group(function () {
    Route::get('/hero-banners', [HeroBannersApiController::class, 'index'])->name('api.hero-banners.index');
    // Backward compatibility for existing endpoints and tests
    Route::get('/hero-sliders', [HeroBannersApiController::class, 'index'])->name('api.hero-sliders.index');
});
