<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Http\Responders;

use App\Core\Contracts\Customer;
use App\Core\Plugins\Services\PluginContributions;
use App\Http\Responses\ApiResponse;
use App\Plugins\OrderManagement\src\Contracts\OrderPlacementResponseContributor;
use App\Plugins\OrderManagement\src\DTOs\OrderPlacementResult;
use Illuminate\Http\JsonResponse;

final class OrderPlacementResponder
{
    public function __construct(
        private readonly PluginContributions $contributions,
    ) {}

    public function respond(OrderPlacementResult $result, Customer $customer): JsonResponse
    {
        if (! $result->isSuccessful()) {
            $errorMessage = $result->errorMessage ?? 'Failed to place order';

            return ApiResponse::error(
                $errorMessage,
                422,
                ['order' => [$errorMessage]]
            );
        }

        if ($result->requiresPayment && empty($result->checkoutUrl)) {
            return ApiResponse::error('Payment checkout URL not available', 502);
        }

        $order = $result->order;

        $payload = [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'requires_payment' => $result->requiresPayment,
            'checkout_url' => $result->requiresPayment ? $result->checkoutUrl : null,
        ];

        /** @var list<OrderPlacementResponseContributor> $responseContributors */
        $responseContributors = $this->contributions->active('order.placement.response');
        foreach ($responseContributors as $contributor) {
            $payload = $contributor->extend($order, $customer, $payload);
        }

        return ApiResponse::success($payload);
    }
}
