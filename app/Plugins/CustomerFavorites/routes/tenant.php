<?php

declare(strict_types=1);

use App\Plugins\CustomerFavorites\src\Http\Controllers\Api\CustomerFavoritesApiController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    'plugin.active:customer-favorites',
])->prefix('api/customer-favorites')->group(function () {
    Route::get('/', [CustomerFavoritesApiController::class, 'index'])->name('api.customer-favorites.index');
    Route::post('/sync', [CustomerFavoritesApiController::class, 'sync'])->name('api.customer-favorites.sync');
    Route::post('/', [CustomerFavoritesApiController::class, 'store'])->name('api.customer-favorites.store');
});
