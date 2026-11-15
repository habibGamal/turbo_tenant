<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Order;

use App\Models\User;
use App\Plugins\CartEngine\src\DTOs\CartLine;
use App\Plugins\CouponsPromotions\src\Models\Coupon;
use App\Plugins\GuestCheckout\src\Models\GuestUser;
use App\Plugins\LocationsDelivery\src\Models\Address;
use App\Plugins\LocationsDelivery\src\Models\Area;
use App\Plugins\LocationsDelivery\src\Models\Governorate;
use App\Plugins\OrderManagement\src\DTOs\PlaceOrderData;
use App\Plugins\OrderManagement\src\Enums\PaymentMethod;
use App\Plugins\OrderManagement\src\Services\Pricing\OrderPricingCalculator;
use App\Plugins\OrderManagement\src\Settings\OrderSettingKey;
use App\Plugins\PaymentProcessing\src\Settings\PaymentSettingKey;
use App\Plugins\StoreSettings\src\Services\SettingService;
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
        $this->settingService->set(OrderSettingKey::TAX_RATE, 14.0);
        Config::set('app.service_rate', 0.05);
        $this->settingService->set(PaymentSettingKey::COD_FEE, 10.00);

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

        $lines = [
            $this->makeLine(100.0),
            $this->makeLine(100.0),
        ];

        // Subtotal = 200.0
        // Discount = 0
        // Tax = 200 * 0.14 = 28.0
        // Service = (200 * 0.05) + 10 (COD) = 20.0
        // Delivery = 30.0
        // Total = 200 + 28 + 20 + 30 = 278.0

        $totals = $this->calculator->calculate($data, $lines);

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

        $lines = [
            $this->makeLine(100.0),
        ];

        $totals = $this->calculator->calculate($data, $lines);

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

        $lines = [
            $this->makeLine(50.0),
        ];

        $totals = $this->calculator->calculate($data, $lines);

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

        $lines = [
            $this->makeLine(100.0),
        ];

        $totals = $this->calculator->calculate($data, $lines, ['applied_coupon' => $coupon]);

        $this->assertEquals(15.00, $totals->discount);
        $this->assertEquals(100.00, $totals->subTotal);
    }

    private function makeLine(float $subtotal): CartLine
    {
        return new CartLine(
            itemId: 1,
            productId: 1,
            categoryId: null,
            variantId: null,
            weightOptionValueId: null,
            weightMultiplier: 1,
            quantity: 1.0,
            unitPrice: $subtotal,
            extrasUnitTotal: 0.0,
            subtotal: $subtotal,
            productName: 'Sample Product',
            productNameAr: null,
            variantName: null,
            variantNameAr: null,
            weightLabel: null,
        );
    }
}
