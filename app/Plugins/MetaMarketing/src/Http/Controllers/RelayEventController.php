<?php

declare(strict_types=1);

namespace App\Plugins\MetaMarketing\src\Http\Controllers;

use App\Plugins\MetaMarketing\src\Capi\UserDataBuilder;
use App\Plugins\MetaMarketing\src\DTOs\MetaMarketingConfig;
use App\Plugins\MetaMarketing\src\Http\Requests\RelayEventRequest;
use App\Plugins\MetaMarketing\src\Jobs\SendMetaRelayEvent;
use Illuminate\Http\Response;

/**
 * POST /api/meta/events - relays storefront funnel events to the Conversions API.
 */
final class RelayEventController
{
    public function __construct(
        private readonly MetaMarketingConfig $config,
        private readonly UserDataBuilder $userDataBuilder,
    ) {}

    public function __invoke(RelayEventRequest $request): Response
    {
        if (! $this->config->canSendServerEvents()) {
            return response()->noContent();
        }

        $userData = $this->userDataBuilder->forRequest($request);

        $event = [
            'event_name' => (string) $request->validated('event_name'),
            'event_time' => now()->getTimestamp(),
            'event_id' => (string) $request->validated('event_id'),
            'action_source' => 'website',
            'event_source_url' => (string) $request->validated('event_source_url'),
            'user_data' => $userData,
        ];

        $customData = $request->customData();
        if (! empty($customData)) {
            $event['custom_data'] = $customData;
        }

        SendMetaRelayEvent::dispatch($event);

        return response()->noContent();
    }
}
