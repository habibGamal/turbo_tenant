<?php

declare(strict_types=1);

use App\Plugins\CatalogProducts\src\Http\Controllers\Api\ProductController as ApiProductController;
use App\Plugins\CatalogProducts\src\Http\Controllers\Api\ProductSearchController;
use App\Plugins\CatalogProducts\src\Http\Controllers\Api\SearchController;
use App\Plugins\CatalogProducts\src\Http\Controllers\MenuController;
use App\Plugins\CatalogProducts\src\Http\Controllers\ProductController;
use App\Plugins\CatalogProducts\src\Http\Controllers\SectionController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
    Route::get('/menu', [MenuController::class, 'index'])->name('menu');
    Route::get('/sections/{section}', [SectionController::class, 'show'])->name('sections.show');

    // Catalog API routes
    Route::get('/api/products/by-ids', [ApiProductController::class, 'getByIds'])->name('api.products.by-ids');
    Route::get('/api/search/suggestions', [SearchController::class, 'suggestions'])->name('api.search.suggestions');
    Route::get('/api/products/{product}', [ApiProductController::class, 'show'])->name('api.products.show');
    Route::get('/api/products/search', ProductSearchController::class)->name('api.products.search');
});
