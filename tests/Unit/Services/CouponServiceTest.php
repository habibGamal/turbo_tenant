<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Area;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Governorate;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CouponService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CouponServiceTest extends TestCase
{
    use RefreshDatabase;

    private CouponService $couponService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->couponService = new CouponService();
    }

    public function test_validates_inactive_coupon(): void
    {
        $coupon = Coupon::factory()->create(['is_active' => false]);
        $user = User::factory()->create();

        $result = $this->couponService->validateCoupon(
            $coupon,
            $user,
            [],
            100
        );

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('not active', $result['message']);
    }

    public function test_validates_expired_coupon(): void
    {
        $coupon = Coupon::factory()->create([
            'expiry_date' => Carbon::yesterday(),
            'is_active' => true,
        ]);
        $user = User::factory()->create();

        $result = $this->couponService->validateCoupon(
            $coupon,
            $user,
            [],
            100
        );

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('expired', $result['message']);
    }

    public function test_validates_max_usage_limit(): void
    {
        $coupon = Coupon::factory()->create([
            'is_active' => true,
            'max_usage' => 10,
            'usage_count' => 10,
            'expiry_date' => Carbon::tomorrow(),
        ]);
        $user = User::factory()->create();

        $result = $this->couponService->validateCoupon(
            $coupon,
            $user,
            [],
            100
        );

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('maximum usage', $result['message']);
    }

    public function test_validates_minimum_order_total(): void
    {
        $coupon = Coupon::factory()->create([
            'is_active' => true,
            'expiry_date' => Carbon::tomorrow(),
            'conditions' => [
                'min_order_total' => 200,
            ],
        ]);
        $user = User::factory()->create();

        $result = $this->couponService->validateCoupon(
            $coupon,
            $user,
            [],
            100
        );

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Minimum order total', $result['message']);
    }

    public function test_validates_maximum_order_total(): void
    {
        $coupon = Coupon::factory()->create([
            'is_active' => true,
            'expiry_date' => Carbon::tomorrow(),
            'conditions' => [
                'max_order_total' => 100,
            ],
        ]);
        $user = User::factory()->create();

        $result = $this->couponService->validateCoupon(
            $coupon,
            $user,
            [],
            200
        );

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('Maximum order total', $result['message']);
    }

    public function test_validates_specific_products(): void
    {
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();

        $coupon = Coupon::factory()->create([
            'is_active' => true,
            'expiry_date' => Carbon::tomorrow(),
            'conditions' => [
                'applicable_to' => [
                    'type' => 'products',
                    'product_ids' => [$product1->id],
                ],
            ],
        ]);
        $user = User::factory()->create();

        $cartItems = [
            ['product_id' => $product2->id, 'subtotal' => 100],
        ];

        $result = $this->couponService->validateCoupon(
            $coupon,
            $user,
            $cartItems,
            100
        );

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('not applicable', $result['message']);
    }

    public function test_validates_specific_categories(): void
    {
        $category1 = Category::factory()->create();
        $category2 = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category2->id]);

        $coupon = Coupon::factory()->create([
            'is_active' => true,
            'expiry_date' => Carbon::tomorrow(),
            'conditions' => [
                'applicable_to' => [
                    'type' => 'categories',
                    'category_ids' => [$category1->id],
                ],
            ],
        ]);
        $user = User::factory()->create();

        $cartItems = [
            [
                'product_id' => $product->id,
                'product' => ['category_id' => $category2->id],
                'subtotal' => 100,
            ],
        ];

        $result = $this->couponService->validateCoupon(
            $coupon,
            $user,
            $cartItems,
            100
        );

        $this->assertFalse($result['valid']);
    }

    public function test_validates_governorate_restrictions(): void
    {
        $gov1 = Governorate::factory()->create();
        $gov2 = Governorate::factory()->create();

        $coupon = Coupon::factory()->create([
            'is_active' => true,
            'expiry_date' => Carbon::tomorrow(),
            'conditions' => [
                'shipping' => [
                    'applicable_governorates' => [$gov1->id],
                ],
            ],
        ]);
        $user = User::factory()->create();

        $result = $this->couponService->validateCoupon(
            $coupon,
            $user,
            [],
            100,
            null,
            null,
            $gov2->id
        );

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('delivery location', $result['message']);
    }

    public function test_validates_area_restrictions(): void
    {
        $area1 = Area::factory()->create();
        $area2 = Area::factory()->create();

        $coupon = Coupon::factory()->create([
            'is_active' => true,
            'expiry_date' => Carbon::tomorrow(),
            'conditions' => [
                'shipping' => [
                    'applicable_areas' => [$area1->id],
                ],
            ],
        ]);
        $user = User::factory()->create();

        $result = $this->couponService->validateCoupon(
            $coupon,
            $user,
            [],
            100,
            null,
            $area2->id
        );

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('delivery location', $result['message']);
    }

    public function test_validates_first_order_only(): void
    {
        $user = User::factory()->create();
        Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'confirmed',
        ]);

        $coupon = Coupon::factory()->create([
            'is_active' => true,
            'expiry_date' => Carbon::tomorrow(),
            'conditions' => [
                'usage_restrictions' => [
                    'first_order_only' => true,
                ],
            ],
        ]);

        $result = $this->couponService->validateCoupon(
            $coupon,
            $user,
            [],
            100
        );

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('not available for your account', $result['message']);
    }

    public function test_validates_user_specific_restrictions(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $coupon = Coupon::factory()->create([
            'is_active' => true,
            'expiry_date' => Carbon::tomorrow(),
            'conditions' => [
                'usage_restrictions' => [
                    'user_specific' => true,
                    'user_ids' => [$user1->id],
                ],
            ],
        ]);

        $result = $this->couponService->validateCoupon(
            $coupon,
            $user2,
            [],
            100
        );

        $this->assertFalse($result['valid']);
    }

    public function test_validates_valid_days(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 13, 12, 0, 0)); // Monday

        $coupon = Coupon::factory()->create([
            'is_active' => true,
            'expiry_date' => Carbon::tomorrow(),
            'conditions' => [
                'valid_days' => [0, 6], // Sunday and Saturday only
            ],
        ]);
        $user = User::factory()->create();

        $result = $this->couponService->validateCoupon(
            $coupon,
            $user,
            [],
            100
        );

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('not valid at this time', $result['message']);
    }

    public function test_validates_valid_hours(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 1, 13, 8, 0, 0)); // 8 AM

        $coupon = Coupon::factory()->create([
            'is_active' => true,
            'expiry_date' => Carbon::tomorrow(),
            'conditions' => [
                'valid_hours' => [
                    'start' => '09:00',
                    'end' => '17:00',
                ],
            ],
        ]);
        $user = User::factory()->create();

        $result = $this->couponService->validateCoupon(
            $coupon,
            $user,
            [],
            100
        );

        $this->assertFalse($result['valid']);
    }

    public function test_calculates_percentage_discount(): void
    {
        $coupon = Coupon::factory()->create([
            'type' => 'percentage',
            'value' => 10,
        ]);

        $discount = $this->couponService->calculateDiscount($coupon, [], 100);

        $this->assertEquals(10, $discount);
    }

    public function test_calculates_fixed_discount(): void
    {
        $coupon = Coupon::factory()->create([
            'type' => 'fixed',
            'value' => 20,
        ]);

        $discount = $this->couponService->calculateDiscount($coupon, [], 100);

        $this->assertEquals(20, $discount);
    }

    public function test_calculates_fixed_discount_not_exceeding_subtotal(): void
    {
        $coupon = Coupon::factory()->create([
            'type' => 'fixed',
            'value' => 150,
        ]);

        $discount = $this->couponService->calculateDiscount($coupon, [], 100);

        $this->assertEquals(100, $discount);
    }

    public function test_calculates_discount_for_specific_products(): void
    {
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();

        $coupon = Coupon::factory()->create([
            'type' => 'percentage',
            'value' => 10,
            'conditions' => [
                'applicable_to' => [
                    'type' => 'products',
                    'product_ids' => [$product1->id],
                ],
            ],
        ]);

        $cartItems = [
            ['product_id' => $product1->id, 'subtotal' => 100],
            ['product_id' => $product2->id, 'subtotal' => 50],
        ];

        $discount = $this->couponService->calculateDiscount($coupon, $cartItems, 150);

        $this->assertEquals(10, $discount); // 10% of 100
    }

    public function test_calculates_free_shipping(): void
    {
        $coupon = Coupon::factory()->create([
            'conditions' => [
                'shipping' => [
                    'free_shipping' => true,
                ],
            ],
        ]);

        $shippingFee = $this->couponService->calculateShippingFee($coupon, 50, 200, 20);

        $this->assertEquals(0, $shippingFee);
    }

    public function test_calculates_free_shipping_with_threshold(): void
    {
        $coupon = Coupon::factory()->create([
            'conditions' => [
                'shipping' => [
                    'free_shipping' => true,
                    'free_shipping_threshold' => 200,
                ],
            ],
        ]);

        // Order total after discount is 180 (below threshold)
        $shippingFee = $this->couponService->calculateShippingFee($coupon, 50, 200, 20);
        $this->assertEquals(50, $shippingFee);

        // Order total after discount is 200 (meets threshold)
        $shippingFee = $this->couponService->calculateShippingFee($coupon, 50, 250, 50);
        $this->assertEquals(0, $shippingFee);
    }

    public function test_applies_coupon_increments_usage(): void
    {
        $coupon = Coupon::factory()->create([
            'usage_count' => 5,
            'total_consumed' => 50,
        ]);

        $this->couponService->applyCoupon($coupon, 10);

        $coupon->refresh();
        $this->assertEquals(6, $coupon->usage_count);
        $this->assertEquals(60, $coupon->total_consumed);
    }

    public function test_finds_coupon_by_code(): void
    {
        $coupon = Coupon::factory()->create([
            'code' => 'TEST123',
            'is_active' => true,
        ]);

        $found = $this->couponService->findByCode('test123');

        $this->assertNotNull($found);
        $this->assertEquals($coupon->id, $found->id);
    }

    public function test_valid_coupon_passes_all_checks(): void
    {
        $product = Product::factory()->create();
        $coupon = Coupon::factory()->create([
            'is_active' => true,
            'expiry_date' => Carbon::tomorrow(),
            'max_usage' => 100,
            'usage_count' => 50,
            'conditions' => [
                'min_order_total' => 50,
                'max_order_total' => 500,
                'applicable_to' => [
                    'type' => 'products',
                    'product_ids' => [$product->id],
                ],
            ],
        ]);
        $user = User::factory()->create();

        $cartItems = [
            ['product_id' => $product->id, 'subtotal' => 100],
        ];

        $result = $this->couponService->validateCoupon(
            $coupon,
            $user,
            $cartItems,
            100
        );

        $this->assertTrue($result['valid']);
    }
}
