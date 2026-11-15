<?php

declare(strict_types=1);

namespace App\Plugins\MetaMarketing\src\Diagnostics;

use App\Plugins\MetaMarketing\src\Capi\MetaApiResult;
use App\Plugins\MetaMarketing\src\Capi\MetaConversionsClient;
use App\Plugins\MetaMarketing\src\Capi\UserDataBuilder;
use App\Plugins\MetaMarketing\src\DTOs\MetaMarketingConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class MetaDiagnosticsService
{
    public function __construct(
        private readonly MetaConversionsClient $client,
        private readonly MetaMarketingConfig $config,
        private readonly UserDataBuilder $userDataBuilder,
    ) {}

    public function verifyCredentials(): MetaApiResult
    {
        return $this->client->verifyCredentials();
    }

    public function sendTestPageView(?Request $request = null, ?string $testEventCode = null): MetaApiResult
    {
        $request ??= request();

        $eventId = 'diagnostics.'.(string) Str::uuid();
        $userData = $this->userDataBuilder->forRequest($request);

        $event = [
            'event_name' => 'PageView',
            'event_time' => now()->getTimestamp(),
            'event_id' => $eventId,
            'action_source' => 'website',
            'event_source_url' => url('/'),
            'user_data' => $userData,
        ];

        return $this->client->sendEvents([$event], $testEventCode ?? $this->config->testEventCode);
    }

    public function getConfig(): MetaMarketingConfig
    {
        return $this->config;
    }
}
