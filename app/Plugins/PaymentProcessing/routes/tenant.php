<?php

declare(strict_types=1);

use App\Plugins\PaymentProcessing\src\Http\Controllers\KashierWebhookController;
use App\Plugins\PaymentProcessing\src\Http\Controllers\PaymentController;
use App\Plugins\PaymentProcessing\src\Http\Controllers\PaymobWebhookController;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

Route::middleware([
    'web',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/orders/{orderId}/payment/callback', [PaymentController::class, 'paymentCallback'])->name('orders.payment.callback');

    Route::middleware('auth')->group(function () {
        Route::get('/payments/{order}/kashier', [PaymentController::class, 'showKashierPayment'])->name('payment.kashier');
    });

    // Payment gateway webhooks (no auth, no CSRF)
    Route::post('/api/webhooks/paymob', [PaymobWebhookController::class, 'handle'])
        ->name('webhooks.paymob')
        ->withoutMiddleware([VerifyCsrfToken::class]);

    Route::post('/api/webhooks/kashier', [KashierWebhookController::class, 'handle'])
        ->name('webhooks.kashier')
        ->withoutMiddleware([VerifyCsrfToken::class]);
});
