<?php

declare(strict_types=1);

use App\Core\Contracts\SettingsRepository;
use App\Models\User;
use App\Plugins\MetaMarketing\src\DTOs\MetaMarketingConfig;
use App\Plugins\MetaMarketing\src\Models\MetaCapiEvent;
use App\Plugins\MetaMarketing\src\Settings\MetaSettingKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $settings = app(SettingsRepository::class);
    $settings->set(MetaSettingKey::PIXEL_ID, '123456789012345');
    $settings->set(MetaSettingKey::CAPI_ENABLED, true);
    $settings->set(MetaSettingKey::CAPI_ACCESS_TOKEN, Crypt::encryptString('secret-capi-token'));
    app()->forgetInstance(MetaMarketingConfig::class);
});

test('valid relay event returns 204 and triggers one HTTP call to Meta with the same event_id', function () {
    Http::fake([
        'https://graph.facebook.com/*' => Http::response([
            'events_received' => 1,
            'fbtrace_id' => 'relay_trace_123',
        ], 200),
    ]);

    $response = $this->withCredentials()->withUnencryptedCookies([
        '_fbp' => 'fb.1.1680000000.88888',
        '_fbc' => 'fb.1.1680000000.99999',
    ])->postJson('/api/meta/events', [
        'event_name' => 'AddToCart',
        'event_id' => 'evt_cart_12345',
        'event_source_url' => 'https://tenant.test/products/10',
        'custom_data' => [
            'value' => 150.5,
            'currency' => 'EGP',
            'content_type' => 'product',
            'content_ids' => ['10', '10-2'],
            'num_items' => 1,
            'ignored_unknown_field' => 'should_be_stripped',
        ],
    ]);

    $response->assertNoContent();

    Http::assertSent(function (Illuminate\Http\Client\Request $request) {
        if (! str_contains($request->url(), '123456789012345/events')) {
            return false;
        }

        $events = $request['data'];
        if (count($events) !== 1) {
            return false;
        }

        $event = $events[0];

        return $event['event_name'] === 'AddToCart'
            && $event['event_id'] === 'evt_cart_12345'
            && $event['action_source'] === 'website'
            && $event['event_source_url'] === 'https://tenant.test/products/10'
            && $event['custom_data']['value'] === 150.5
            && $event['custom_data']['content_ids'] === ['10', '10-2']
            && ! array_key_exists('ignored_unknown_field', $event['custom_data'])
            && $event['user_data']['fbp'] === 'fb.1.1680000000.88888'
            && $event['user_data']['fbc'] === 'fb.1.1680000000.99999';
    });

    // Successful relay events do not write to meta_capi_events delivery log (keeps volume low)
    expect(MetaCapiEvent::count())->toBe(0);
});

test('relay event with authenticated user includes hashed customer credentials', function () {
    Http::fake([
        'https://graph.facebook.com/*' => Http::response(['events_received' => 1], 200),
    ]);

    $user = User::factory()->create([
        'name' => 'Mona Zaki',
        'email' => 'mona@example.com',
    ]);

    $response = $this->actingAs($user)->postJson('/api/meta/events', [
        'event_name' => 'ViewContent',
        'event_id' => 'evt_view_999',
        'event_source_url' => 'https://tenant.test/products/5',
    ]);

    $response->assertNoContent();

    Http::assertSent(function (Illuminate\Http\Client\Request $request) use ($user) {
        $userData = $request['data'][0]['user_data'];

        return $userData['em'] === [hash('sha256', 'mona@example.com')]
            && $userData['fn'] === [hash('sha256', 'mona')]
            && $userData['ln'] === [hash('sha256', 'zaki')]
            && $userData['external_id'] === [hash('sha256', 'u:'.$user->id)];
    });
});

test('Purchase event is rejected with 422 on relay endpoint (server-authoritative only)', function () {
    Http::fake();

    $response = $this->postJson('/api/meta/events', [
        'event_name' => 'Purchase',
        'event_id' => 'evt_fake_purchase',
        'event_source_url' => 'https://tenant.test/checkout',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['event_name']);

    Http::assertNothingSent();
});

test('malformed content_ids are rejected with 422', function () {
    Http::fake();

    $response = $this->postJson('/api/meta/events', [
        'event_name' => 'AddToCart',
        'event_id' => 'evt_bad_ids',
        'event_source_url' => 'https://tenant.test/cart',
        'custom_data' => [
            'content_ids' => ['bad-id', '12-abc', '<script>'],
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['custom_data.content_ids.0']);

    Http::assertNothingSent();
});

test('disabled CAPI responds with 204 and makes no HTTP calls', function () {
    Http::fake();

    $settings = app(SettingsRepository::class);
    $settings->set(MetaSettingKey::CAPI_ENABLED, false);
    app()->forgetInstance(MetaMarketingConfig::class);

    $response = $this->postJson('/api/meta/events', [
        'event_name' => 'PageView',
        'event_id' => 'evt_pageview_disabled',
        'event_source_url' => 'https://tenant.test/menu',
    ]);

    $response->assertNoContent();
    Http::assertNothingSent();
});

test('relay failure creates a meta_capi_events row with status failed and payload without access token', function () {
    Http::fake([
        'https://graph.facebook.com/*' => Http::response([
            'error' => [
                'message' => 'Malformed parameter',
                'type' => 'OAuthException',
                'code' => 100,
                'fbtrace_id' => 'relay_error_trace',
            ],
        ], 400),
    ]);

    $response = $this->postJson('/api/meta/events', [
        'event_name' => 'Search',
        'event_id' => 'evt_search_fail',
        'event_source_url' => 'https://tenant.test/menu?q=pizza',
        'custom_data' => [
            'search_string' => 'pizza',
        ],
    ]);

    $response->assertNoContent();

    $log = MetaCapiEvent::where('event_id', 'evt_search_fail')->first();
    expect($log)->not->toBeNull()
        ->and($log->status)->toBe('failed')
        ->and($log->http_status)->toBe(400)
        ->and($log->error)->toContain('Malformed parameter')
        ->and(json_encode($log->payload))->not->toContain('secret-capi-token');
});
