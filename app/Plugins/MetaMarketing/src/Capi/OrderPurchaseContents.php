<?php

declare(strict_types=1);

namespace App\Plugins\MetaMarketing\src\Capi;

use App\Plugins\MetaMarketing\src\Support\MetaContentId;
use App\Plugins\OrderManagement\src\Models\Order;

/**
 * Purchase `content_ids` / `contents` / `num_items` for an order. Shared by the server Purchase event
 * and the placement response `tracking` block, so browser and server send identical values.
 */
final readonly class OrderPurchaseContents
{
    /**
     * @param  list<string>  $contentIds
     * @param  list<array{id: string, quantity: int|float, item_price: float}>  $contents
     */
    private function __construct(
        public array $contentIds,
        public array $contents,
        public int $numItems,
    ) {}

    /**
     * Deterministic Purchase event id: retries and the browser Pixel event deduplicate on it.
     */
    public static function eventId(int $orderId): string
    {
        return 'purchase.'.$orderId;
    }

    public static function fromOrder(Order $order): self
    {
        $order->loadMissing('items');

        $contentIds = [];
        $contents = [];
        $numItems = 0;

        foreach ($order->items as $item) {
            $variantId = $item->getAttribute('variant_id');
            $contentId = MetaContentId::for((int) $item->getAttribute('product_id'), $variantId !== null ? (int) $variantId : null);
            $quantity = (float) $item->getAttribute('quantity');

            $contentIds[] = $contentId;
            $contents[] = [
                'id' => $contentId,
                'quantity' => floor($quantity) === $quantity ? (int) $quantity : $quantity,
                'item_price' => (float) $item->getAttribute('unit_price'),
            ];
            $numItems += (int) round($quantity);
        }

        return new self($contentIds, $contents, $numItems);
    }
}
