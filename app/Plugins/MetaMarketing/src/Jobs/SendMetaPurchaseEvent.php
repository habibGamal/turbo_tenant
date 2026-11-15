<?php

declare(strict_types=1);

namespace App\Plugins\MetaMarketing\src\Jobs;

use App\Plugins\MetaMarketing\src\Capi\MetaConversionsClient;
use App\Plugins\MetaMarketing\src\Capi\OrderPurchaseContents;
use App\Plugins\MetaMarketing\src\Capi\UserDataBuilder;
use App\Plugins\MetaMarketing\src\DTOs\MetaMarketingConfig;
use App\Plugins\MetaMarketing\src\Models\MetaCapiEvent;
use App\Plugins\MetaMarketing\src\Models\MetaOrderTracking;
use App\Plugins\OrderManagement\src\Enums\OrderType;
use App\Plugins\OrderManagement\src\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

final class SendMetaPurchaseEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /**
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 300, 900, 3600];

    /**
     * @param  int  $eventTime  Unix time of the conversion (placement for COD, payment for online), fixed at
     *                          dispatch so queue retries report the real conversion time.
     */
    public function __construct(
        public int $orderId,
        public int $eventTime,
    ) {
        $this->afterCommit();
    }

    public function handle(
        MetaConversionsClient $client,
        UserDataBuilder $userDataBuilder,
        MetaMarketingConfig $config,
    ): void {
        if (! $config->canSendServerEvents()) {
            return;
        }

        $order = Order::with('items')->find($this->orderId);
        if ($order === null) {
            return;
        }

        if ($order->type === OrderType::POS || $order->type === 'pos') {
            return;
        }

        $tracking = MetaOrderTracking::where('order_id', $order->id)->first();
        if ($tracking?->purchase_sent_at !== null) {
            return;
        }

        $eventId = OrderPurchaseContents::eventId($order->id);
        $eventSourceUrl = ($tracking !== null && filled($tracking->event_source_url))
            ? (string) $tracking->event_source_url
            : url('/checkout');
        $userData = $userDataBuilder->forOrder($order, $tracking);

        $purchase = OrderPurchaseContents::fromOrder($order);

        $customData = [
            'currency' => $config->currency,
            'value' => (float) $order->total,
            'order_id' => (string) $order->order_number,
            'content_type' => 'product',
            'content_ids' => $purchase->contentIds,
            'contents' => $purchase->contents,
            'num_items' => $purchase->numItems,
        ];

        $event = [
            'event_name' => 'Purchase',
            'event_time' => $this->eventTime,
            'event_id' => $eventId,
            'action_source' => 'website',
            'event_source_url' => $eventSourceUrl,
            'user_data' => $userData,
            'custom_data' => $customData,
        ];

        $capiEvent = MetaCapiEvent::firstOrNew([
            'event_name' => 'Purchase',
            'event_id' => $eventId,
        ]);
        $capiEvent->order_id = $order->id;
        $capiEvent->payload = $event;
        $capiEvent->attempts = ($capiEvent->attempts ?? 0) + 1;
        $capiEvent->status = 'pending';
        $capiEvent->save();

        $result = $client->sendEvents([$event]);

        if ($result->successful) {
            $capiEvent->update([
                'status' => 'sent',
                'http_status' => $result->httpStatus,
                'fbtrace_id' => $result->fbtraceId,
                'events_received' => $result->eventsReceived,
                'error' => null,
                'sent_at' => now(),
            ]);

            if ($tracking !== null) {
                $tracking->update(['purchase_sent_at' => now()]);
            } else {
                MetaOrderTracking::create([
                    'order_id' => $order->id,
                    'purchase_event_id' => $eventId,
                    'purchase_sent_at' => now(),
                ]);
            }

            return;
        }

        $capiEvent->update([
            'status' => 'failed',
            'http_status' => $result->httpStatus,
            'fbtrace_id' => $result->fbtraceId,
            'events_received' => $result->eventsReceived,
            'error' => $result->errorMessage,
        ]);

        if ($result->httpStatus !== null && $result->httpStatus >= 400 && $result->httpStatus < 500) {
            return;
        }

        throw new RuntimeException('Meta CAPI purchase event failed: '.($result->errorMessage ?? 'Unknown error'));
    }
}
