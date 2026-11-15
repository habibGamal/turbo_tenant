<?php

declare(strict_types=1);

use App\Core\Plugins\Routing\PluginRoutes;
use App\Plugins\MetaMarketing\src\Http\Controllers\CatalogFeedController;
use App\Plugins\MetaMarketing\src\Http\Controllers\RelayEventController;
use Illuminate\Support\Facades\Route;

PluginRoutes::tenant('meta-marketing')->group(function () {
    // The storefront mirrors browser Pixel events here so they are also sent through the Conversions API.
    Route::post('/api/meta/events', RelayEventController::class)
        ->middleware('throttle:120,1')
        ->name('api.meta.events');

    // Meta Commerce Manager fetches the product catalog from these URLs (scheduled data feed).
    Route::get('/meta/catalog/{token}.ar.csv', [CatalogFeedController::class, 'arabicOverride'])
        ->name('meta.catalog.feed.ar');
    Route::get('/meta/catalog/{token}.{format}', [CatalogFeedController::class, 'primary'])
        ->where(['token' => '[A-Za-z0-9]+', 'format' => 'csv|xml'])
        ->name('meta.catalog.feed');
});
