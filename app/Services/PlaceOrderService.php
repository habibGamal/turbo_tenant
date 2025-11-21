<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Address;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemExtra;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PlaceOrderService
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly PaymobService $paymobService
    ) {}

    /**
     * Place an order from cart
     */
    public function placeOrder(
        User $user,
        int $branchId,
        PaymentMethod $paymentMethod,
        ?int $addressId = null,
        ?int $couponId = null,
        ?string $note = null,
        string $type = 'web_delivery',
        array $billingData = []
    ): array {
        // Get cart data
        $cart = $this->cartService->getCart($user);

        if (empty($cart['items'])) {
            return [
                'success' => false,
                'error' => 'Cart is empty',
            ];
        }

        try {
            DB::beginTransaction();

            // Calculate order totals
            $totals = $this->calculateOrderTotals($cart['items'], $couponId, $addressId, $type);

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

            // Clear cart after order is created
            // $this->cartService->clearCart($user);

            DB::commit();

            // For COD or Credit, no online payment required
            if (! $paymentMethod->requiresOnlinePayment()) {
                $order->update([
                    'payment_status' => PaymentStatus::PENDING,
                    'status' => 'confirmed',
                ]);

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
            // $redirectionUrl = config('app.url') . '/orders/' . $order->id . '/payment/callback';
            $redirectionUrl = url('/orders/'.$order->id.'/payment/callback');
            $notificationUrl = config('app.url').'/api/webhooks/paymob';

            logger()->info('Creating payment intention', [
                'order_id' => $order->id,
                'redirection_url' => $redirectionUrl,
                'notification_url' => $notificationUrl,
            ]);
            $paymentResult = $this->paymobService->createPaymentIntention(
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

            return [
                'success' => true,
                'order' => $order->fresh(['items.extras', 'user', 'branch', 'address']),
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

        // Extract data from the nested 'data' key that Paymob sends
        $paymentData = $callbackData['data'] ?? $callbackData;

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
        if ($hmac && ! $this->paymobService->validateCallbackHmac($paymentData, $hmac)) {
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
                'status' => 'confirmed',
                'transaction_id' => $transactionId,
                'payment_data' => json_encode([
                    'transaction_id' => $transactionId,
                    'amount_cents' => $amountCents,
                    'currency' => $currency,
                    'response_code' => $responseCode,
                    'message' => $dataMessage,
                    'payment_method' => $paymentData['source_data_type'] ?? null,
                    'card_last_digits' => $paymentData['source_data_pan'] ?? null,
                    'card_type' => $paymentData['source_data_sub_type'] ?? null,
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
            ]),
        ]);

        return [
            'success' => false,
            'error' => $dataMessage ?? 'Payment failed',
            'order' => $order->fresh(['items.extras', 'user', 'branch', 'address']),
        ];
    }

    /**
     * Handle webhook notification from Paymob
     */
    public function handleWebhook(array $webhookData, string $hmac): array
    {
        // Validate HMAC
        if (! $this->paymobService->validateHmac($webhookData['obj'] ?? [], $hmac)) {
            return [
                'success' => false,
                'error' => 'Invalid webhook signature',
            ];
        }

        // Process webhook
        $processedData = $this->paymobService->processWebhook($webhookData);

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
            'payment_method' => $processedData['payment_method'],
            'payment_data' => $processedData['payment_data'],
        ]);

        // Update order status based on payment status
        if ($processedData['payment_status'] === 'completed') {
            $order->update(['status' => 'confirmed']);
        } elseif ($processedData['payment_status'] === 'failed') {
            $order->update(['status' => 'cancelled']);
        }

        return [
            'success' => true,
            'order' => $order,
            'payment_data' => $processedData,
        ];
    }

    /**
     * Calculate order totals
     */
    private function calculateOrderTotals(array $items, ?int $couponId, ?int $addressId, string $type): array
    {
        $subTotal = array_reduce($items, fn ($total, $item) => $total + ($item['subtotal'] ?? 0), 0);

        $discount = 0;
        if ($couponId) {
            $coupon = Coupon::find($couponId);
            if ($coupon) {
                $discount = $this->calculateDiscount($coupon, $subTotal);
            }
        }

        $tax = $this->calculateTax($subTotal - $discount);
        $service = $this->calculateService($subTotal - $discount);
        $deliveryFee = $this->calculateDeliveryFee($addressId, $type);

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
     * Calculate discount based on coupon
     */
    private function calculateDiscount(Coupon $coupon, float $subTotal): float
    {
        // TODO: Implement coupon validation and discount calculation
        // This is a placeholder implementation
        return 0;
    }

    /**
     * Calculate tax (e.g., 14% VAT in Egypt)
     */
    private function calculateTax(float $amount): float
    {
        $taxRate = config('app.tax_rate', 0.14);

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
        User $user,
        int $branchId,
        ?int $addressId,
        ?int $couponId,
        ?string $note,
        string $type,
        array $totals,
        PaymentMethod $paymentMethod
    ): Order {
        return Order::create([
            'order_number' => $this->generateOrderNumber(),
            'user_id' => $user->id,
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
                'variant_name' => $variant['name'] ?? null,
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
    private function prepareBillingData(User $user, ?int $addressId, array $billingData): array
    {
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
}
