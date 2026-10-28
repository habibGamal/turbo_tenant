<?php

declare(strict_types=1);

use App\Plugins\CatalogProducts\src\Http\Controllers\Api\CatalogProductsApiController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    'plugin.active:catalog-products',
])->prefix('api/catalog-products')->group(function () {
    Route::get('/', [CatalogProductsApiController::class, 'index'])->name('api.catalog-products.index');
    Route::get('/sections', [CatalogProductsApiController::class, 'sections'])->name('api.catalog-products.sections');
    Route::get('/categories', [CatalogProductsApiController::class, 'categories'])->name('api.catalog-products.categories');
    Route::get('/suggestions', [CatalogProductsApiController::class, 'suggestions'])->name('api.catalog-products.suggestions');
    Route::get('/by-ids', [CatalogProductsApiController::class, 'byIds'])->name('api.catalog-products.by-ids');
    Route::get('/{id}', [CatalogProductsApiController::class, 'show'])->name('api.catalog-products.show');
});
