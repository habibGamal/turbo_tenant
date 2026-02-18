<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\OrderPosStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SettingKey;
use App\Interfaces\PaymentGatewayInterface;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\GuestUser;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemExtra;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PlaceOrderService
{
    private PaymentGatewayInterface $paymentGateway;

    public function __construct(
        private readonly CartService $cartService,
        private readonly PaymentGatewayFactory $gatewayFactory,
        private readonly CouponService $couponService,
        private readonly SettingService $settingService
    ) {
        $this->paymentGateway = $this->gatewayFactory->getActiveGateway();
    }

    /**
     * Place an order from cart
     */
    public function placeOrder(
        User|GuestUser $user,
        int $branchId,
        PaymentMethod $paymentMethod,
        ?int $addressId = null,
        ?int $couponId = null,
        ?string $note = null,
        string $type = 'web_delivery',
        array $billingData = []
    ): array {
        // Determine if this is a guest order
        $isGuest = $user instanceof GuestUser;

        // Get cart data (pass null for GuestUser to use session-based cart)
        $cartUser = $isGuest ? null : $user;
        $cart = $this->cartService->getCart($cartUser);

        if (empty($cart['items'])) {
            return [
                'success' => false,
                'error' => 'Cart is empty',
            ];
        }

        // Check work times
        if (! $this->isStoreOpen()) {
            $acceptOrdersAfterWorkTimes = filter_var($this->settingService->get(SettingKey::ACCEPT_ORDERS_AFTER_WORK_TIMES, 'true'), FILTER_VALIDATE_BOOLEAN);

            if (! $acceptOrdersAfterWorkTimes) {
                return [
                    'success' => false,
                    'error' => 'We are currently closed. Please try again during our work hours.',
                ];
            }
        }

        try {
            DB::beginTransaction();

            // Validate and get coupon if provided
            $coupon = null;
            if ($couponId) {
                $coupon = Coupon::find($couponId);
                if (! $coupon) {
                    return [
                        'success' => false,
                        'error' => 'Invalid coupon',
                    ];
                }

                // Get address details for validation
                $address = $addressId ? Address::with('area.governorate')->find($addressId) : null;
                $areaId = $address?->area_id;
                $governorateId = $address?->area?->governorate_id;

                // Calculate subtotal for validation
                $subTotal = array_reduce($cart['items'], fn ($total, $item) => $total + ($item['subtotal'] ?? 0), 0);

                // Validate coupon
                $validation = $this->couponService->validateCoupon(
                    $coupon,
                    $user,
                    $cart['items'],
                    $subTotal,
                    $addressId,
                    $areaId,
                    $governorateId
                );

                if (! $validation['valid']) {
                    return [
                        'success' => false,
                        'error' => $validation['message'],
                    ];
                }
            }

            // Calculate order totals
            $totals = $this->calculateOrderTotals($cart['items'], $coupon, $addressId, $type, $paymentMethod);

            // Create order
            $order = $this->createOrder(
                $user,
                $branchId,
                $addressId,
                $couponId,
                $note,
                $type,
                $totals,
                $paymentMethod
            );

            // Create order items
            $this->createOrderItems($order, $cart['items']);

            // Apply coupon usage tracking
            if ($coupon && $totals['discount'] > 0) {
                $this->couponService->applyCoupon($coupon, $totals['discount']);
            }

            // Clear cart after order is created
            $this->cartService->clearCart($user);

            DB::commit();

            // For COD or Credit, no online payment required
            if (! $paymentMethod->requiresOnlinePayment()) {
                $order->update([
                    'payment_status' => PaymentStatus::PENDING,
                    'status' => OrderStatus::PENDING,
                    'pos_status' => OrderPosStatus::READY,
                ]);

                // app(OrderPOSService::class)->placeOrder($order);
                return [
                    'success' => true,
                    'order' => $order->fresh(['items.extras', 'user', 'branch', 'address']),
                    'requires_payment' => false,
                ];
            }

            // For online payment methods (Card, Wallet, Kiosk, Bank Transfer)
            // Prepare billing data
            $billingData = $this->prepareBillingData($user, $addressId, $billingData);

            // Create payment intention
            // Determine webhook URL based on active gateway
            $gatewayId = $this->paymentGateway->getGatewayId();
            $redirectionUrl = url('/orders/'.$order->id.'/payment/callback');
            $notificationUrl = url("/api/webhooks/{$gatewayId}");

            logger()->info('Creating payment intention', [
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

            if (! $paymentResult['success']) {
                // Rollback order if payment creation fails
                DB::transaction(function () use ($order) {
                    $order->update([
                        'status' => 'cancelled',
                        'payment_status' => PaymentStatus::FAILED,
                    ]);
                });

                return [
                    'success' => false,
                    'error' => $paymentResult['error'] ?? 'Failed to create payment',
                    'order' => $order,
                ];
            }
            logger()->info('Payment intention created', [
                'order_id' => $order->id,
                'paymentResult' => $paymentResult,
            ]);
            $order->fresh(['items.extras', 'user', 'branch', 'address']);

            return [
                'success' => true,
                'order' => $order,
                'requires_payment' => true,
                'checkout_url' => $paymentResult['checkout_url'],
                'payment_data' => $paymentResult['data'],
            ];
        } catch (Exception $e) {
            DB::rollBack();

            return [
                'success' => false,
                'error' => 'Failed to place order: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Handle payment callback (success/failure)
     */
    public function handlePaymentCallback(int $orderId, array $callbackData): array
    {
        $order = Order::with(['items.extras', 'user', 'branch', 'address'])->findOrFail($orderId);

        // Extract data from the nested 'data' key
        $paymentData = $callbackData['data'] ?? $callbackData;

        // Detect gateway type based on callback data format
        $isKashier = isset($paymentData['paymentStatus']) || isset($paymentData['merchantOrderId']);

        if ($isKashier) {
            return $this->handleKashierCallback($order, $paymentData);
        }

        return $this->handlePaymobCallback($order, $paymentData);
    }

    /**
     * Handle webhook notification from payment gateway
     */
    public function handleWebhook(array $webhookData, string $hmac): array
    {
        // Validate HMAC
        if (! $this->paymentGateway->validateHmac($webhookData['obj'] ?? $webhookData, $hmac)) {
            return [
                'success' => false,
                'error' => 'Invalid webhook signature',
            ];
        }

        // Process webhook
        $processedData = $this->paymentGateway->processWebhook($webhookData);

        // Find order by merchant_order_id
        $order = Order::where('merchant_order_id', $processedData['merchant_order_id'])->first();

        if (! $order) {
            return [
                'success' => false,
                'error' => 'Order not found',
            ];
        }

        // Update order with payment information
        $order->update([
            'transaction_id' => $processedData['transaction_id'],
            'payment_status' => $processedData['payment_status'],
            'payment_data' => $processedData['payment_data'],
        ]);

        // Update order status based on payment status
        if ($processedData['payment_status'] === 'completed') {
            $order->update(['pos_status' => OrderPosStatus::READY]);
        } elseif ($processedData['payment_status'] === 'failed') {
            // $order->update(['status' => 'cancelled']);
        }

        return [
            'success' => true,
            'order' => $order,
            'payment_data' => $processedData,
        ];
    }

    /**
     * Handle Kashier-specific callback format
     */
    private function handleKashierCallback(Order $order, array $paymentData): array
    {
        $paymentStatus = mb_strtoupper($paymentData['paymentStatus'] ?? 'FAILED');
        $transactionId = $paymentData['transactionId'] ?? null;
        $amount = $paymentData['amount'] ?? null;
        $currency = $paymentData['currency'] ?? null;
        $signature = $paymentData['signature'] ?? null;

        // Validate signature if provided
        if ($signature && ! $this->paymentGateway->validateCallbackHmac($paymentData, $signature)) {
            return [
                'success' => false,
                'error' => 'Invalid payment signature',
                'order' => $order,
            ];
        }

        // Check payment status
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

        // Payment failed
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

    /**
     * Handle Paymob-specific callback format
     */
    private function handlePaymobCallback(Order $order, array $paymentData): array
    {
        // Process callback data with correct Paymob field names
        $success = filter_var($paymentData['success'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $pending = filter_var($paymentData['pending'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $transactionId = $paymentData['id'] ?? null;
        $hmac = $paymentData['hmac'] ?? null;

        // Additional payment info
        $amountCents = $paymentData['amount_cents'] ?? null;
        $currency = $paymentData['currency'] ?? null;
        $responseCode = $paymentData['txn_response_code'] ?? $paymentData['acq_response_code'] ?? null;
        $dataMessage = $paymentData['data_message'] ?? null;

        // Validate HMAC if provided (use callback-specific validation)
        if ($hmac && ! $this->paymentGateway->validateCallbackHmac($paymentData, $hmac)) {
            return [
                'success' => false,
                'error' => 'Invalid payment signature',
                'order' => $order,
            ];
        }

        // Update order based on payment status
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

        // Payment failed
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

    /**
     * Calculate order totals
     */
    private function calculateOrderTotals(array $items, ?Coupon $coupon, ?int $addressId, string $type, PaymentMethod $paymentMethod): array
    {
        $subTotal = array_reduce($items, fn ($total, $item) => $total + ($item['subtotal'] ?? 0), 0);

        // Calculate discount using CouponService
        $discount = 0;
        if ($coupon) {
            $discount = $this->couponService->calculateDiscount($coupon, $items, $subTotal);
        }

        $tax = $this->calculateTax($subTotal - $discount);
        $service = $this->calculateService($subTotal - $discount);

        // Apply COD fee if applicable
        if ($paymentMethod === PaymentMethod::COD) {
            $codFee = (float) $this->settingService->get(SettingKey::COD_FEE, 0);
            $service += $codFee;
        }

        // Calculate base delivery fee
        $baseDeliveryFee = $this->calculateDeliveryFee($addressId, $type);

        // Apply coupon to shipping fee if applicable
        $deliveryFee = $this->couponService->calculateShippingFee(
            $coupon,
            $baseDeliveryFee,
            $subTotal,
            $discount
        );

        $total = $subTotal - $discount + $tax + $service + $deliveryFee;

        return [
            'sub_total' => $subTotal,
            'discount' => $discount,
            'tax' => $tax,
            'service' => $service,
            'delivery_fee' => $deliveryFee,
            'total' => $total,
        ];
    }

    /**
     * Calculate tax (e.g., 14% VAT in Egypt)
     */
    private function calculateTax(float $amount): float
    {
        $taxRate = config('app.tax_rate', 0.0);

        return $amount * $taxRate;
    }

    /**
     * Calculate service charge (if applicable)
     */
    private function calculateService(float $amount): float
    {
        $serviceRate = config('app.service_rate', 0);

        return $amount * $serviceRate;
    }

    /**
     * Calculate delivery fee based on area shipping cost
     */
    private function calculateDeliveryFee(?int $addressId, string $type): float
    {
        // No delivery fee for takeaway or POS orders
        if (in_array($type, ['web_takeaway', 'pos'])) {
            return 0;
        }

        // No delivery fee if no address provided
        if (! $addressId) {
            return 0;
        }

        // Get the address with area relationship
        $address = Address::with('area')->find($addressId);

        if (! $address || ! $address->area) {
            return 0;
        }

        // Return the shipping cost from the area
        return $address->area->shipping_cost;
    }

    /**
     * Create order record
     */
    private function createOrder(
        User|GuestUser $user,
        int $branchId,
        ?int $addressId,
        ?int $couponId,
        ?string $note,
        string $type,
        array $totals,
        PaymentMethod $paymentMethod
    ): Order {
        $isGuest = $user instanceof GuestUser;

        return Order::create([
            'order_number' => $this->generateOrderNumber(),
            'user_id' => $isGuest ? null : $user->id,
            'guest_user_id' => $isGuest ? $user->id : null,
            'branch_id' => $branchId,
            'address_id' => $addressId,
            'coupon_id' => $couponId,
            'note' => $note,
            'type' => $type,
            'status' => 'pending',
            'payment_status' => PaymentStatus::PENDING,
            'payment_method' => $paymentMethod,
            'sub_total' => $totals['sub_total'],
            'discount' => $totals['discount'],
            'tax' => $totals['tax'],
            'service' => $totals['service'],
            'delivery_fee' => $totals['delivery_fee'],
            'total' => $totals['total'],
        ]);
    }

    /**
     * Create order items from cart items
     */
    private function createOrderItems(Order $order, array $cartItems): void
    {
        foreach ($cartItems as $cartItem) {
            $product = $cartItem['product'];
            $variant = $cartItem['variant'] ?? null;
            $weightOptionValue = $cartItem['weight_option_value'] ?? null;

            $orderItem = OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $cartItem['product_id'],
                'variant_id' => $cartItem['variant_id'],
                'weight_option_value_id' => $cartItem['weight_option_value_id'] ?? null,
                'weight_multiplier' => $cartItem['weight_multiplier'] ?? 1,
                'product_name' => $product['name'],
                'product_name_ar' => $product['name_ar'] ?? null,
                'variant_name' => $variant['name'] ?? null,
                'variant_name_ar' => $variant['name_ar'] ?? null,
                'quantity' => $cartItem['quantity'],
                'unit_price' => $cartItem['price'],
                'total' => $cartItem['subtotal'],
            ]);

            // Create order item extras with quantities
            if (! empty($cartItem['extras'])) {
                foreach ($cartItem['extras'] as $extra) {
                    OrderItemExtra::create([
                        'order_item_id' => $orderItem->id,
                        'extra_option_item_id' => $extra['id'],
                        'extra_name' => $extra['name'],
                        'extra_name_ar' => $extra['name_ar'] ?? null,
                        'extra_price' => $extra['price'],
                        'quantity' => $extra['quantity'] ?? 1,
                    ]);
                }
            }
        }
    }

    /**
     * Generate unique order number
     */
    private function generateOrderNumber(): string
    {
        do {
            $orderNumber = 'ORD-'.mb_strtoupper(Str::random(8));
        } while (Order::where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }

    /**
     * Prepare billing data for Paymob
     */
    private function prepareBillingData(User|GuestUser $user, ?int $addressId, array $billingData): array
    {
        $isGuest = $user instanceof GuestUser;

        if ($isGuest) {
            // For guest users, use GuestUser data
            return [
                'first_name' => $billingData['first_name'] ?? $user->name ?? 'Guest',
                'last_name' => $billingData['last_name'] ?? '',
                'email' => $billingData['email'] ?? $user->email ?? 'guest@example.com',
                'phone_number' => $billingData['phone_number'] ?? $user->full_phone,
                'apartment' => $billingData['apartment'] ?? $user->apartment ?? 'NA',
                'floor' => $billingData['floor'] ?? $user->floor ?? 'NA',
                'street' => $billingData['street'] ?? $user->street ?? 'NA',
                'building' => $billingData['building'] ?? $user->building ?? 'NA',
                'city' => $billingData['city'] ?? $user->city ?? 'Cairo',
                'country' => $billingData['country'] ?? 'EG',
                'postal_code' => $billingData['postal_code'] ?? 'NA',
            ];
        }

        // For registered users, use Address or User data
        $address = $addressId ? Address::find($addressId) : null;

        return [
            'first_name' => $billingData['first_name'] ?? $user->name ?? 'NA',
            'last_name' => $billingData['last_name'] ?? 'NA',
            'email' => $billingData['email'] ?? $user->email ?? 'customer@example.com',
            'phone_number' => $billingData['phone_number'] ?? $user->phone ?? '+201000000000',
            'apartment' => $billingData['apartment'] ?? $address?->apartment ?? 'NA',
            'floor' => $billingData['floor'] ?? $address?->floor ?? 'NA',
            'street' => $billingData['street'] ?? $address?->street ?? 'NA',
            'building' => $billingData['building'] ?? $address?->building ?? 'NA',
            'city' => $billingData['city'] ?? $address?->city ?? 'Cairo',
            'country' => $billingData['country'] ?? 'EG',
            'postal_code' => $billingData['postal_code'] ?? 'NA',
        ];
    }

    /**
     * Check if store is currently open
     */
    private function isStoreOpen(): bool
    {
        $workTimesJson = $this->settingService->get(SettingKey::WORK_TIMES, '[]');
        $workTimes = json_decode($workTimesJson, true) ?? [];

        if (empty($workTimes)) {
            return true; // Assume open if no times defined
        }

        $now = now();
        $currentDay = $now->format('l'); // Sunday, Monday, etc.
        $currentTime = $now->format('H:i');

        foreach ($workTimes as $daySchedule) {
            if (($daySchedule['day'] ?? '') === $currentDay) {
                if (! empty($daySchedule['closed'])) {
                    return false;
                }

                $from = $daySchedule['from'] ?? '00:00';
                $to = $daySchedule['to'] ?? '23:59';

                return $currentTime >= $from && $currentTime <= $to;
            }
        }

        return true; // Assume open if day not found in schedule
    }
}
