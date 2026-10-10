<?php

declare(strict_types=1);

namespace App\Services\Order\Repositories;

use App\DTOs\Order\OrderTotals;
use App\DTOs\Order\PlaceOrderData;
use App\Enums\OrderPosStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemExtra;
use App\Services\CartService;
use App\Services\CouponService;
use App\Services\Order\Contracts\OrderRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class OrderRepository implements OrderRepositoryInterface
{
    public function __construct(
        private CartService $cartService,
        private CouponService $couponService
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $cartItems
     */
    public function createOrderWithItems(
        PlaceOrderData $data,
        array $cartItems,
        OrderTotals $totals,
        ?Coupon $coupon
    ): Order {
        return DB::transaction(function () use ($data, $cartItems, $totals, $coupon): Order {
            $isGuest = $data->isGuest();

            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'user_id' => $isGuest ? null : $data->user->id,
                'guest_user_id' => $isGuest ? $data->user->id : null,
                'branch_id' => $data->branchId,
                'address_id' => $data->addressId,
                'coupon_id' => $data->couponId,
                'note' => $data->note,
                'type' => $data->type,
                'status' => 'pending',
                'payment_status' => PaymentStatus::PENDING,
                'payment_method' => $data->paymentMethod,
                'sub_total' => $totals->subTotal,
                'discount' => $totals->discount,
                'tax' => $totals->tax,
                'service' => $totals->service,
                'delivery_fee' => $totals->deliveryFee,
                'total' => $totals->total,
            ]);

            $this->createOrderItems($order, $cartItems);

            if ($coupon && $totals->discount > 0) {
                $this->couponService->applyCoupon($coupon, $totals->discount);
            }

            $this->cartService->clearCart($data->user);

            return $order;
        });
    }

    public function finalizeOfflineOrder(Order $order): Order
    {
        $order->update([
            'payment_status' => PaymentStatus::PENDING,
            'status' => OrderStatus::PENDING,
            'pos_status' => OrderPosStatus::READY,
        ]);

        return $order->fresh(['items.extras', 'user', 'branch', 'address']);
    }

    public function markOrderAsPaymentFailed(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $order->update([
                'status' => 'cancelled',
                'payment_status' => PaymentStatus::FAILED,
            ]);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $cartItems
     */
    private function createOrderItems(Order $order, array $cartItems): void
    {
        foreach ($cartItems as $cartItem) {
            $product = $cartItem['product'];
            $variant = $cartItem['variant'] ?? null;

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

    private function generateOrderNumber(): string
    {
        do {
            $orderNumber = 'ORD-'.mb_strtoupper(Str::random(8));
        } while (Order::where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }
}
