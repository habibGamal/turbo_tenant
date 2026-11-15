<?php

declare(strict_types=1);

namespace App\Plugins\MetaMarketing\src\Jobs;

use App\Plugins\MetaMarketing\src\Capi\MetaConversionsClient;
use App\Plugins\MetaMarketing\src\DTOs\MetaMarketingConfig;
use App\Plugins\MetaMarketing\src\Models\MetaCapiEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

final class SendMetaRelayEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>  $event
     */
    public function __construct(
        public array $event,
    ) {}

    public function handle(MetaConversionsClient $client, MetaMarketingConfig $config): void
    {
        if (! $config->canSendServerEvents()) {
            return;
        }

        $result = $client->sendEvents([$this->event]);

        if ($result->successful) {
            return;
        }

        // Delivery log: write a meta_capi_events row only on failure for relay events
        MetaCapiEvent::create([
            'event_name' => (string) ($this->event['event_name'] ?? 'relay'),
            'event_id' => (string) ($this->event['event_id'] ?? ''),
            'order_id' => null,
            'status' => 'failed',
            'attempts' => $this->attempts(),
            'http_status' => $result->httpStatus,
            'fbtrace_id' => $result->fbtraceId,
            'events_received' => $result->eventsReceived,
            'error' => $result->errorMessage,
            'payload' => $this->event,
            'sent_at' => null,
        ]);

        if ($result->httpStatus !== null && $result->httpStatus >= 400 && $result->httpStatus < 500) {
            return;
        }

        throw new RuntimeException('Meta CAPI relay event failed: '.($result->errorMessage ?? 'Unknown error'));
    }
}
