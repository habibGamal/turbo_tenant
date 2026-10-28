<?php

declare(strict_types=1);

use App\Plugins\PaymentProcessing\src\Http\Controllers\KashierWebhookController;
use App\Plugins\PaymentProcessing\src\Http\Controllers\PaymentController;
use App\Plugins\PaymentProcessing\src\Http\Controllers\PaymobWebhookController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

// Payment gateway webhooks (no auth, no CSRF, remain reachable regardless of plugin state)
Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::post('/api/webhooks/paymob', [PaymobWebhookController::class, 'handle'])
        ->name('webhooks.paymob')
        ->withoutMiddleware([VerifyCsrfToken::class]);

    Route::post('/api/webhooks/kashier', [KashierWebhookController::class, 'handle'])
        ->name('webhooks.kashier')
        ->withoutMiddleware([VerifyCsrfToken::class]);
});

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
    'plugin.active:payment-processing',
])->group(function () {
    // Browser redirects
    Route::get('/orders/{orderId}/payment/callback', [PaymentController::class, 'paymentCallback'])->name('orders.payment.callback');

    // Decoupled REST API endpoints
    Route::prefix('api')->group(function () {
        Route::get('/orders/{orderId}/payment-status', [App\Plugins\PaymentProcessing\src\Http\Controllers\Api\PaymentProcessingApiController::class, 'paymentStatus'])
            ->name('api.orders.payment-status');
        Route::get('/payments/{order}/kashier-config', [App\Plugins\PaymentProcessing\src\Http\Controllers\Api\PaymentProcessingApiController::class, 'kashierConfig'])
            ->name('api.payments.kashier-config');
    });
});
