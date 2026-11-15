<?php

declare(strict_types=1);

use App\Core\Plugins\Routing\PluginRoutes;
use App\Plugins\LocationsDelivery\src\Http\Controllers\Api\LocationsDeliveryApiController;
use Illuminate\Support\Facades\Route;

PluginRoutes::tenant('locations-delivery')->prefix('api/locations-delivery')->group(function () {
    Route::get('/', [LocationsDeliveryApiController::class, 'index'])->name('api.locations-delivery.index');
    Route::get('/governorates', [LocationsDeliveryApiController::class, 'governorates'])->name('api.locations-delivery.governorates');
    Route::get('/branches', [LocationsDeliveryApiController::class, 'branches'])->name('api.locations-delivery.branches');

    Route::middleware('auth')->group(function () {
        Route::get('/addresses', [LocationsDeliveryApiController::class, 'addresses'])->name('api.locations-delivery.addresses.index');
        Route::post('/addresses', [LocationsDeliveryApiController::class, 'storeAddress'])->name('api.locations-delivery.addresses.store');
        Route::match(['put', 'patch'], '/addresses/{address}', [LocationsDeliveryApiController::class, 'updateAddress'])->name('api.locations-delivery.addresses.update');
        Route::delete('/addresses/{address}', [LocationsDeliveryApiController::class, 'destroyAddress'])->name('api.locations-delivery.addresses.destroy');
    });
});
