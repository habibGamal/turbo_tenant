<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Core\Contracts\SettingsRepository;
use App\Enums\SettingKey;
use App\Models\User;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CouponsPromotions\src\Models\Coupon;
use App\Plugins\LocationsDelivery\src\Models\Area;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\LocationsDelivery\src\Models\Governorate;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Settings\OrderSettingKey;
use App\Plugins\PosIntegration\src\Services\POS\POSCustomerDataFormatter;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

final class StorefrontCheckoutAndShippingSettingsTest extends TestCase
{
    public function test_bootstrap_exposes_whatsapp_free_shipping_threshold_and_simplified_address_settings(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->set(SettingKey::WHATSAPP_NUMBER, '+201012345678');
        $settings->set(OrderSettingKey::MIN_ORDER_AMOUNT, 150);
        $settings->set(OrderSettingKey::FREE_SHIPPING_THRESHOLD, 700);
        $settings->set(OrderSettingKey::SIMPLIFIED_ADDRESS_ENABLED, true);

        $response = $this->getJson('/api/tenant/bootstrap');

        $response->assertOk()
            ->assertJsonPath('settings.whatsapp_number', '+201012345678')
            ->assertJsonPath('settings.min_order_amount', 150)
            ->assertJsonPath('settings.free_shipping_threshold', 700)
            ->assertJsonPath('settings.simplified_address_enabled', true);
    }

