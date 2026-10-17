<?php

declare(strict_types=1);

use App\Plugins\CustomerFavorites\src\Http\Controllers\FavoritesController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/favorites', [FavoritesController::class, 'index'])->name('favorites.index');
});
