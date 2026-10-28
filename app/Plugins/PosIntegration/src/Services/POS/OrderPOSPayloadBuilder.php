<?php

declare(strict_types=1);

namespace App\Plugins\PosIntegration\src\Services\POS;

use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\PosIntegration\src\Models\ProductPosMapping;
use App\Plugins\PosIntegration\src\Services\POS\Contracts\OrderPOSPayloadBuilderInterface;
use App\Plugins\PosIntegration\src\Services\POS\Contracts\POSProductResolverInterface;
use Illuminate\Support\Collection;

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
        if (method_exists($this->productResolver, 'setMappings')) {
            $this->productResolver->setMappings($mappings);
        }

        try {
            foreach ($order->items as $item) {
                $resolvedItem = $this->productResolver->resolveOrderItem($item, $order->branch_id);

                if ($resolvedItem !== null) {
                    $orderItems[] = $resolvedItem;
                }
            }
        } finally {
            if (method_exists($this->productResolver, 'setMappings')) {
                $this->productResolver->setMappings(null);
            }
        }

        $orderData = [
            'type' => $this->mapOrderType($order->type),
            'shiftId' => (int) $order->shift_id,
            'orderNumber' => (string) $order->order_number,
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
        $extraOptionItemIds = $order->items->flatMap->extras->pluck('extra_option_item_id')->filter()->unique()->values()->all();

        if (empty($productIds) && empty($extraOptionItemIds)) {
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
            ->where(function ($query) use ($productIds, $extraOptionItemIds) {
                $hasCondition = false;
                if (! empty($productIds)) {
                    $query->whereIn('product_id', $productIds);
                    $hasCondition = true;
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
    private function mapOrderType(?string $type): string
    {
        return match ($type) {
            'takeaway', 'web_takeaway' => 'web_takeaway',
            default => 'web_delivery',
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

        $paymentMethod = $order->payment_method;
        if (is_string($paymentMethod)) {
            $paymentMethod = \App\Enums\PaymentMethod::tryFrom($paymentMethod);
        }

        $mappedMethod = $paymentMethod instanceof \App\Enums\PaymentMethod
            ? $paymentMethod->posMapping()
            : (string) $order->payment_method;

        $preferences = [
            'payment_method' => $mappedMethod,
        ];

        if (! empty($order->transaction_id)) {
            $preferences['transaction_id'] = (string) $order->transaction_id;
        }

        return $preferences;
    }
}
