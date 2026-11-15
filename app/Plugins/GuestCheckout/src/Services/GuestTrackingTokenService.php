<?php

declare(strict_types=1);

namespace App\Plugins\GuestCheckout\src\Services;

use App\Plugins\OrderManagement\src\Models\Order;

final class GuestTrackingTokenService
{
    public function generate(Order $order): string
    {
        $tenantId = (string) (tenant('id') ?? '');
        $payload = "{$tenantId}:{$order->id}:{$order->order_number}";
        $key = (string) config('app.key');

        return hash_hmac('sha256', $payload, $key);
    }

    public function verify(Order $order, string $token): bool
    {
        return hash_equals($this->generate($order), $token);
    }
}
