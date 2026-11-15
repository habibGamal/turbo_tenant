<?php

declare(strict_types=1);

use App\Core\Plugins\Routing\PluginRoutes;
use App\Plugins\CartEngine\src\Http\Controllers\Api\CartEngineApiController;
use Illuminate\Support\Facades\Route;

PluginRoutes::tenant('cart-engine')->prefix('api')->group(function () {
    Route::get('/cart', [CartEngineApiController::class, 'index'])->name('api.cart.index');
    Route::post('/cart/items', [CartEngineApiController::class, 'store'])->name('api.cart.store');
    Route::patch('/cart/items/{itemId}', [CartEngineApiController::class, 'update'])->name('api.cart.update');
    Route::delete('/cart/items/{itemId}', [CartEngineApiController::class, 'destroy'])->name('api.cart.destroy');
    Route::delete('/cart', [CartEngineApiController::class, 'clear'])->name('api.cart.clear');
    Route::post('/cart/sync', [CartEngineApiController::class, 'sync'])->name('api.cart.sync');
});
