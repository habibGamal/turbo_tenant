<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\KashierService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class PaymentController extends Controller
{
    public function __construct(
        private readonly KashierService $kashierService
    ) {}

    /**
     * Show the Kashier payment page.
     */
    public function showKashierPayment(Request $request, Order $order): Response
    {
        $user = Auth::user();

        // Verify user owns the order
        if (! $user || $order->user_id !== $user->id) {
            abort(403, 'Unauthorized');
        }

        // Check order has kashier params stored
        $kashierParams = session("kashier_params_{$order->id}");

        if (! $kashierParams) {
            // Generate new params if not in session
            $redirectionUrl = url("/orders/{$order->id}/payment/callback");
            $notificationUrl = url('/api/webhooks/kashier');

            $result = $this->kashierService->createPaymentIntention(
                $order,
                [],
                $redirectionUrl,
                $notificationUrl
            );

            if (! $result['success']) {
                return Inertia::render('PaymentCallback', [
                    'success' => false,
                    'message' => $result['error'] ?? 'Failed to initialize payment',
                    'order' => $order,
                ]);
            }

            $kashierParams = $result['kashier_params'];
        }

        return Inertia::render('Kashier', [
            'kashierParams' => $kashierParams,
            'order' => $order->load(['items.product', 'user']),
        ]);
    }
}
