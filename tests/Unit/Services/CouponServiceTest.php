<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\User;
use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CouponsPromotions\src\DTOs\CouponContext;
use App\Plugins\CouponsPromotions\src\Enums\ApplicableTo;
use App\Plugins\CouponsPromotions\src\Enums\DiscountType;
use App\Plugins\CouponsPromotions\src\Models\Coupon;
use App\Plugins\CouponsPromotions\src\Rules\ActiveCouponRule;
use App\Plugins\CouponsPromotions\src\Rules\ApplicableItemsCouponRule;
use App\Plugins\CouponsPromotions\src\Rules\ExpiryCouponRule;
use App\Plugins\CouponsPromotions\src\Rules\MaxOrderTotalCouponRule;
use App\Plugins\CouponsPromotions\src\Rules\MaxUsageCouponRule;
use App\Plugins\CouponsPromotions\src\Rules\MinOrderTotalCouponRule;
use App\Plugins\CouponsPromotions\src\Rules\ShippingLocationCouponRule;
use App\Plugins\CouponsPromotions\src\Rules\TimeRestrictionsCouponRule;
use App\Plugins\CouponsPromotions\src\Rules\UserRestrictionsCouponRule;
use App\Plugins\CouponsPromotions\src\Services\CouponService;
use App\Plugins\LocationsDelivery\src\Models\Area;
use App\Plugins\LocationsDelivery\src\Models\Governorate;
use App\Plugins\OrderManagement\src\Models\Order;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\CartLines;
use Tests\TestCase;

final class CouponServiceTest extends TestCase
{
    use RefreshDatabase;

