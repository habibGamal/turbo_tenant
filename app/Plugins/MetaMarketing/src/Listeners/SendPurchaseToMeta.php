<?php

declare(strict_types=1);

namespace App\Plugins\MetaMarketing\src\Listeners;

use App\Plugins\MetaMarketing\src\DTOs\MetaMarketingConfig;
use App\Plugins\MetaMarketing\src\Jobs\SendMetaPurchaseEvent;
use App\Plugins\OrderManagement\src\Enums\OrderType;
use App\Plugins\OrderManagement\src\Models\Order;

/**
 * Handles OrderFinalized (COD/offline, fired at placement) and OrderPaymentCompleted (online).
 */
final class SendPurchaseToMeta
{
    public function __construct(
        private readonly MetaMarketingConfig $config,
    ) {}

    public function handle(object $event): void
    {
        if (! property_exists($event, 'order')) {
            return;
        }

        /** @var Order $order */
        $order = $event->order;

        if ($order->type === OrderType::POS || $order->type === 'pos') {
            return;
        }

        if (! $this->config->canSendServerEvents()) {
            return;
        }

        SendMetaPurchaseEvent::dispatch($order->id, now()->getTimestamp());
    }
}
