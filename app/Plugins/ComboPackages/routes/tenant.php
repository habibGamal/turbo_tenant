<?php

declare(strict_types=1);

use App\Plugins\ComboPackages\src\Http\Controllers\Api\PackageApiController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    'plugin.active:combo-packages',
])->prefix('api/packages')->name('api.packages.')->group(function () {
    Route::get('/', [PackageApiController::class, 'index'])->name('index');
    Route::get('/{id}', [PackageApiController::class, 'show'])->name('show');
});
