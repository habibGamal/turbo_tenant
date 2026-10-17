<?php

declare(strict_types=1);

namespace App\Plugins\PaymentProcessing\src\Services;

use App\Plugins\PosIntegration\src\Enums\OrderPosStatus;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\PaymentProcessing\src\Contracts\PaymentGatewayInterface;
use App\Plugins\PaymentProcessing\src\Contracts\PaymentWebhookServiceInterface;

final readonly class PaymentWebhookService implements PaymentWebhookServiceInterface
{
    public function __construct(
        private PaymentGatewayInterface $paymentGateway
    ) {}

    public function handleWebhook(array $webhookData, string $signature): array
    {
        if (! $this->paymentGateway->validateHmac($webhookData['obj'] ?? $webhookData, $signature)) {
            return [
                'success' => false,
                'error' => 'Invalid webhook signature',
            ];
        }

        $processedData = $this->paymentGateway->processWebhook($webhookData);
        $order = Order::where('merchant_order_id', $processedData['merchant_order_id'])->first();

        if (! $order) {
            return [
                'success' => false,
                'error' => 'Order not found',
            ];
        }

        $order->update([
            'transaction_id' => $processedData['transaction_id'],
            'payment_status' => $processedData['payment_status'],
            'payment_data' => $processedData['payment_data'],
        ]);

        if ($processedData['payment_status'] === 'completed') {
            $order->update(['pos_status' => OrderPosStatus::READY]);
        }

        return [
            'success' => true,
            'order' => $order,
            'payment_data' => $processedData,
        ];
    }
}
