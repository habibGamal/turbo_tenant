<?php

declare(strict_types=1);

namespace App\Plugins\PaymentProcessing\src\Services;

use App\Plugins\PosIntegration\src\Enums\OrderPosStatus;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\PaymentProcessing\src\Contracts\PaymentCallbackServiceInterface;
use App\Plugins\PaymentProcessing\src\Contracts\PaymentGatewayInterface;

final readonly class PaymentCallbackService implements PaymentCallbackServiceInterface
{
    public function __construct(
        private PaymentGatewayInterface $paymentGateway
    ) {}

    public function handleCallback(int $orderId, array $callbackData): array
    {
        $order = Order::with(['items.extras', 'user', 'branch', 'address'])->findOrFail($orderId);
        $paymentData = $callbackData['data'] ?? $callbackData;

        $isKashier = isset($paymentData['paymentStatus']) || isset($paymentData['merchantOrderId']);

        if ($isKashier) {
            return $this->handleKashierCallback($order, $paymentData);
        }

        return $this->handlePaymobCallback($order, $paymentData);
    }

    private function handleKashierCallback(Order $order, array $paymentData): array
    {
        $paymentStatus = mb_strtoupper($paymentData['paymentStatus'] ?? 'FAILED');
        $transactionId = $paymentData['transactionId'] ?? null;
        $amount = $paymentData['amount'] ?? null;
        $currency = $paymentData['currency'] ?? null;
        $signature = $paymentData['signature'] ?? null;

        if ($signature && ! $this->paymentGateway->validateCallbackHmac($paymentData, $signature)) {
            return [
                'success' => false,
                'error' => 'Invalid payment signature',
                'order' => $order,
            ];
        }

        if ($paymentStatus === 'SUCCESS') {
            $order->update([
                'payment_status' => 'completed',
                'transaction_id' => $transactionId,
                'pos_status' => OrderPosStatus::READY,
                'payment_data' => json_encode([
                    'transaction_id' => $transactionId,
                    'amount' => $amount,
                    'currency' => $currency,
                    'card_brand' => $paymentData['cardBrand'] ?? null,
                    'masked_card' => $paymentData['maskedCard'] ?? null,
                    'order_reference' => $paymentData['orderReference'] ?? null,
                    'gateway' => 'kashier',
                ]),
            ]);

            return [
                'success' => true,
                'message' => 'Payment successful',
                'order' => $order->fresh(['items.extras', 'user', 'branch', 'address']),
            ];
        }

        if ($paymentStatus === 'PENDING') {
            $order->update([
                'payment_status' => 'processing',
                'transaction_id' => $transactionId,
                'payment_data' => json_encode([
                    'transaction_id' => $transactionId,
                    'amount' => $amount,
                    'currency' => $currency,
                    'message' => 'Payment pending',
                    'gateway' => 'kashier',
                ]),
            ]);

            return [
                'success' => true,
                'message' => 'Payment is being processed',
                'order' => $order->fresh(['items.extras', 'user', 'branch', 'address']),
            ];
        }

        $order->update([
            'payment_status' => 'failed',
            'status' => 'cancelled',
            'transaction_id' => $transactionId,
            'payment_data' => json_encode([
                'transaction_id' => $transactionId,
                'amount' => $amount,
                'currency' => $currency,
                'message' => 'Payment failed',
                'gateway' => 'kashier',
            ]),
        ]);

        return [
            'success' => false,
            'error' => 'Payment failed',
            'order' => $order->fresh(['items.extras', 'user', 'branch', 'address']),
        ];
    }

    private function handlePaymobCallback(Order $order, array $paymentData): array
    {
        $success = filter_var($paymentData['success'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $pending = filter_var($paymentData['pending'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $transactionId = $paymentData['id'] ?? null;
        $hmac = $paymentData['hmac'] ?? null;

        $amountCents = $paymentData['amount_cents'] ?? null;
        $currency = $paymentData['currency'] ?? null;
        $responseCode = $paymentData['txn_response_code'] ?? $paymentData['acq_response_code'] ?? null;
        $dataMessage = $paymentData['data_message'] ?? null;

        if ($hmac && ! $this->paymentGateway->validateCallbackHmac($paymentData, $hmac)) {
            return [
                'success' => false,
                'error' => 'Invalid payment signature',
                'order' => $order,
            ];
        }

        if ($success && ! $pending) {
            $order->update([
                'payment_status' => 'completed',
                'transaction_id' => $transactionId,
                'pos_status' => OrderPosStatus::READY,
                'payment_data' => json_encode([
                    'transaction_id' => $transactionId,
                    'amount_cents' => $amountCents,
                    'currency' => $currency,
                    'response_code' => $responseCode,
                    'message' => $dataMessage,
                    'payment_method' => $paymentData['source_data_type'] ?? null,
                    'card_last_digits' => $paymentData['source_data_pan'] ?? null,
                    'card_type' => $paymentData['source_data_sub_type'] ?? null,
                    'gateway' => 'paymob',
                ]),
            ]);

            return [
                'success' => true,
                'message' => 'Payment successful',
                'order' => $order->fresh(['items.extras', 'user', 'branch', 'address']),
            ];
        }

        if ($pending) {
            $order->update([
                'payment_status' => 'processing',
                'transaction_id' => $transactionId,
                'payment_data' => json_encode([
                    'transaction_id' => $transactionId,
                    'amount_cents' => $amountCents,
                    'currency' => $currency,
                    'message' => 'Payment pending',
                    'gateway' => 'paymob',
                ]),
            ]);

            return [
                'success' => true,
                'message' => 'Payment is being processed',
                'order' => $order->fresh(['items.extras', 'user', 'branch', 'address']),
            ];
        }

        $order->update([
            'payment_status' => 'failed',
            'status' => 'cancelled',
            'transaction_id' => $transactionId,
            'payment_data' => json_encode([
                'transaction_id' => $transactionId,
                'amount_cents' => $amountCents,
                'currency' => $currency,
                'response_code' => $responseCode,
                'message' => $dataMessage ?? 'Payment failed',
                'gateway' => 'paymob',
            ]),
        ]);

        return [
            'success' => false,
            'error' => $dataMessage ?? 'Payment failed',
            'order' => $order->fresh(['items.extras', 'user', 'branch', 'address']),
        ];
    }
}