    private CouponService $couponService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->couponService = app(CouponService::class);
    }

    public function test_validates_inactive_coupon(): void
    {
        $coupon = Coupon::factory()->create(['is_active' => false]);
        $user = User::factory()->create();

        $result = $this->couponService->validateCoupon(
            $coupon,
            new CouponContext(customer: $user, subtotal: 100)
        );

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('not active', $result->message());
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
            new CouponContext(customer: $user, subtotal: 100)
        );

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('expired', $result->message());
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
            new CouponContext(customer: $user, subtotal: 100)
        );

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('maximum usage', $result->message());
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
            new CouponContext(customer: $user, subtotal: 100)
        );

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('Minimum order total', $result->message());
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
            new CouponContext(customer: $user, subtotal: 200)
        );

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('Maximum order total', $result->message());
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
            CartLines::make($product2->id, 100),
        ];

        $result = $this->couponService->validateCoupon(
            $coupon,
            new CouponContext(customer: $user, lines: $cartItems, subtotal: 100)
        );

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('not applicable', $result->message());
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
            CartLines::make($product->id, 100, categoryId: $category2->id),
        ];

        $result = $this->couponService->validateCoupon(
            $coupon,
            new CouponContext(customer: $user, lines: $cartItems, subtotal: 100)
        );

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('not applicable', $result->message());
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
            new CouponContext(customer: $user, subtotal: 100, governorateId: $gov2->id)
        );

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('delivery location', $result->message());
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
            new CouponContext(customer: $user, subtotal: 100, areaId: $area2->id)
        );

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('delivery location', $result->message());
    }

    public function test_validates_first_order_only(): void
    {
        $user = User::factory()->create();
        Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'processing',
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
            new CouponContext(customer: $user, subtotal: 100)
        );

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('not available for your account', $result->message());
    }

    public function test_first_order_only_rejects_registered_user_with_pending_order(): void
    {
        $user = User::factory()->create();
        Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'pending',
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
            new CouponContext(customer: $user, subtotal: 100)
        );

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('not available for your account', $result->message());
    }

    public function test_first_order_only_rejects_guest_phone_with_pending_order(): void
    {
        $phone = '+201099998888';
        Order::factory()->create([
            'user_id' => null,
            'customer_phone' => $phone,
            'status' => 'pending',
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
            new CouponContext(customer: null, subtotal: 100, phone: $phone)
        );

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('not available for your account', $result->message());
    }

    public function test_first_order_only_allows_user_and_guest_with_only_cancelled_order(): void
    {
        $user = User::factory()->create();
        Order::factory()->create([
            'user_id' => $user->id,
            'status' => 'cancelled',
        ]);

        $guestPhone = '+201011112222';
        Order::factory()->create([
            'user_id' => null,
            'customer_phone' => $guestPhone,
            'status' => 'cancelled',
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

        $userResult = $this->couponService->validateCoupon(
            $coupon,
            new CouponContext(customer: $user, subtotal: 100)
        );
        $this->assertTrue($userResult->isValid());

        $guestResult = $this->couponService->validateCoupon(
            $coupon,
            new CouponContext(customer: null, subtotal: 100, phone: $guestPhone)
        );
        $this->assertTrue($guestResult->isValid());
    }

    public function test_max_per_user_counts_pending_order_and_ignores_cancelled_order(): void
    {
        $user = User::factory()->create();
        $coupon = Coupon::factory()->create([
            'is_active' => true,
            'expiry_date' => Carbon::tomorrow(),
            'conditions' => [
                'usage_restrictions' => [
                    'max_per_user' => 1,
                ],
            ],
        ]);

        Order::factory()->create([
            'user_id' => $user->id,
            'coupon_id' => $coupon->id,
            'status' => 'cancelled',
        ]);

        // Cancelled order should not count towards limit
        $result1 = $this->couponService->validateCoupon($coupon, new CouponContext(customer: $user, subtotal: 100));
        $this->assertTrue($result1->isValid());

        // Now create a pending order using this coupon
        Order::factory()->create([
            'user_id' => $user->id,
            'coupon_id' => $coupon->id,
            'status' => 'pending',
        ]);

        // Pending order should count, exceeding/hitting limit of 1
        $result2 = $this->couponService->validateCoupon($coupon, new CouponContext(customer: $user, subtotal: 100));
        $this->assertFalse($result2->isValid());
        $this->assertStringContainsString('not available for your account', $result2->message());
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
            new CouponContext(customer: $user2, subtotal: 100)
        );

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('not available for your account', $result->message());
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
            new CouponContext(customer: $user, subtotal: 100)
        );

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('not valid at this time', $result->message());
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
            new CouponContext(customer: $user, subtotal: 100)
        );

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('not valid at this time', $result->message());
    }

    public function test_validates_overnight_valid_hours(): void
    {
        $coupon = Coupon::factory()->create([
            'is_active' => true,
            'expiry_date' => Carbon::tomorrow(),
            'conditions' => [
                'valid_hours' => [
                    'start' => '22:00',
                    'end' => '02:00',
                ],
            ],
        ]);
        $user = User::factory()->create();

        // At 23:30 (before midnight)
        Carbon::setTestNow(Carbon::create(2025, 1, 13, 23, 30, 0));
        $result1 = $this->couponService->validateCoupon(
            $coupon,
            new CouponContext(customer: $user, subtotal: 100)
        );
        $this->assertTrue($result1->isValid(), 'Expected overnight coupon to be valid at 23:30');

        // At 01:00 (after midnight)
        Carbon::setTestNow(Carbon::create(2025, 1, 14, 1, 0, 0));
        $result2 = $this->couponService->validateCoupon(
            $coupon,
            new CouponContext(customer: $user, subtotal: 100)
        );
        $this->assertTrue($result2->isValid(), 'Expected overnight coupon to be valid at 01:00');

        // Outside window, e.g. at 12:00
        Carbon::setTestNow(Carbon::create(2025, 1, 14, 12, 0, 0));
        $result3 = $this->couponService->validateCoupon(
            $coupon,
            new CouponContext(customer: $user, subtotal: 100)
        );
        $this->assertFalse($result3->isValid(), 'Expected overnight coupon to be invalid at 12:00');

        Carbon::setTestNow(); // clear test now
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
            CartLines::make($product1->id, 100),
            CartLines::make($product2->id, 50, itemId: 2),
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
            CartLines::make($product->id, 100),
        ];

        $result = $this->couponService->validateCoupon(
            $coupon,
            new CouponContext(customer: $user, lines: $cartItems, subtotal: 100)
        );

        $this->assertTrue($result->isValid());
        $this->assertSame('Coupon is valid', $result->message());
    }

    // =========================================================================
    // Targeted Unit Tests per Rule Class (pass + fail)
    // =========================================================================

    public function test_active_coupon_rule(): void
    {
        $rule = new ActiveCouponRule;
        $context = new CouponContext;

        $active = Coupon::factory()->make(['is_active' => true]);
        $inactive = Coupon::factory()->make(['is_active' => false]);

        $this->assertNull($rule->check($active, $context));
        $this->assertSame('This coupon is not active', $rule->check($inactive, $context));
    }

    public function test_expiry_coupon_rule(): void
    {
        $rule = new ExpiryCouponRule;
        $context = new CouponContext;

        $validFuture = Coupon::factory()->make(['expiry_date' => Carbon::tomorrow()]);
        $validNoExpiry = Coupon::factory()->make(['expiry_date' => null]);
        $expired = Coupon::factory()->make(['expiry_date' => Carbon::yesterday()]);

        $this->assertNull($rule->check($validFuture, $context));
        $this->assertNull($rule->check($validNoExpiry, $context));
        $this->assertSame('This coupon has expired', $rule->check($expired, $context));
    }

    public function test_max_usage_coupon_rule(): void
    {
        $rule = new MaxUsageCouponRule;
        $context = new CouponContext;

        $underLimit = Coupon::factory()->make(['max_usage' => 10, 'usage_count' => 9]);
        $noLimit = Coupon::factory()->make(['max_usage' => null, 'usage_count' => 100]);
        $atLimit = Coupon::factory()->make(['max_usage' => 10, 'usage_count' => 10]);

        $this->assertNull($rule->check($underLimit, $context));
        $this->assertNull($rule->check($noLimit, $context));
        $this->assertSame('This coupon has reached its maximum usage limit', $rule->check($atLimit, $context));
    }

    public function test_min_order_total_coupon_rule(): void
    {
        $rule = new MinOrderTotalCouponRule;
        $coupon = Coupon::factory()->make(['conditions' => ['min_order_total' => 150.0]]);

        $passContext = new CouponContext(subtotal: 150.0);
        $failContext = new CouponContext(subtotal: 149.99);

        $this->assertNull($rule->check($coupon, $passContext));
        $this->assertSame('Minimum order total of 150 required', $rule->check($coupon, $failContext));
    }

    public function test_max_order_total_coupon_rule(): void
    {
        $rule = new MaxOrderTotalCouponRule;
        $coupon = Coupon::factory()->make(['conditions' => ['max_order_total' => 300.0]]);

        $passContext = new CouponContext(subtotal: 300.0);
        $failContext = new CouponContext(subtotal: 300.01);

        $this->assertNull($rule->check($coupon, $passContext));
        $this->assertSame('Maximum order total of 300 exceeded', $rule->check($coupon, $failContext));
    }

    public function test_applicable_items_coupon_rule(): void
    {
        $rule = new ApplicableItemsCouponRule;
        $coupon = Coupon::factory()->make([
            'conditions' => [
                'applicable_to' => [
                    'type' => 'products',
                    'product_ids' => [10, 20],
                ],
            ],
        ]);

        $matchingContext = new CouponContext(lines: [CartLines::make(10, 50)]);
        $nonMatchingContext = new CouponContext(lines: [CartLines::make(99, 50)]);

        $this->assertNull($rule->check($coupon, $matchingContext));
        $this->assertSame('This coupon is not applicable to items in your cart', $rule->check($coupon, $nonMatchingContext));
    }

    public function test_shipping_location_coupon_rule(): void
    {
        $rule = new ShippingLocationCouponRule;
        $coupon = Coupon::factory()->make([
            'conditions' => [
                'shipping' => [
                    'applicable_governorates' => [1],
                    'applicable_areas' => [5],
                ],
            ],
        ]);

        $passContext = new CouponContext(areaId: 5, governorateId: 1);
        $failGovContext = new CouponContext(areaId: 5, governorateId: 2);
        $failAreaContext = new CouponContext(areaId: 6, governorateId: 1);

        $this->assertNull($rule->check($coupon, $passContext));
        $this->assertSame('This coupon is not available for your delivery location', $rule->check($coupon, $failGovContext));
        $this->assertSame('This coupon is not available for your delivery location', $rule->check($coupon, $failAreaContext));
    }

    public function test_user_restrictions_coupon_rule(): void
    {
        $orderHistory = app(\App\Plugins\OrderManagement\src\Contracts\CustomerOrderHistory::class);
        $rule = new UserRestrictionsCouponRule($orderHistory);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $coupon = Coupon::factory()->create([
            'conditions' => [
                'usage_restrictions' => [
                    'user_specific' => true,
                    'user_ids' => [$user1->id],
                ],
            ],
        ]);

        $this->assertNull($rule->check($coupon, new CouponContext(customer: $user1)));
        $this->assertSame('This coupon is not available for your account', $rule->check($coupon, new CouponContext(customer: $user2)));
    }

    public function test_time_restrictions_coupon_rule(): void
    {
        $rule = new TimeRestrictionsCouponRule;
        $coupon = Coupon::factory()->make([
            'conditions' => [
                'valid_days' => [1], // Monday
                'valid_hours' => [
                    'start' => '10:00',
                    'end' => '14:00',
                ],
            ],
        ]);

        Carbon::setTestNow(Carbon::create(2025, 1, 13, 11, 0, 0)); // Monday 11am
        $this->assertNull($rule->check($coupon, new CouponContext));

        Carbon::setTestNow(Carbon::create(2025, 1, 14, 11, 0, 0)); // Tuesday 11am
        $this->assertSame('This coupon is not valid at this time', $rule->check($coupon, new CouponContext));

        Carbon::setTestNow();
    }

    // =========================================================================
    // DiscountType and ApplicableTo Enum Tests
    // =========================================================================

    public function test_discount_type_computes_percentage_and_fixed_discount(): void
    {
        $this->assertSame(20.0, DiscountType::PERCENTAGE->calculateDiscount(100.0, 20.0));
        $this->assertSame(12.5, DiscountType::PERCENTAGE->calculateDiscount(50.0, 25.0));

        $this->assertSame(30.0, DiscountType::FIXED->calculateDiscount(100.0, 30.0));
        $this->assertSame(50.0, DiscountType::FIXED->calculateDiscount(50.0, 80.0)); // Capped at applicable amount
    }

    public function test_applicable_to_enum_matches_applies_and_sums_subtotal(): void
    {
        $line1 = CartLines::make(10, 40.0, categoryId: 1);
        $line2 = CartLines::make(20, 60.0, categoryId: 2);
        $lines = [$line1, $line2];

        // ALL
        $all = ApplicableTo::ALL;
        $this->assertTrue($all->matches($line1, []));
        $this->assertTrue($all->appliesTo($lines, []));
        $this->assertSame(100.0, $all->sumApplicableSubtotal($lines, [], 100.0));

        // PRODUCTS
        $products = ApplicableTo::PRODUCTS;
        $conditionsProducts = [
            'applicable_to' => [
                'type' => 'products',
                'product_ids' => [10],
            ],
        ];
        $this->assertTrue($products->matches($line1, $conditionsProducts));
        $this->assertFalse($products->matches($line2, $conditionsProducts));
        $this->assertTrue($products->appliesTo($lines, $conditionsProducts));
        $this->assertSame(40.0, $products->sumApplicableSubtotal($lines, $conditionsProducts, 100.0));

        // PRODUCTS edge case: empty product_ids applies to everything for validity
        $emptyProducts = ['applicable_to' => ['type' => 'products', 'product_ids' => []]];
        $this->assertTrue($products->appliesTo($lines, $emptyProducts));

        // CATEGORIES
        $categories = ApplicableTo::CATEGORIES;
        $conditionsCategories = [
            'applicable_to' => [
                'type' => 'categories',
                'category_ids' => [2],
            ],
        ];
        $this->assertFalse($categories->matches($line1, $conditionsCategories));
        $this->assertTrue($categories->matches($line2, $conditionsCategories));
        $this->assertTrue($categories->appliesTo($lines, $conditionsCategories));
        $this->assertSame(60.0, $categories->sumApplicableSubtotal($lines, $conditionsCategories, 100.0));

        // CATEGORIES edge case: empty category_ids applies to everything for validity
        $emptyCategories = ['applicable_to' => ['type' => 'categories', 'category_ids' => []]];
        $this->assertTrue($categories->appliesTo($lines, $emptyCategories));
    }

    public function test_apply_coupon_is_atomic_single_update_query(): void
    {
        $coupon = Coupon::factory()->create([
            'usage_count' => 3,
            'total_consumed' => 25.50,
        ]);

        $queryLog = [];
        DB::listen(function ($query) use (&$queryLog) {
            if (str_contains(mb_strtolower($query->sql), 'update') && str_contains(mb_strtolower($query->sql), 'coupons')) {
                $queryLog[] = $query->sql;
            }
        });

        $this->couponService->applyCoupon($coupon, 15.25);

        // Exactly ONE update statement was executed
        $this->assertCount(1, $queryLog, 'Expected exactly one UPDATE statement on coupons');
        $this->assertStringContainsString('usage_count', $queryLog[0]);
        $this->assertStringContainsString('total_consumed', $queryLog[0]);

        $coupon->refresh();
        $this->assertSame(4, $coupon->usage_count);
        $this->assertSame(40.75, (float) $coupon->total_consumed);
    }
}
