<?php

declare(strict_types=1);

use App\Core\Plugins\Routing\PluginRoutes;
use App\Plugins\CmsPages\src\Http\Controllers\Api\CmsPagesApiController;
use Illuminate\Support\Facades\Route;

PluginRoutes::tenant('cms-pages')->prefix('api')->group(function () {
    Route::get('/cms-pages', [CmsPagesApiController::class, 'index'])->name('api.cms-pages.index');
    Route::get('/cms-pages/{slug}', [CmsPagesApiController::class, 'show'])->name('api.cms-pages.show')->where('slug', '[a-zA-Z0-9\-_]+');
});
