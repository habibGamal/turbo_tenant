<?php

declare(strict_types=1);

use App\Core\Contracts\SettingsRepository;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\LocationsDelivery\src\Models\Area;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\MetaMarketing\src\DTOs\MetaMarketingConfig;
use App\Plugins\MetaMarketing\src\Models\MetaOrderTracking;
use App\Plugins\MetaMarketing\src\Settings\MetaSettingKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Auth::logout();

    $settings = app(SettingsRepository::class);
    $settings->set(MetaSettingKey::PIXEL_ID, '123456789012345');
    $settings->set(MetaSettingKey::CAPI_ENABLED, true);
    $settings->set(MetaSettingKey::CAPI_ACCESS_TOKEN, Crypt::encryptString('secret-capi-token'));
    app()->forgetInstance(MetaMarketingConfig::class);

    $this->area = Area::factory()->create(['is_active' => true]);
    $this->branch = Branch::factory()->create();
});

test('listener receives raw unencrypted _fbp and _fbc cookies through encryptCookies except list', function () {
    Http::fake();

    $product = Product::factory()->create(['base_price' => 50.00, 'is_active' => true]);

    $this->postJson(route('api.cart.store'), [
        'product_id' => $product->id,
        'quantity' => 1,
    ]);

    $rawFbp = 'fb.1.1700000000.999888777';
    $rawFbc = 'fb.1.1700000000.111222333';

    // Send unencrypted raw cookies as set by Meta browser JS
    $response = $this->withCredentials()->withUnencryptedCookies([
        '_fbp' => $rawFbp,
        '_fbc' => $rawFbc,
    ])->postJson(route('api.orders.place'), [
        'branch_id' => $this->branch->id,
        'payment_method' => 'cod',
        'type' => 'web_delivery',
        'guest_data' => [
            'name' => 'Raw Cookie Test',
            'phone' => '01012345678',
            'phone_country_code' => '+20',
            'email' => 'rawcookie@example.com',
            'street' => 'Street 1',
            'building' => '1',
            'city' => 'Cairo',
            'area_id' => $this->area->id,
        ],
    ]);

    $response->assertSuccessful();

    $orderId = $response->json('data.order_id');
    $tracking = MetaOrderTracking::where('order_id', $orderId)->first();

    expect($tracking)->not->toBeNull()
        ->and($tracking->fbp)->toBe($rawFbp)
        ->and($tracking->fbc)->toBe($rawFbc);
});

test('when _fbc is missing but referer has fbclid, fbc is constructed as fb.1.{timestamp}.{fbclid}', function () {
    Http::fake();

    $product = Product::factory()->create(['base_price' => 50.00, 'is_active' => true]);

    $this->postJson(route('api.cart.store'), [
        'product_id' => $product->id,
        'quantity' => 1,
    ]);

    $rawFbp = 'fb.1.1700000000.444555666';

    $response = $this->withCredentials()->withUnencryptedCookies([
        '_fbp' => $rawFbp,
    ])->withHeaders([
        'Referer' => 'https://tenant.example/landing?fbclid=IwAR1234567890abcdef',
    ])->postJson(route('api.orders.place'), [
        'branch_id' => $this->branch->id,
        'payment_method' => 'cod',
        'type' => 'web_delivery',
        'guest_data' => [
            'name' => 'Fbclid Fallback Test',
            'phone' => '01012345678',
            'phone_country_code' => '+20',
            'email' => 'fbclid@example.com',
            'street' => 'Street 1',
            'building' => '1',
            'city' => 'Cairo',
            'area_id' => $this->area->id,
        ],
    ]);

    $response->assertSuccessful();

    $orderId = $response->json('data.order_id');
    $tracking = MetaOrderTracking::where('order_id', $orderId)->first();

    expect($tracking)->not->toBeNull()
        ->and($tracking->fbp)->toBe($rawFbp)
        ->and($tracking->fbc)->toMatch('/^fb\.1\.\d+\.IwAR1234567890abcdef$/');
});
