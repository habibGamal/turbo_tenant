<?php

declare(strict_types=1);

use App\Plugins\CmsPages\src\Http\Controllers\Api\CmsPagesApiController;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    'plugin.active:cms-pages',
])->prefix('api')->group(function () {
    Route::get('/cms-pages', [CmsPagesApiController::class, 'index'])->name('api.cms-pages.index');
    Route::get('/cms-pages/{slug}', [CmsPagesApiController::class, 'show'])->name('api.cms-pages.show');
});
