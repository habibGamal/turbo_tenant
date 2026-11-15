<?php

declare(strict_types=1);

use App\Core\Plugins\Routing\PluginRoutes;
use App\Plugins\ComboPackages\src\Http\Controllers\Api\PackageApiController;
use Illuminate\Support\Facades\Route;

PluginRoutes::tenant('combo-packages')->prefix('api/packages')->name('api.packages.')->group(function () {
    Route::get('/', [PackageApiController::class, 'index'])->name('index');
    Route::get('/{id}', [PackageApiController::class, 'show'])->name('show')->whereNumber('id');
});
