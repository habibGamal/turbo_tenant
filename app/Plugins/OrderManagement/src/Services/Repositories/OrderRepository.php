<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Services\Repositories;

use App\Core\Plugins\Services\PluginManager;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Plugins\CartEngine\src\Services\CartService;
use App\Plugins\OrderManagement\src\DTOs\OrderTotals;
use App\Plugins\OrderManagement\src\DTOs\PlaceOrderData;
use App\Plugins\OrderManagement\src\Enums\OrderPosStatus;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Models\OrderItem;
use App\Plugins\OrderManagement\src\Models\OrderItemExtra;
use App\Plugins\OrderManagement\src\Pipelines\Context\OrderValidationContext;
use App\Plugins\OrderManagement\src\Services\Contracts\OrderRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class OrderRepository implements OrderRepositoryInterface
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly PluginManager $pluginManager
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $cartItems
     */
    public function createOrderWithItems(
        PlaceOrderData $data,
        array $cartItems,
        OrderTotals $totals,
        ?OrderValidationContext $validationContext = null
    ): Order {
        return DB::transaction(function () use ($data, $cartItems, $totals, $validationContext): Order {
            $isGuest = $data->isGuest();

            $deliveryAddress = null;
            $address = null;
            if ($data->addressId && $this->pluginManager->isPluginActive('locations-delivery')) {
                $address = \App\Plugins\LocationsDelivery\src\Models\Address::with(['area.governorate'])->find($data->addressId);
                if ($address) {
                    $deliveryAddress = $address->toArray();
                    if ($address->area) {
                        $deliveryAddress['area_name'] = $address->area->name;
                        if ($address->area->governorate) {
                            $deliveryAddress['governorate_name'] = $address->area->governorate->name;
                        }
                    }
                }
            }

            $customerName = $data->user?->name ?? null;
            $customerPhone = $data->user?->phone ?? $address?->phone_number ?? null;

            $couponId = null;
            $couponCode = null;
            if ($this->pluginManager->isPluginActive('coupons-promotions')) {
                $couponId = $validationContext?->get('coupon_id') ?? $data->couponId;
                $appliedCoupon = $validationContext?->get('applied_coupon');
                if (! $appliedCoupon && $couponId) {
                    $appliedCoupon = \App\Plugins\CouponsPromotions\src\Models\Coupon::find($couponId);
                }
                $couponCode = $appliedCoupon?->code ?? null;
            }

            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'user_id' => $isGuest ? null : $data->user->id,
                'guest_user_id' => $isGuest ? $data->user->id : null,
                'branch_id' => $data->branchId,
                'address_id' => $data->addressId,
                'coupon_id' => $couponId,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'delivery_address' => $deliveryAddress,
                'coupon_code' => $couponCode,
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
            $weightOptionValue = $cartItem['weight_option_value'] ?? null;

            $weightLabel = null;
            if (! empty($weightOptionValue)) {
                $weightLabel = $weightOptionValue['label'] ?? (isset($weightOptionValue['value']) ? (string) $weightOptionValue['value'] : null);
            }

            $extrasUnitTotal = 0.0;
            if (! empty($cartItem['extras'])) {
                foreach ($cartItem['extras'] as $extra) {
                    $extraPrice = (float) ($extra['price'] ?? 0);
                    $extraQty = (int) ($extra['quantity'] ?? 1);
                    $extrasUnitTotal += $extraPrice * $extraQty;
                }
            }

            $orderItem = OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product['id'],
                'variant_id' => $variant['id'] ?? null,
                'weight_option_value_id' => $weightOptionValue['id'] ?? null,
                'weight_multiplier' => $cartItem['weight_multiplier'] ?? 1,
                'product_name' => $product['name'],
                'product_name_ar' => $product['name_ar'] ?? null,
                'variant_name' => $variant['name'] ?? null,
                'variant_name_ar' => $variant['name_ar'] ?? null,
                'notes' => $cartItem['notes'] ?? null,
                'weight_label' => $weightLabel,
                'extras_unit_total' => $extrasUnitTotal,
                'quantity' => $cartItem['quantity'],
                'unit_price' => $cartItem['price'],
                'total' => $cartItem['subtotal'],
            ]);

            if (! empty($cartItem['extras'])) {
                $this->createOrderItemExtras($orderItem, $cartItem['extras']);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $extras
     */
    private function createOrderItemExtras(OrderItem $orderItem, array $extras): void
    {
        foreach ($extras as $extra) {
            $extraOptionItem = $extra['extra_option_item'] ?? null;

            OrderItemExtra::create([
                'order_item_id' => $orderItem->id,
                'extra_option_item_id' => $extra['id'] ?? ($extraOptionItem['id'] ?? null),
                'extra_name' => $extra['name'] ?? ($extraOptionItem['name'] ?? 'Extra'),
                'extra_name_ar' => $extra['name_ar'] ?? ($extraOptionItem['name_ar'] ?? null),
                'extra_price' => $extra['price'],
                'quantity' => $extra['quantity'] ?? 1,
            ]);
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
