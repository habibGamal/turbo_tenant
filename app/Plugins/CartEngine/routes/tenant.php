<?php

declare(strict_types=1);

use App\Plugins\CartEngine\src\Http\Controllers\CartController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
    Route::post('/cart/items', [CartController::class, 'store'])->name('cart.store');
    Route::patch('/cart/items/{itemId}', [CartController::class, 'update'])->name('cart.update');
    Route::delete('/cart/items/{itemId}', [CartController::class, 'destroy'])->name('cart.destroy');
    Route::delete('/cart', [CartController::class, 'clear'])->name('cart.clear');
    Route::post('/cart/sync', [CartController::class, 'sync'])->name('cart.sync');
});
