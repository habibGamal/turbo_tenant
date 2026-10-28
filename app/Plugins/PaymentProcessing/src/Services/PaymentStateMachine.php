<?php

declare(strict_types=1);

namespace App\Plugins\PaymentProcessing\src\Services;

use App\Enums\PaymentStatus;
use App\Plugins\OrderManagement\src\Enums\OrderPosStatus;
use App\Plugins\OrderManagement\src\Models\Order;
use Illuminate\Support\Facades\Log;

final class PaymentStateMachine
{
    /**
     * Explicit allowed status transitions.
     * pending -> processing | completed | failed
     * processing -> completed | failed
     * failed -> processing | completed (allows customer retry after decline)
     * completed -> refunded
     *
     * @var array<string, list<string>>
     */
    private const array ALLOWED_TRANSITIONS = [
        PaymentStatus::PENDING->value => [
            PaymentStatus::PROCESSING->value,
            PaymentStatus::COMPLETED->value,
            PaymentStatus::FAILED->value,
        ],
        PaymentStatus::PROCESSING->value => [
            PaymentStatus::COMPLETED->value,
            PaymentStatus::FAILED->value,
        ],
        PaymentStatus::COMPLETED->value => [
            PaymentStatus::REFUNDED->value,
        ],
        PaymentStatus::FAILED->value => [
            PaymentStatus::PROCESSING->value,
            PaymentStatus::COMPLETED->value,
        ],
        PaymentStatus::REFUNDED->value => [],
    ];

    /**
     * Check if a transition from current status to target status is allowed.
     */
    public function canTransition(PaymentStatus|string $from, PaymentStatus|string $to): bool
    {
        $fromValue = $from instanceof PaymentStatus ? $from->value : (string) $from;
        $toValue = $to instanceof PaymentStatus ? $to->value : (string) $to;

        $allowedTargets = self::ALLOWED_TRANSITIONS[$fromValue] ?? [];

        return in_array($toValue, $allowedTargets, true);
    }

    /**
     * Apply status transition to the order if allowed.
     * Returns true if transition was applied, false if it was an ignored no-op.
     * Setting pos_status READY happens only on the transition into completed.
     *
     * @param  array<string, mixed>  $extraAttributes
     */
    public function transition(
        Order $order,
        PaymentStatus|string $targetStatus,
        array $extraAttributes = [],
        ?string $gatewayId = null
    ): bool {
        $currentStatus = $order->payment_status instanceof PaymentStatus
            ? $order->payment_status->value
            : (string) ($order->payment_status ?? PaymentStatus::PENDING->value);

        $targetStatusValue = $targetStatus instanceof PaymentStatus
            ? $targetStatus->value
            : (string) $targetStatus;

        if (! $this->canTransition($currentStatus, $targetStatusValue)) {
            Log::info('Payment status transition ignored', [
                'gateway' => $gatewayId,
                'merchant_order_id' => $order->merchant_order_id,
                'transaction_id' => $extraAttributes['transaction_id'] ?? $order->transaction_id,
                'status' => $currentStatus,
                'result' => 'ignored_transition',
            ]);

            return false;
        }

        $attributesToUpdate = array_merge($extraAttributes, [
            'payment_status' => $targetStatusValue,
        ]);

        if ($targetStatusValue === PaymentStatus::COMPLETED->value) {
            $attributesToUpdate['pos_status'] = OrderPosStatus::READY;
        }

        $order->update($attributesToUpdate);

        return true;
    }
}
