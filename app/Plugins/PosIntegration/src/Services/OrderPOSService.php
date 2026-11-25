<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Services;

use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\OrderManagement\src\Enums\OrderStatus;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Models\OrderStatusEvent;
use App\Plugins\OrderManagement\src\Services\OrderStatusTransitions;
use App\Plugins\PosIntegration\src\Exceptions\PosDeliveryOutcomeUnknown;
use App\Plugins\PosIntegration\src\Services\POS\Contracts\OrderPOSPayloadBuilderInterface;
use App\Plugins\PosIntegration\src\Services\POS\Contracts\POSApiClientInterface;
use App\Plugins\PosIntegration\src\Services\POS\Contracts\POSErrorResponseHandlerInterface;
use Closure;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderPOSService
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
     * @return array{success: bool, message?: string, notFoundProducts?: array<string>, alreadyReceived?: bool, posStatus?: string|null}
     *
     * @throws Exception
     */
    public function placeOrder(Order $order, ?Closure $beforeSend = null): array
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
            'items.components',
        ]);

        $branch = $order->branch;

        if (! $branch) {
            throw new Exception('Order does not have a branch assigned');
        }

        // 1. Retrieve active shift ID if not already assigned
        $order->shift_id = $order->shift_id ?: $this->getShiftId($branch);

        // 2. Build the order payload conforming to docs/pos-website-link.md (links to POS-owned contract)
        $payload = $this->payloadBuilder->build($order);

        // 3. Send order to POS
        try {
            $beforeSend?->__invoke();
            $result = $this->apiClient->sendOrder($branch, $payload);

            if ($result['status'] >= 500) {
                throw new PosDeliveryOutcomeUnknown('لا يمكن التواصل مع الفرع في الوقت الحالي');
            }

            if (! $result['successful']) {
                return $this->errorHandler->handle($result['data'], $result['status'], $branch);
            }

            if (($result['data']['already_received'] ?? false) === true) {
                return $this->alreadyReceived($order, $result['data']);
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

    /**
     * The POS already saved this order on an earlier attempt (its response was
     * lost, e.g. a timeout) and confirmed it is the same order. Treat it as
     * sent and catch the website order up with the POS status.
     *
     * @param  array<string, mixed>  $data
     * @return array{success: true, alreadyReceived: true, posStatus: string|null}
     */
    private function alreadyReceived(Order $order, array $data): array
    {
        $posStatus = $data['order']['status'] ?? null;
        $posStatus = is_string($posStatus) ? $posStatus : null;

        Log::info('POS already had this order; treating it as sent', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'pos_order_id' => $data['order']['id'] ?? null,
            'pos_status' => $posStatus,
        ]);

        $target = $posStatus !== null ? OrderStatus::tryFrom($posStatus) : null;
        if ($target !== null) {
            $this->catchUpStatus($order, $target);
        }

        return [
            'success' => true,
            'alreadyReceived' => true,
            'posStatus' => $posStatus,
        ];
    }

    /**
     * Move the website order forward to the POS status through allowed
     * transitions only, recording each step like the POS status callback does.
     * A status it cannot reach (e.g. a step backwards) is left alone.
     */
    private function catchUpStatus(Order $order, OrderStatus $target): void
    {
        $transitions = app(OrderStatusTransitions::class);

        DB::transaction(function () use ($order, $target, $transitions): void {
            $order->refresh();

            while ($order->status !== $target) {
                $next = $transitions->canTransition($order->status, $target, $order->type)
                    ? $target
                    : $this->nextForwardStatus($order->status, $target);

                if ($next === null || ! $transitions->canTransition($order->status, $next, $order->type)) {
                    return;
                }

                $from = $order->status;
                $order->update(['status' => $next]);
                OrderStatusEvent::create([
                    'order_id' => $order->id,
                    'from_status' => $from,
                    'to_status' => $next,
                    'source' => 'integration',
                    'ip' => null,
                ]);
            }
        });
    }

    /**
     * The next step on pending -> preparing -> out for delivery -> delivered,
     * when the target lies ahead of the current status.
     */
    private function nextForwardStatus(OrderStatus $current, OrderStatus $target): ?OrderStatus
    {
        $forward = [OrderStatus::PENDING, OrderStatus::PREPARING, OrderStatus::OUT_FOR_DELIVERY, OrderStatus::DELIVERED];
        $from = array_search($current, $forward, true);
        $to = array_search($target, $forward, true);

        if ($from === false || $to === false || $from >= $to) {
            return null;
        }

        return $forward[$from + 1];
    }
}
