<?php

declare(strict_types=1);

namespace App\Plugins\OrderManagement\src\Services\Repositories;

use App\Plugins\CartEngine\src\DTOs\CartLine;
use App\Plugins\CartEngine\src\DTOs\CartLineComponent;
use App\Plugins\CartEngine\src\DTOs\CartLineExtra;
use App\Plugins\CartEngine\src\Services\Contracts\CartServiceInterface;
use App\Plugins\LocationsDelivery\src\Contracts\AddressBook;
use App\Plugins\OrderManagement\src\DTOs\OrderTotals;
use App\Plugins\OrderManagement\src\DTOs\PlaceOrderData;
use App\Plugins\OrderManagement\src\Enums\OrderStatus;
use App\Plugins\OrderManagement\src\Enums\OrderType;
use App\Plugins\OrderManagement\src\Enums\PaymentStatus;
use App\Plugins\OrderManagement\src\Events\OrderPaymentFailed;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Models\OrderItem;
use App\Plugins\OrderManagement\src\Models\OrderItemComponent;
use App\Plugins\OrderManagement\src\Models\OrderItemExtra;
use App\Plugins\OrderManagement\src\Pipelines\Context\OrderValidationContext;
use App\Plugins\OrderManagement\src\Services\Contracts\OrderRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class OrderRepository implements OrderRepositoryInterface
{
    public function __construct(
        private readonly CartServiceInterface $cartService,
        private readonly AddressBook $addressBook
    ) {}

    /**
     * @param  list<CartLine>  $lines
     */
    public function createOrderWithItems(
        PlaceOrderData $data,
        array $lines,
        OrderTotals $totals,
        ?OrderValidationContext $validationContext = null
    ): Order {
        return DB::transaction(function () use ($data, $lines, $totals, $validationContext): Order {
            $isGuest = $data->user->isGuest();

            $deliveryAddress = null;
            if ($data->addressId) {
                $deliveryAddress = $this->addressBook->snapshotAddress((int) $data->addressId);
            } elseif ($isGuest && $data->type === OrderType::WEB_DELIVERY->value) {
                $address = $data->user->customerAddress();
                $deliveryAddress = [
                    'street' => $address['street'] ?? null,
                    'building' => $address['building'] ?? null,
                    'floor' => $address['floor'] ?? null,
                    'apartment' => $address['apartment'] ?? null,
                    'city' => $address['city'] ?? null,
                    'area_id' => $address['area_id'] ?? null,
                ];

                if (! empty($address['area_id'])) {
                    $labels = $this->addressBook->areaLabels((int) $address['area_id']);
                    if (isset($labels['area_name'])) {
                        $deliveryAddress['area_name'] = $labels['area_name'];
                    }
                    if (isset($labels['governorate_name'])) {
                        $deliveryAddress['governorate_name'] = $labels['governorate_name'];
                    }
                }
            }

            $customerName = $data->user->customerName();
            $customerPhone = $data->user->customerPhone() ?? ($deliveryAddress['phone_number'] ?? null);
            $customerEmail = $data->user->customerEmail();

            $couponId = $validationContext?->appliedPromotionId();
            $couponCode = $validationContext?->appliedPromotionCode();

            $customerId = $data->user->customerId();

            $order = Order::create([
                'order_number' => $this->generateOrderNumber(),
                'user_id' => $isGuest ? null : $customerId,
                'guest_user_id' => $isGuest ? $customerId : null,
                'branch_id' => $data->branchId,
                'address_id' => $data->addressId,
                'coupon_id' => $couponId,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'customer_email' => $customerEmail,
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

            $this->createOrderItems($order, $lines);

            // Online payment initialization may fail; retain the cart until a
            // usable checkout URL has actually been created.
            if (! $data->paymentMethod->requiresOnlinePayment()) {
                $this->cartService->clearCart($data->user);
            }

            return $order;
        });
    }

    public function finalizeOfflineOrder(Order $order): Order
    {
        $order->update([
            'payment_status' => PaymentStatus::PENDING,
            'status' => OrderStatus::PENDING,
        ]);

        return $order->fresh(['items.extras', 'items.components', 'user', 'branch', 'address']);
    }

    public function markOrderAsPaymentFailed(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $order->update([
                'status' => 'cancelled',
                'payment_status' => PaymentStatus::FAILED,
            ]);
        });

        OrderPaymentFailed::dispatch($order);
    }

    /**
     * @param  list<CartLine>  $lines
     */
    private function createOrderItems(Order $order, array $lines): void
    {
        foreach ($lines as $line) {
            $orderItem = OrderItem::create([
                'order_id' => $order->id,
                'line_type' => $line->type,
                'line_reference_id' => $line->referenceId,
                'product_id' => $line->productId,
                'variant_id' => $line->variantId,
                'weight_option_value_id' => $line->weightOptionValueId,
                'weight_multiplier' => $line->weightMultiplier,
                'product_name' => $line->productName,
                'product_name_ar' => $line->productNameAr,
                'variant_name' => $line->variantName,
                'variant_name_ar' => $line->variantNameAr,
                'notes' => $line->notes,
                'weight_label' => $line->weightLabel,
                'extras_unit_total' => $line->extrasUnitTotal,
                'quantity' => $line->quantity,
                'unit_price' => $line->unitPrice,
                'total' => $line->subtotal,
            ]);

            $this->createOrderItemExtras($orderItem, $line->extras);
            $this->createOrderItemComponents($orderItem, $line->components);
        }
    }

    /**
     * @param  list<CartLineComponent>  $components
     */
    private function createOrderItemComponents(OrderItem $orderItem, array $components): void
    {
        foreach ($components as $component) {
            $extrasSnapshot = ! empty($component->extras)
                ? array_values(array_map(fn (CartLineExtra $extra): array => [
                    'extra_option_item_id' => $extra->extraOptionItemId,
                    'name' => $extra->name,
                    'name_ar' => $extra->nameAr,
                    'price' => $extra->unitPrice,
                    'quantity' => $extra->quantity,
                ], $component->extras))
                : null;

            OrderItemComponent::create([
                'order_item_id' => $orderItem->id,
                'source_id' => $component->sourceId,
                'group_name' => $component->groupName,
                'group_name_ar' => $component->groupNameAr,
                'product_id' => $component->productId,
                'variant_id' => $component->variantId,
                'product_name' => $component->productName,
                'product_name_ar' => $component->productNameAr,
                'variant_name' => $component->variantName,
                'variant_name_ar' => $component->variantNameAr,
                'quantity' => $component->quantity,
                'unit_price_adjustment' => $component->unitPriceAdjustment,
                'sort_order' => $component->sortOrder,
                'extras' => $extrasSnapshot,
                'notes' => $component->notes,
            ]);
        }
    }

    /**
     * @param  list<CartLineExtra>  $extras
     */
    private function createOrderItemExtras(OrderItem $orderItem, array $extras): void
    {
        foreach ($extras as $extra) {
            OrderItemExtra::create([
                'order_item_id' => $orderItem->id,
                'extra_option_item_id' => $extra->extraOptionItemId,
                'extra_name' => $extra->name,
                'extra_name_ar' => $extra->nameAr,
                'extra_price' => $extra->unitPrice,
                'quantity' => $extra->quantity,
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
