<?php

declare(strict_types=1);

namespace App\Plugins\MetaMarketing\src\Http\Responders;

use App\Core\Contracts\Customer;
use App\Plugins\MetaMarketing\src\Capi\OrderPurchaseContents;
use App\Plugins\MetaMarketing\src\DTOs\MetaMarketingConfig;
use App\Plugins\OrderManagement\src\Contracts\OrderPlacementResponseContributor;
use App\Plugins\OrderManagement\src\Models\Order;

/**
 * Adds `tracking` (purchase event id, server totals, contents) to the order placement response
 * so the browser Pixel Purchase uses the same values and event id as the server event.
 */
final class PurchaseTrackingResponseContributor implements OrderPlacementResponseContributor
{
    public function __construct(
        private readonly MetaMarketingConfig $config,
    ) {}

    public function pluginId(): string
    {
        return 'meta-marketing';
    }

    public function extend(Order $order, Customer $customer, array $response): array
    {
        $purchase = OrderPurchaseContents::fromOrder($order);

        $response['tracking'] = [
            'purchase_event_id' => OrderPurchaseContents::eventId($order->id),
            'order_id' => (string) $order->order_number,
            'value' => (float) $order->total,
            'currency' => $this->config->currency,
            'content_type' => 'product',
            'content_ids' => $purchase->contentIds,
            'contents' => $purchase->contents,
            'num_items' => $purchase->numItems,
        ];

        return $response;
    }
}
