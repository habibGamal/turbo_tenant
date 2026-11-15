<?php

declare(strict_types=1);

namespace App\Plugins\PaymentProcessing\src\Http\Requests\Concerns;

use App\Plugins\OrderManagement\src\Models\Order;
use Illuminate\Support\Facades\Auth;

trait AuthorizesOrderPayment
{
    protected function canAccessOrder(Order $order): bool
    {
        $user = $this->user();
        $userId = $user ? $user->id : Auth::id();

        if ($order->user_id !== null) {
            return $userId !== null && (int) $order->user_id === (int) $userId;
        }

        $guestOrderIds = $this->hasSession()
            ? $this->session()->get('guest_order_ids', [])
            : session('guest_order_ids', []);

        return in_array($order->id, (array) $guestOrderIds, false);
    }
}
