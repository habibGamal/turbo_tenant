<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Branch;
use App\Models\Order;
use App\Services\POS\Contracts\OrderPOSPayloadBuilderInterface;
use App\Services\POS\Contracts\POSApiClientInterface;
use App\Services\POS\Contracts\POSErrorResponseHandlerInterface;
use Exception;
use Illuminate\Support\Facades\Log;

final class OrderPOSService
{
    public function __construct(
        private readonly POSApiClientInterface $apiClient,
        private readonly OrderPOSPayloadBuilderInterface $payloadBuilder,
        private readonly POSErrorResponseHandlerInterface $errorHandler,
    ) {}

    /**
     * Get the current shift ID from the POS system.
     *
     * @throws Exception
     */
    public function getShiftId(Branch $branch): int
    {
        return $this->apiClient->getShiftId($branch);
    }

    /**
     * Check if the branch can accept orders.
     *
     * @throws Exception
     */
    public function canAcceptOrder(Branch $branch): bool
    {
        return $this->apiClient->canAcceptOrder($branch);
    }

    /**
     * Place an order with the POS system.
     *
     * @return array{success: bool, message?: string, notFoundProducts?: array<string>}
     *
     * @throws Exception
     */
    public function placeOrder(Order $order): array
    {
        // Ensure required relationships are loaded
        $order->loadMissing([
            'user',
            'guestUser.area.governorate',
            'address.area.governorate',
            'branch',
            'items.product',
            'items.variant',
            'items.weightOptionValue',
            'items.extras.extraOptionItem',
        ]);

        $branch = $order->branch;

        if (! $branch) {
            throw new Exception('Order does not have a branch assigned');
        }

        // 1. Retrieve active shift ID if not already assigned
        $order->shift_id = $order->shift_id ?: $this->getShiftId($branch);

        // 2. Build the order payload conforming to CREATE_ORDER_API_DOC.md
        $payload = $this->payloadBuilder->build($order);

        // 3. Send order to POS
        try {
            $result = $this->apiClient->sendOrder($branch, $payload);

            if (! $result['successful']) {
                return $this->errorHandler->handle($result['data'], $result['status'], $branch);
            }

            return [
                'success' => true,
            ];
        } catch (Exception $e) {
            Log::error('Failed to place order with POS', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'branch_id' => $branch->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
