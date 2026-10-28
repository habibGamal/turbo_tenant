<?php

declare(strict_types=1);

namespace App\Plugins\PaymentProcessing\src\Services;

use App\Enums\PaymentStatus;
use App\Plugins\OrderManagement\src\Contracts\PaymentGatewayInterface;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\PaymentProcessing\src\Contracts\PaymentWebhookServiceInterface;
use App\Plugins\PaymentProcessing\src\Models\PaymentEvent;
use Illuminate\Support\Facades\Log;

final readonly class PaymentWebhookService implements PaymentWebhookServiceInterface
{
    public function __construct(
        private PaymentStateMachine $stateMachine
    ) {}

    public function handleWebhook(array $webhookData, string $signature, PaymentGatewayInterface $gateway): array
    {
        $gatewayId = $gateway->getGatewayId();

        if (! $gateway->validateHmac($webhookData['obj'] ?? $webhookData, $signature)) {
            Log::warning('Payment webhook signature invalid', [
                'gateway' => $gatewayId,
                'result' => 'invalid_signature',
            ]);

            return [
                'success' => false,
                'error' => 'Invalid webhook signature',
            ];
        }

        $processedData = $gateway->processWebhook($webhookData);
        $transactionId = (string) ($processedData['transaction_id'] ?? '');
        $merchantOrderId = (string) ($processedData['merchant_order_id'] ?? '');
        $targetStatus = (string) ($processedData['payment_status'] ?? PaymentStatus::PENDING->value);
        $webhookAmountCents = (int) ($processedData['amount_cents'] ?? 0);
        $webhookCurrency = (string) ($processedData['currency'] ?? '');
        $eventType = isset($webhookData['type']) || isset($webhookData['event'])
            ? (string) ($webhookData['type'] ?? $webhookData['event'])
            : null;

        if ($this->isDuplicate($gatewayId, $transactionId)) {
            $this->logResult($gatewayId, $merchantOrderId, $transactionId, $targetStatus, 'duplicate', 'info');

            $order = $this->findOrder($merchantOrderId);

            return [
                'success' => true,
                'order' => $order,
                'payment_data' => $processedData,
                'duplicate' => true,
            ];
        }

        $order = $this->findOrder($merchantOrderId);
        if (! $order) {
            $this->logResult($gatewayId, $merchantOrderId, $transactionId, $targetStatus, 'order_not_found', 'warning');

            return [
                'success' => false,
                'error' => 'Order not found',
            ];
        }

        if (! $this->amountMatches($order, $webhookAmountCents)) {
            $this->logResult($gatewayId, $merchantOrderId, $transactionId, $targetStatus, 'amount_mismatch', 'warning');

            return [
                'success' => false,
                'error' => 'Amount mismatch',
            ];
        }

        if (! $this->currencyMatches($gateway, $webhookCurrency)) {
            $this->logResult($gatewayId, $merchantOrderId, $transactionId, $targetStatus, 'currency_mismatch', 'warning');

            return [
                'success' => false,
                'error' => 'Currency mismatch',
            ];
        }

        $transitioned = $this->stateMachine->transition(
            $order,
            $targetStatus,
            [
                'transaction_id' => $transactionId,
                'payment_data' => $processedData['payment_data'] ?? null,
            ],
            $gatewayId
        );

        $eventResult = $transitioned ? $targetStatus : 'ignored_transition';
        $this->recordEvent($gatewayId, $transactionId, $merchantOrderId, $eventType, $webhookAmountCents, $eventResult);

        $freshOrder = $order->fresh();
        $finalStatus = $freshOrder->payment_status instanceof PaymentStatus
            ? $freshOrder->payment_status->value
            : (string) ($freshOrder->payment_status ?? $targetStatus);

        $this->logResult($gatewayId, $merchantOrderId, $transactionId, $finalStatus, $transitioned ? 'success' : 'ignored_transition', 'info');

        return [
            'success' => true,
            'order' => $freshOrder,
            'payment_data' => $processedData,
        ];
    }

    private function isDuplicate(string $gatewayId, string $transactionId): bool
    {
        if ($transactionId === '') {
            return false;
        }

        return PaymentEvent::where('gateway', $gatewayId)
            ->where('transaction_id', $transactionId)
            ->exists();
    }

    private function findOrder(string $merchantOrderId): ?Order
    {
        if ($merchantOrderId === '') {
            return null;
        }

        return Order::where('merchant_order_id', $merchantOrderId)->first();
    }

    private function amountMatches(Order $order, int $webhookAmountCents): bool
    {
        $expectedAmountCents = (int) round(((float) $order->total) * 100);

        return $webhookAmountCents === $expectedAmountCents;
    }

    private function currencyMatches(PaymentGatewayInterface $gateway, string $webhookCurrency): bool
    {
        if ($webhookCurrency === '') {
            return true;
        }

        return mb_strtoupper($webhookCurrency) === mb_strtoupper($gateway->getCurrency());
    }

    private function recordEvent(
        string $gatewayId,
        string $transactionId,
        string $merchantOrderId,
        ?string $eventType,
        int $amountCents,
        string $result
    ): void {
        if ($transactionId === '') {
            return;
        }

        PaymentEvent::create([
            'gateway' => $gatewayId,
            'transaction_id' => $transactionId,
            'merchant_order_id' => $merchantOrderId,
            'event_type' => $eventType,
            'amount_cents' => $amountCents,
            'result' => $result,
        ]);
    }

    /**
     * @return array{gateway: string, merchant_order_id: string, transaction_id: string, status: string, result: string}
     */
    private function buildLogContext(
        string $gateway,
        string $merchantOrderId,
        string $transactionId,
        string $status,
        string $result
    ): array {
        return [
            'gateway' => $gateway,
            'merchant_order_id' => $merchantOrderId,
            'transaction_id' => $transactionId,
            'status' => $status,
            'result' => $result,
        ];
    }

    private function logResult(
        string $gateway,
        string $merchantOrderId,
        string $transactionId,
        string $status,
        string $result,
        string $level = 'info'
    ): void {
        $context = $this->buildLogContext($gateway, $merchantOrderId, $transactionId, $status, $result);

        if ($level === 'warning') {
            Log::warning("Payment webhook {$result}", $context);
        } else {
            Log::info("Payment webhook {$result}", $context);
        }
    }
}