    public function test_free_shipping_threshold_waives_delivery_fee_when_discounted_subtotal_meets_threshold(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->set(OrderSettingKey::FREE_SHIPPING_THRESHOLD, 700);
        $settings->set(OrderSettingKey::SIMPLIFIED_ADDRESS_ENABLED, false);

        $branch = Branch::factory()->create(['is_active' => true]);
        $gov = Governorate::create([
            'name' => 'Cairo',
            'name_ar' => 'القاهره',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $area = Area::create([
            'name' => 'Cairo',
            'name_ar' => 'القاهره',
            'shipping_cost' => 95.0,
            'governorate_id' => $gov->id,
            'branch_id' => $branch->id,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Below threshold (500 < 700) -> delivery fee 95 is charged
        $cheapProduct = Product::factory()->create([
            'base_price' => 500.00,
            'price_after_discount' => null,
            'is_active' => true,
        ]);

        Auth::logout();
        $this->postJson(route('api.cart.store'), [
            'product_id' => $cheapProduct->id,
            'quantity' => '1',
        ])->assertSuccessful();

        $belowResponse = $this->postJson(route('api.orders.place'), [
            'branch_id' => $branch->id,
            'payment_method' => 'cod',
            'type' => 'web_delivery',
            'guest_data' => [
                'name' => 'Guest Below',
                'phone' => '01011112222',
                'area_id' => $area->id,
                'street' => 'Street 1',
            ],
        ])->assertSuccessful();

        $belowOrder = Order::findOrFail($belowResponse->json('data.order_id'));
        $this->assertEquals(95.00, (float) $belowOrder->delivery_fee);
        $this->assertEquals(595.00, (float) $belowOrder->total);

        // Subtotal 750 with 100 discount = 650 (< 700) -> delivery fee 95 still charged
        $expensiveProduct = Product::factory()->create([
            'base_price' => 750.00,
            'price_after_discount' => null,
            'is_active' => true,
        ]);
        $coupon = Coupon::factory()->create([
            'code' => 'DROP100_'.uniqid(),
            'type' => 'fixed',
            'value' => 100.0,
            'is_active' => true,
            'expiry_date' => now()->addMonth(),
            'conditions' => ['shipping' => ['free_shipping' => false]],
        ]);

        $this->postJson(route('api.cart.store'), [
            'product_id' => $expensiveProduct->id,
            'quantity' => '1',
        ])->assertSuccessful();

        $discountedBelowResponse = $this->postJson(route('api.orders.place'), [
            'branch_id' => $branch->id,
            'payment_method' => 'cod',
            'type' => 'web_delivery',
            'coupon_id' => $coupon->id,
            'guest_data' => [
                'name' => 'Guest Discounted',
                'phone' => '01011112223',
                'area_id' => $area->id,
                'street' => 'Street 2',
            ],
        ])->assertSuccessful();

        $discountedBelowOrder = Order::findOrFail($discountedBelowResponse->json('data.order_id'));
        $this->assertEquals(95.00, (float) $discountedBelowOrder->delivery_fee);
        $this->assertEquals(745.00, (float) $discountedBelowOrder->total);

        // Subtotal 750 without discount (>= 700) -> delivery fee waived (0.00)
        $this->postJson(route('api.cart.store'), [
            'product_id' => $expensiveProduct->id,
            'quantity' => '1',
        ])->assertSuccessful();

        $aboveResponse = $this->postJson(route('api.orders.place'), [
            'branch_id' => $branch->id,
            'payment_method' => 'cod',
            'type' => 'web_delivery',
            'guest_data' => [
                'name' => 'Guest Above',
                'phone' => '01011112224',
                'area_id' => $area->id,
                'street' => 'Street 3',
            ],
        ])->assertSuccessful();

        $aboveOrder = Order::findOrFail($aboveResponse->json('data.order_id'));
        $this->assertEquals(0.00, (float) $aboveOrder->delivery_fee);
        $this->assertEquals(750.00, (float) $aboveOrder->total);
    }

    public function test_address_endpoint_respects_simplified_address_setting(): void
    {
        $settings = app(SettingsRepository::class);
        $branch = Branch::factory()->create(['is_active' => true]);
        $gov = Governorate::create([
            'name' => 'Alexandria',
            'name_ar' => 'اسكندريه',
            'is_active' => true,
            'sort_order' => 2,
        ]);
        $area = Area::create([
            'name' => 'Alexandria',
            'name_ar' => 'اسكندريه',
            'shipping_cost' => 105.0,
            'governorate_id' => $gov->id,
            'branch_id' => $branch->id,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $user = User::factory()->create();

        // Traditional mode (false): building, floor, apartment are required
        $settings->set(OrderSettingKey::SIMPLIFIED_ADDRESS_ENABLED, false);
        $this->actingAs($user)->postJson('/api/locations-delivery/addresses', [
            'area_id' => $area->id,
            'phone_number' => '+201012345678',
            'street' => 'Smouha, Victor Emmanuel St, Bldg 10',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['building', 'floor', 'apartment']);

        // Simplified mode (true): street + area_id + phone_number suffice
        $settings->set(OrderSettingKey::SIMPLIFIED_ADDRESS_ENABLED, true);
        $this->actingAs($user)->postJson('/api/locations-delivery/addresses', [
            'area_id' => $area->id,
            'phone_number' => '+201012345678',
            'street' => 'Smouha, Victor Emmanuel St, Bldg 10',
        ])->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.street', 'Smouha, Victor Emmanuel St, Bldg 10');
    }

    public function test_guest_checkout_requires_street_and_allows_omitting_building_fields_when_simplified_address_enabled(): void
    {
        $settings = app(SettingsRepository::class);
        $settings->set(OrderSettingKey::SIMPLIFIED_ADDRESS_ENABLED, true);

        $branch = Branch::factory()->create(['is_active' => true]);
        $gov = Governorate::create([
            'name' => 'Giza',
            'name_ar' => 'الجيزه',
            'is_active' => true,
            'sort_order' => 3,
        ]);
        $area = Area::create([
            'name' => 'Giza',
            'name_ar' => 'الجيزه',
            'shipping_cost' => 95.0,
            'governorate_id' => $gov->id,
            'branch_id' => $branch->id,
            'is_active' => true,
            'sort_order' => 3,
        ]);
        $product = Product::factory()->create([
            'base_price' => 240.00,
            'price_after_discount' => null,
            'is_active' => true,
        ]);

        Auth::logout();
        $this->postJson(route('api.cart.store'), [
            'product_id' => $product->id,
            'quantity' => '1',
        ])->assertSuccessful();

        // Missing street is rejected when simplified address mode is enabled
        $this->postJson(route('api.orders.place'), [
            'branch_id' => $branch->id,
            'payment_method' => 'cod',
            'type' => 'web_delivery',
            'guest_data' => [
                'name' => 'Guest Simplified',
                'phone' => '01099887766',
                'area_id' => $area->id,
            ],
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['guest_data.street']);

        // Providing street without building/floor/apartment succeeds
        $okResponse = $this->postJson(route('api.orders.place'), [
            'branch_id' => $branch->id,
            'payment_method' => 'cod',
            'type' => 'web_delivery',
            'guest_data' => [
                'name' => 'Guest Simplified',
                'phone' => '01099887766',
                'area_id' => $area->id,
                'street' => 'Dokki, 15 Tahrir St, next to Metro',
            ],
        ])->assertSuccessful();

        $order = Order::findOrFail($okResponse->json('data.order_id'));
        $this->assertSame('Dokki, 15 Tahrir St, next to Metro', $order->delivery_address['street'] ?? null);

        // POS formatter deduplicates identical area_name and governorate_name
        $formatted = app(POSCustomerDataFormatter::class)->format($order);
        $this->assertSame('Dokki, 15 Tahrir St, next to Metro، الجيزه', $formatted['address']);
    }
}
