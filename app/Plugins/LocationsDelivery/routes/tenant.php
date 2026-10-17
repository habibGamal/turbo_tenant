<?php

declare(strict_types=1);

use App\Plugins\LocationsDelivery\src\Http\Controllers\AddressController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::middleware('auth')->group(function () {
        Route::post('/addresses', [AddressController::class, 'store'])->name('addresses.store');
        Route::patch('/addresses/{address}', [AddressController::class, 'update'])->name('addresses.update');
        Route::delete('/addresses/{address}', [AddressController::class, 'destroy'])->name('addresses.destroy');
        Route::get('/governorates-areas', [AddressController::class, 'getGovernoratesAreas'])->name('governorates-areas.index');
    });
});
