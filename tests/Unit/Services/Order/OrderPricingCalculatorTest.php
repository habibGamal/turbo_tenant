<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Order;

use App\DTOs\Order\PlaceOrderData;
use App\Enums\PaymentMethod;
use App\Enums\SettingKey;
use App\Models\Address;
use App\Models\Area;
use App\Models\Coupon;
use App\Models\Governorate;
use App\Models\GuestUser;
use App\Models\User;
use App\Services\Order\Pricing\OrderPricingCalculator;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class OrderPricingCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private OrderPricingCalculator $calculator;

    private SettingService $settingService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = app(OrderPricingCalculator::class);
        $this->settingService = app(SettingService::class);
    }

    public function test_calculates_subtotal_tax_service_and_delivery_for_registered_user(): void
    {
        Config::set('app.tax_rate', 0.14);
        Config::set('app.service_rate', 0.05);
        $this->settingService->set(SettingKey::COD_FEE, 10.00);

        $governorate = Governorate::factory()->create();
        $area = Area::factory()->create([
            'governorate_id' => $governorate->id,
            'shipping_cost' => 30.00,
        ]);
        $user = User::factory()->create();
        $address = Address::factory()->create([
            'user_id' => $user->id,
            'area_id' => $area->id,
        ]);

        $data = new PlaceOrderData(
            user: $user,
            branchId: 1,
            paymentMethod: PaymentMethod::COD,
            addressId: $address->id,
            type: 'web_delivery'
        );

        $cartItems = [
            ['subtotal' => 100.0],
            ['subtotal' => 100.0],
        ];

        // Subtotal = 200.0
        // Discount = 0
        // Tax = 200 * 0.14 = 28.0
        // Service = (200 * 0.05) + 10 (COD) = 20.0
        // Delivery = 30.0
        // Total = 200 + 28 + 20 + 30 = 278.0

        $totals = $this->calculator->calculate($data, $cartItems, null);

        $this->assertEquals(200.00, $totals->subTotal);
        $this->assertEquals(0.00, $totals->discount);
        $this->assertEquals(28.00, $totals->tax);
        $this->assertEquals(20.00, $totals->service);
        $this->assertEquals(30.00, $totals->deliveryFee);
        $this->assertEquals(278.00, $totals->total);
    }

    public function test_delivery_fee_is_zero_for_takeaway(): void
    {
        $user = User::factory()->create();
        $data = new PlaceOrderData(
            user: $user,
            branchId: 1,
            paymentMethod: PaymentMethod::CARD,
            type: 'web_takeaway'
        );

        $cartItems = [
            ['subtotal' => 100.0],
        ];

        $totals = $this->calculator->calculate($data, $cartItems, null);

        $this->assertEquals(0.00, $totals->deliveryFee);
    }

    public function test_guest_user_delivery_fee_resolved_from_area_id(): void
    {
        $governorate = Governorate::factory()->create();
        $area = Area::factory()->create([
            'governorate_id' => $governorate->id,
            'shipping_cost' => 45.00,
        ]);

        $guest = GuestUser::factory()->create([
            'area_id' => $area->id,
        ]);

        $data = new PlaceOrderData(
            user: $guest,
            branchId: 1,
            paymentMethod: PaymentMethod::COD,
            type: 'web_delivery'
        );

        $cartItems = [
            ['subtotal' => 50.0],
        ];

        $totals = $this->calculator->calculate($data, $cartItems, null);

        $this->assertEquals(45.00, $totals->deliveryFee);
    }

    public function test_calculates_discount_with_coupon(): void
    {
        $user = User::factory()->create();
        $data = new PlaceOrderData(
            user: $user,
            branchId: 1,
            paymentMethod: PaymentMethod::CARD,
            type: 'web_takeaway'
        );

        $coupon = Coupon::factory()->create([
            'code' => 'DISC15',
            'type' => 'fixed',
            'value' => 15.00,
            'is_active' => true,
        ]);

        $cartItems = [
            ['subtotal' => 100.0],
        ];

        $totals = $this->calculator->calculate($data, $cartItems, $coupon);

        $this->assertEquals(15.00, $totals->discount);
        $this->assertEquals(100.00, $totals->subTotal);
    }
}
