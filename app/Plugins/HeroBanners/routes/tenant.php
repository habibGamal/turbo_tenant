<?php

declare(strict_types=1);

use App\Plugins\HeroBanners\src\Http\Controllers\Api\HeroBannersApiController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    'plugin.active:hero-banners',
])->prefix('api')->group(function () {
    Route::get('/hero-banners', [HeroBannersApiController::class, 'index'])->name('api.hero-banners.index');
    // Backward compatibility for existing endpoints and tests
    Route::get('/hero-sliders', [HeroBannersApiController::class, 'index'])->name('api.hero-sliders.index');
});
