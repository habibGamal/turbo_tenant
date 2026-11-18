<?php

declare(strict_types=1);

namespace App\Plugins\GuestCheckout\src\Http\Response;

use App\Plugins\GuestCheckout\src\Services\GuestTrackingTokenService;
use App\Plugins\OrderManagement\src\Contracts\OrderPaymentStatusResponseContributor;
use App\Plugins\OrderManagement\src\Models\Order;

final class GuestPaymentStatusResponseContributor implements OrderPaymentStatusResponseContributor
{
    public function __construct(
        private readonly GuestTrackingTokenService $tokenService,
    ) {}

    public function pluginId(): string
    {
        return 'guest-checkout';
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    public function extend(Order $order, array $response): array
    {
        if ($order->user_id !== null) {
            return $response;
        }

        $response['tracking_token'] = $this->tokenService->generate($order);

        return $response;
    }
}
