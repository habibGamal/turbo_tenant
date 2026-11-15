<?php

declare(strict_types=1);

use App\Core\Plugins\Routing\PluginRoutes;
use App\Plugins\CustomerFavorites\src\Http\Controllers\Api\CustomerFavoritesApiController;
use Illuminate\Support\Facades\Route;

PluginRoutes::tenant('customer-favorites')->prefix('api/customer-favorites')->group(function () {
    Route::get('/', [CustomerFavoritesApiController::class, 'index'])->name('api.customer-favorites.index');
    Route::post('/sync', [CustomerFavoritesApiController::class, 'sync'])->name('api.customer-favorites.sync');
    Route::post('/', [CustomerFavoritesApiController::class, 'store'])->name('api.customer-favorites.store');
});
