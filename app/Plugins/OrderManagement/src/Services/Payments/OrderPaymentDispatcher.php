<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Services\Payments;

use App\Plugins\PaymentProcessing\src\Contracts\PaymentGatewayInterface;
use App\Plugins\LocationsDelivery\src\Models\Address;
use App\Plugins\OrderManagement\src\DTOs\OrderPlacementResult;
use App\Plugins\OrderManagement\src\DTOs\PlaceOrderData;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Services\Contracts\OrderPaymentDispatcherInterface;
use App\Plugins\OrderManagement\src\Services\Contracts\OrderRepositoryInterface;
use Illuminate\Support\Facades\Log;

class OrderPaymentDispatcher implements OrderPaymentDispatcherInterface
{
    public function __construct(
        private PaymentGatewayInterface $paymentGateway,
        private OrderRepositoryInterface $orderRepository
    ) {}

    public function dispatchPayment(Order $order, PlaceOrderData $data): OrderPlacementResult
    {
        $billingData = $this->prepareBillingData($data);
        $gatewayId = $this->paymentGateway->getGatewayId();
        $redirectionUrl = url('/orders/'.$order->id.'/payment/callback');
        $notificationUrl = url("/api/webhooks/{$gatewayId}");

        Log::info('Creating payment intention', [
            'order_id' => $order->id,
            'gateway' => $gatewayId,
            'redirection_url' => $redirectionUrl,
            'notification_url' => $notificationUrl,
        ]);

        $paymentResult = $this->paymentGateway->createPaymentIntention(
            $order,
            $billingData,
            $redirectionUrl,
            $notificationUrl
        );

        if (! ($paymentResult['success'] ?? false)) {
            $this->orderRepository->markOrderAsPaymentFailed($order);

            return OrderPlacementResult::failure(
                errorMessage: $paymentResult['error'] ?? 'Failed to create payment',
                order: $order
            );
        }

        Log::info('Payment intention created', [
            'order_id' => $order->id,
            'paymentResult' => $paymentResult,
        ]);

        $freshOrder = $order->fresh(['items.extras', 'user', 'branch', 'address']);

        return OrderPlacementResult::success(
            order: $freshOrder,
            requiresPayment: true,
            checkoutUrl: $paymentResult['checkout_url'] ?? null,
            paymentData: $paymentResult['data'] ?? []
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareBillingData(PlaceOrderData $data): array
    {
        $user = $data->user;
        $customBilling = $data->billingData;

        if ($data->isGuest()) {
            return [
                'first_name' => $customBilling['first_name'] ?? $user->name ?? 'Guest',
                'last_name' => $customBilling['last_name'] ?? 'Guest',
                'email' => $customBilling['email'] ?? $user->email ?? 'guest@example.com',
                'phone_number' => $customBilling['phone_number'] ?? $user->full_phone ?? $user->phone ?? null,
                'apartment' => $customBilling['apartment'] ?? $user->apartment ?? 'NA',
                'floor' => $customBilling['floor'] ?? $user->floor ?? 'NA',
                'street' => $customBilling['street'] ?? $user->street ?? 'NA',
                'building' => $customBilling['building'] ?? $user->building ?? 'NA',
                'city' => $customBilling['city'] ?? $user->city ?? 'Cairo',
                'country' => $customBilling['country'] ?? 'EG',
                'postal_code' => $customBilling['postal_code'] ?? 'NA',
            ];
        }

        $address = $data->addressId ? Address::find($data->addressId) : null;

        return [
            'first_name' => $customBilling['first_name'] ?? $user->name ?? 'NA',
            'last_name' => $customBilling['last_name'] ?? 'NA',
            'email' => $customBilling['email'] ?? $user->email ?? 'customer@example.com',
            'phone_number' => $customBilling['phone_number'] ?? $user->phone ?? '+201000000000',
            'apartment' => $customBilling['apartment'] ?? $address?->apartment ?? 'NA',
            'floor' => $customBilling['floor'] ?? $address?->floor ?? 'NA',
            'street' => $customBilling['street'] ?? $address?->street ?? 'NA',
            'building' => $customBilling['building'] ?? $address?->building ?? 'NA',
            'city' => $customBilling['city'] ?? $address?->city ?? 'Cairo',
            'country' => $customBilling['country'] ?? 'EG',
            'postal_code' => $customBilling['postal_code'] ?? $address?->postal_code ?? 'NA',
        ];
    }
}
