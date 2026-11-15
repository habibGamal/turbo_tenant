<?php

declare(strict_types=1);

use App\Plugins\MetaMarketing\src\Capi\MetaConversionsClient;
use App\Plugins\MetaMarketing\src\DTOs\MetaMarketingConfig;
use Illuminate\Support\Facades\Http;

test('sendEvents posts data and access_token to Meta Graph API and returns parsed result', function () {
    Http::fake([
        'https://graph.facebook.com/v26.0/987654321/events' => Http::response([
            'events_received' => 1,
            'fbtrace_id' => 'trace_abc_123',
            'messages' => [],
        ], 200),
    ]);

    $config = new MetaMarketingConfig(
        pixelId: '987654321',
        debugMode: false,
        capiEnabled: true,
        accessToken: 'EAAB_test_token',
        testEventCode: 'TEST999',
        graphVersion: 'v26.0',
        catalogEnabled: false,
        catalogFeedToken: null,
        catalogBrand: 'Test Store',
        currency: 'EGP',
    );

    $client = new MetaConversionsClient($config);

    $event = [
        'event_name' => 'Purchase',
        'event_time' => 1762370000,
        'event_id' => 'purchase.10',
        'action_source' => 'website',
        'user_data' => ['em' => ['some_hash']],
    ];

    $result = $client->sendEvents([$event]);

    expect($result->successful)->toBeTrue()
        ->and($result->httpStatus)->toBe(200)
        ->and($result->eventsReceived)->toBe(1)
        ->and($result->fbtraceId)->toBe('trace_abc_123')
        ->and($result->errorMessage)->toBeNull();

    Http::assertSent(function (Illuminate\Http\Client\Request $request) use ($event) {
        return $request->url() === 'https://graph.facebook.com/v26.0/987654321/events'
            && $request['access_token'] === 'EAAB_test_token'
            && $request['test_event_code'] === 'TEST999'
            && $request['data'] === [$event];
    });
});

test('sendEvents allows overriding testEventCode', function () {
    Http::fake([
        'https://graph.facebook.com/v26.0/987654321/events' => Http::response([
            'events_received' => 1,
            'fbtrace_id' => 'trace_override',
        ], 200),
    ]);

    $config = new MetaMarketingConfig(
        pixelId: '987654321',
        debugMode: false,
        capiEnabled: true,
        accessToken: 'EAAB_test_token',
        testEventCode: 'DEFAULT_CODE',
        graphVersion: 'v26.0',
        catalogEnabled: false,
        catalogFeedToken: null,
        catalogBrand: 'Test Store',
        currency: 'EGP',
    );

    $client = new MetaConversionsClient($config);
    $result = $client->sendEvents([['event_name' => 'PageView']], 'CUSTOM_OVERRIDE');

    expect($result->successful)->toBeTrue();

    Http::assertSent(function (Illuminate\Http\Client\Request $request) {
        return $request['test_event_code'] === 'CUSTOM_OVERRIDE';
    });
});

test('sendEvents handles HTTP 400 error without throwing', function () {
    Http::fake([
        'https://graph.facebook.com/v26.0/987654321/events' => Http::response([
            'error' => [
                'message' => 'Invalid parameter in custom_data',
                'type' => 'OAuthException',
                'code' => 100,
                'fbtrace_id' => 'error_trace_400',
            ],
        ], 400),
    ]);

    $config = new MetaMarketingConfig(
        pixelId: '987654321',
        debugMode: false,
        capiEnabled: true,
        accessToken: 'EAAB_test_token',
        testEventCode: null,
        graphVersion: 'v26.0',
        catalogEnabled: false,
        catalogFeedToken: null,
        catalogBrand: 'Test Store',
        currency: 'EGP',
    );

    $client = new MetaConversionsClient($config);
    $result = $client->sendEvents([['event_name' => 'Purchase']]);

    expect($result->successful)->toBeFalse()
        ->and($result->httpStatus)->toBe(400)
        ->and($result->eventsReceived)->toBeNull()
        ->and($result->fbtraceId)->toBe('error_trace_400')
        ->and($result->errorMessage)->toBe('Invalid parameter in custom_data');
});

test('verifyCredentials calls Meta Graph API GET fields=id,name with token', function () {
    Http::fake([
        'https://graph.facebook.com/v26.0/987654321?fields=id%2Cname&access_token=EAAB_test_token' => Http::response([
            'id' => '987654321',
            'name' => 'My Pixel',
            'fbtrace_id' => 'verify_trace_123',
        ], 200),
    ]);

    $config = new MetaMarketingConfig(
        pixelId: '987654321',
        debugMode: false,
        capiEnabled: true,
        accessToken: 'EAAB_test_token',
        testEventCode: null,
        graphVersion: 'v26.0',
        catalogEnabled: false,
        catalogFeedToken: null,
        catalogBrand: 'Test Store',
        currency: 'EGP',
    );

    $client = new MetaConversionsClient($config);
    $result = $client->verifyCredentials();

    expect($result->successful)->toBeTrue()
        ->and($result->httpStatus)->toBe(200)
        ->and($result->fbtraceId)->toBe('verify_trace_123')
        ->and($result->raw['name'])->toBe('My Pixel');
});

test('missing credentials returns failure without making HTTP call', function () {
    Http::fake();

    $config = new MetaMarketingConfig(
        pixelId: null,
        debugMode: false,
        capiEnabled: true,
        accessToken: null,
        testEventCode: null,
        graphVersion: 'v26.0',
        catalogEnabled: false,
        catalogFeedToken: null,
        catalogBrand: 'Test Store',
        currency: 'EGP',
    );

    $client = new MetaConversionsClient($config);
    $result = $client->sendEvents([['event_name' => 'Purchase']]);

    expect($result->successful)->toBeFalse()
        ->and($result->errorMessage)->toContain('missing');

    Http::assertNothingSent();
});
