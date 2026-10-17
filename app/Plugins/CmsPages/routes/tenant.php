<?php

declare(strict_types=1);

use App\Plugins\CmsPages\src\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/pages/{slug}', [PageController::class, 'show'])->name('pages.show');
});
