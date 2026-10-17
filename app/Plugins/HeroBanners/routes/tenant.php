<?php

declare(strict_types=1);

use App\Plugins\HeroBanners\src\Http\Controllers\HeroSliderController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/api/hero-sliders', [HeroSliderController::class, 'index'])->name('api.hero-sliders.index');
});
