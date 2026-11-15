<?php

declare(strict_types=1);

namespace App\Plugins\MetaMarketing\src\Capi;

use App\Plugins\MetaMarketing\src\DTOs\MetaMarketingConfig;
use Illuminate\Support\Facades\Http;
use Throwable;

final class MetaConversionsClient
{
    public function __construct(
        private readonly MetaMarketingConfig $config,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $events
     */
    public function sendEvents(array $events, ?string $testEventCode = null): MetaApiResult
    {
        if (blank($this->config->pixelId) || blank($this->config->accessToken)) {
            return new MetaApiResult(
                successful: false,
                httpStatus: null,
                errorMessage: 'Meta Pixel ID or Access Token is missing.',
            );
        }

        $url = sprintf(
            'https://graph.facebook.com/%s/%s/events',
            $this->config->graphVersion,
            $this->config->pixelId
        );

        $payload = [
            'data' => $events,
            'access_token' => $this->config->accessToken,
        ];

        $code = $testEventCode ?? $this->config->testEventCode;
        if (filled($code)) {
            $payload['test_event_code'] = $code;
        }

        $timeout = (int) config('services.meta.timeout', 10);

        try {
            $response = Http::timeout($timeout)
                ->asJson()
                ->acceptJson()
                ->post($url, $payload);

            $status = $response->status();
            $raw = (array) ($response->json() ?? []);
            $successful = $response->successful();

            $eventsReceived = isset($raw['events_received']) ? (int) $raw['events_received'] : null;
            $fbtraceId = isset($raw['fbtrace_id'])
                ? (string) $raw['fbtrace_id']
                : (isset($raw['error']['fbtrace_id']) ? (string) $raw['error']['fbtrace_id'] : null);

            $errorMessage = null;
            if (! $successful) {
                $errorMessage = isset($raw['error']['message'])
                    ? (string) $raw['error']['message']
                    : ($response->body() !== '' ? $response->body() : 'HTTP error '.$status);
            }

            return new MetaApiResult(
                successful: $successful,
                httpStatus: $status,
                eventsReceived: $eventsReceived,
                fbtraceId: $fbtraceId,
                errorMessage: $errorMessage,
                raw: $raw,
            );
        } catch (Throwable $e) {
            return new MetaApiResult(
                successful: false,
                httpStatus: null,
                errorMessage: $e->getMessage(),
                raw: [],
            );
        }
    }

    public function verifyCredentials(): MetaApiResult
    {
        if (blank($this->config->pixelId) || blank($this->config->accessToken)) {
            return new MetaApiResult(
                successful: false,
                httpStatus: null,
                errorMessage: 'Meta Pixel ID or Access Token is missing.',
            );
        }

        $url = sprintf(
            'https://graph.facebook.com/%s/%s',
            $this->config->graphVersion,
            $this->config->pixelId
        );

        $timeout = (int) config('services.meta.timeout', 10);

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->get($url, [
                    'fields' => 'id,name',
                    'access_token' => $this->config->accessToken,
                ]);

            $status = $response->status();
            $raw = (array) ($response->json() ?? []);
            $successful = $response->successful();

            $fbtraceId = isset($raw['fbtrace_id'])
                ? (string) $raw['fbtrace_id']
                : (isset($raw['error']['fbtrace_id']) ? (string) $raw['error']['fbtrace_id'] : null);

            $errorMessage = null;
            if (! $successful) {
                $errorMessage = isset($raw['error']['message'])
                    ? (string) $raw['error']['message']
                    : ($response->body() !== '' ? $response->body() : 'HTTP error '.$status);
            }

            return new MetaApiResult(
                successful: $successful,
                httpStatus: $status,
                eventsReceived: null,
                fbtraceId: $fbtraceId,
                errorMessage: $errorMessage,
                raw: $raw,
            );
        } catch (Throwable $e) {
            return new MetaApiResult(
                successful: false,
                httpStatus: null,
                errorMessage: $e->getMessage(),
                raw: [],
            );
        }
    }
}
