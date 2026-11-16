<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Services\POS;

use App\Plugins\OrderManagement\src\Enums\OrderType;
use App\Plugins\OrderManagement\src\Enums\PaymentMethod;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\PosIntegration\src\Models\ProductPosMapping;
use App\Plugins\PosIntegration\src\Services\POS\Contracts\OrderPOSPayloadBuilderInterface;
use App\Plugins\PosIntegration\src\Services\POS\Contracts\POSProductResolverInterface;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class OrderPOSPayloadBuilder implements OrderPOSPayloadBuilderInterface
{
    public function __construct(
        private readonly POSProductResolverInterface $productResolver,
        private readonly POSCustomerDataFormatter $customerFormatter,
    ) {}

    /**
     * Build the order payload for the POS system conforming to CREATE_ORDER_API_DOC.md.
     *
     * @return array{
     *     user: array{name: string, phone: string, area: string, address: string},
     *     order: array<string, mixed>
     * }
     */
    public function build(Order $order): array
    {
        $userData = $this->customerFormatter->format($order);
        $orderItems = [];

        // Preload all mappings for the whole order in ONE query
        $mappings = $this->loadMappingsForOrder($order);
        $lineMappings = method_exists($this->productResolver, 'loadLineMappingsForOrder')
            ? $this->productResolver->loadLineMappingsForOrder($order)
            : collect();

        $resolver = method_exists($this->productResolver, 'withLineMappings')
            ? $this->productResolver->withMappings($mappings)->withLineMappings($lineMappings)
            : $this->productResolver->withMappings($mappings);

        foreach ($order->items as $item) {
            $resolvedItem = $resolver->resolveOrderItem($item, $order->branch_id);

            if ($resolvedItem !== null) {
                $orderItems[] = $resolvedItem;
            }
        }

        $orderData = [
            'type' => $this->mapOrderType($order->type),
            'shiftId' => (int) $order->shift_id,
            'orderNumber' => (string) $order->order_number,
            'idempotencyKey' => $this->idempotencyKey($order),
            'subTotal' => (float) $order->sub_total,
            'tax' => (float) $order->tax,
            'service' => (float) ($order->delivery_fee + $order->service),
            'discount' => (float) $order->discount,
            'total' => (float) $order->total,
            'items' => $orderItems,
        ];

        if (! empty($order->note)) {
            $orderData['note'] = $order->note;
        }

        $webPreferences = $this->buildWebPreferences($order);

        if ($webPreferences !== null) {
            $orderData['webPreferences'] = $webPreferences;
        }

        return [
            'user' => $userData,
            'order' => $orderData,
        ];
    }

    /**
     * A key that stays the same for every send of this order (retries and
     * manual resends), so the POS can tell a repeat of an order it already
     * saved from a different order reusing the number.
     */
    public function idempotencyKey(Order $order): string
    {
        $tenantKey = tenant() ? (string) tenant()->getTenantKey() : 'central';

        return hash('sha256', implode('|', ['web-order', $tenantKey, (string) $order->id, (string) $order->order_number]));
    }

    /**
     * Load all mappings for an order in a single database query.
     *
     * @return Collection<int, ProductPosMapping>
     */
    public function loadMappingsForOrder(Order $order): Collection
    {
        if (method_exists($this->productResolver, 'loadMappingsForOrder')) {
            return $this->productResolver->loadMappingsForOrder($order);
        }

        $productIds = $order->items->pluck('product_id')->filter()->unique()->values()->all();
        $variantIds = [];
        $extraOptionItemIds = $order->items->flatMap->extras->pluck('extra_option_item_id')->filter()->unique()->values()->all();

        foreach ($order->items as $item) {
            if ($item->relationLoaded('components')) {
                foreach ($item->components as $component) {
                    if ($component->product_id) {
                        $productIds[] = $component->product_id;
                    }
                    if ($component->variant_id) {
                        $variantIds[] = $component->variant_id;
                    }
                    if (! empty($component->extras) && is_array($component->extras)) {
                        foreach ($component->extras as $extra) {
                            $extraId = $extra['extra_option_item_id'] ?? $extra['id'] ?? null;
                            if ($extraId) {
                                $extraOptionItemIds[] = (int) $extraId;
                            }
                        }
                    }
                }
            }
        }

        $productIds = array_values(array_unique(array_filter($productIds)));
        $variantIds = array_values(array_unique(array_filter($variantIds)));

        if (empty($productIds) && empty($variantIds) && empty($extraOptionItemIds)) {
            return collect();
        }

        return ProductPosMapping::query()
            ->where(function ($query) use ($order) {
                if ($order->branch_id !== null) {
                    $query->where('branch_id', $order->branch_id)
                        ->orWhereNull('branch_id');
                } else {
                    $query->whereNull('branch_id');
                }
            })
            ->where(function ($query) use ($productIds, $variantIds, $extraOptionItemIds) {
                $hasCondition = false;
                if (! empty($productIds)) {
                    $query->whereIn('product_id', $productIds);
                    $hasCondition = true;
                }
                if (! empty($variantIds)) {
                    if ($hasCondition) {
                        $query->orWhereIn('variant_id', $variantIds);
                    } else {
                        $query->whereIn('variant_id', $variantIds);
                        $hasCondition = true;
                    }
                }
                if (! empty($extraOptionItemIds)) {
                    if ($hasCondition) {
                        $query->orWhereIn('extra_option_item_id', $extraOptionItemIds);
                    } else {
                        $query->whereIn('extra_option_item_id', $extraOptionItemIds);
                    }
                }
            })
            ->get();
    }

    /**
     * Map order type to POS expected type enum ('web_delivery' or 'web_takeaway').
     */
    private function mapOrderType(OrderType $type): string
    {
        return match ($type) {
            OrderType::WEB_DELIVERY => 'web_delivery',
            OrderType::WEB_TAKEAWAY => 'web_takeaway',
            OrderType::POS => throw new InvalidArgumentException('نوع الطلب غير مدعوم في نقطة البيع: pos'),
        };
    }

    /**
     * Map payment method to POS expected method ('cash' or 'card').
     */
    private function mapPaymentMethod(PaymentMethod $method): string
    {
        return match ($method) {
            PaymentMethod::COD => 'cash',
            default => 'card',
        };
    }

    /**
     * Build web preferences (online payment metadata) if payment method exists.
     *
     * @return array{payment_method: string, transaction_id?: string}|null
     */
    private function buildWebPreferences(Order $order): ?array
    {
        if (! $order->payment_method) {
            return null;
        }

        $paymentMethod = $order->payment_method instanceof PaymentMethod
            ? $order->payment_method
            : PaymentMethod::from((string) $order->payment_method);

        $preferences = [
            'payment_method' => $this->mapPaymentMethod($paymentMethod),
        ];

        if (! empty($order->transaction_id)) {
            $preferences['transaction_id'] = (string) $order->transaction_id;
        }

        return $preferences;
    }
}
