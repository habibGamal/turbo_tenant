<?php

declare(strict_types=1);

namespace Tests\Unit\CouponsPromotions;

use App\Plugins\CartEngine\src\DTOs\CartLine;
use App\Plugins\CouponsPromotions\src\DTOs\CouponContext;
use App\Plugins\CouponsPromotions\src\Enums\ApplicableTo;
use App\Plugins\CouponsPromotions\src\Enums\DiscountType;
use App\Plugins\CouponsPromotions\src\Models\Coupon;
use App\Plugins\CouponsPromotions\src\Rules\MinOrderTotalCouponRule;
use App\Plugins\CouponsPromotions\src\Services\CouponService;
use Tests\TestCase;

final class ApplicableToLineTypesTest extends TestCase
{
    public function test_all_with_every_line_eligible_returns_full_subtotal(): void
    {
        $product = $this->makeProductLine(subtotal: 40.0, couponEligible: true);
        $package = $this->makePackageLine(subtotal: 60.0, couponEligible: true);
        $lines = [$product, $package];

        $all = ApplicableTo::ALL;
        $this->assertTrue($all->matches($product, []));
        $this->assertTrue($all->matches($package, []));
        $this->assertTrue($all->appliesTo($lines, []));
        $this->assertSame(100.0, $all->sumApplicableSubtotal($lines, [], 100.0));
    }

    public function test_all_with_an_ineligible_package_line_excludes_it_from_base(): void
    {
        $product = $this->makeProductLine(subtotal: 40.0, couponEligible: true);
        $package = $this->makePackageLine(subtotal: 60.0, couponEligible: false);
        $lines = [$product, $package];

        $all = ApplicableTo::ALL;
        $this->assertTrue($all->matches($product, []));
        $this->assertFalse($all->matches($package, []));
        $this->assertTrue($all->appliesTo($lines, []));
        // Only the eligible product (40.0) contributes to the discount base
        $this->assertSame(40.0, $all->sumApplicableSubtotal($lines, [], 100.0));
    }

    public function test_products_and_categories_never_include_package_lines(): void
    {
        $package = $this->makePackageLine(packageId: 5, subtotal: 60.0, couponEligible: true);
        $product = $this->makeProductLine(productId: 1, subtotal: 40.0, categoryId: 10, couponEligible: true);
        $lines = [$package, $product];

        $products = ApplicableTo::PRODUCTS;
        $conditionsProducts = [
            'applicable_to' => [
                'type' => 'products',
                'product_ids' => [1, 5],
            ],
        ];

        // Package line never matches products, even if packageId matches product_ids
        $this->assertFalse($products->matches($package, $conditionsProducts));
        $this->assertTrue($products->matches($product, $conditionsProducts));
        $this->assertSame(40.0, $products->sumApplicableSubtotal($lines, $conditionsProducts, 100.0));

        $categories = ApplicableTo::CATEGORIES;
        $conditionsCategories = [
            'applicable_to' => [
                'type' => 'categories',
                'category_ids' => [10],
            ],
        ];

        // Package line never matches categories
        $this->assertFalse($categories->matches($package, $conditionsCategories));
        $this->assertTrue($categories->matches($product, $conditionsCategories));
        $this->assertSame(40.0, $categories->sumApplicableSubtotal($lines, $conditionsCategories, 100.0));
    }

    public function test_line_types_with_empty_ids_matches_all_lines_of_that_line_type(): void
    {
        $package1 = $this->makePackageLine(packageId: 1, subtotal: 50.0, couponEligible: true, itemId: 1);
        $package2 = $this->makePackageLine(packageId: 2, subtotal: 70.0, couponEligible: true, itemId: 2);
        $product = $this->makeProductLine(productId: 1, subtotal: 30.0, couponEligible: true, itemId: 3);
        $lines = [$package1, $package2, $product];

        $conditions = [
            'applicable_to' => [
                'type' => 'line_types',
                'line_type' => 'package',
                'ids' => [],
            ],
        ];

        $enum = ApplicableTo::fromConditions($conditions);
        $this->assertSame(ApplicableTo::LINE_TYPES, $enum);
        $this->assertSame([], $enum->ids($conditions));

        $this->assertTrue($enum->matches($package1, $conditions));
        $this->assertTrue($enum->matches($package2, $conditions));
        $this->assertFalse($enum->matches($product, $conditions));

        $this->assertTrue($enum->appliesTo($lines, $conditions));
        $this->assertSame(120.0, $enum->sumApplicableSubtotal($lines, $conditions, 150.0));
    }

    public function test_line_types_with_specific_ids(): void
    {
        $package1 = $this->makePackageLine(packageId: 1, subtotal: 50.0, couponEligible: true, itemId: 1);
        $package2 = $this->makePackageLine(packageId: 2, subtotal: 70.0, couponEligible: true, itemId: 2);
        $lines = [$package1, $package2];

        $conditions = [
            'applicable_to' => [
                'type' => 'line_types',
                'line_type' => 'package',
                'ids' => [1],
            ],
        ];

        $enum = ApplicableTo::fromConditions($conditions);
        $this->assertSame([1], $enum->ids($conditions));

        $this->assertTrue($enum->matches($package1, $conditions));
        $this->assertFalse($enum->matches($package2, $conditions));

        $this->assertTrue($enum->appliesTo($lines, $conditions));
        $this->assertSame(50.0, $enum->sumApplicableSubtotal($lines, $conditions, 120.0));
    }

    public function test_line_types_excludes_ineligible_lines(): void
    {
        $packageEligible = $this->makePackageLine(packageId: 1, subtotal: 50.0, couponEligible: true, itemId: 1);
        $packageIneligible = $this->makePackageLine(packageId: 2, subtotal: 70.0, couponEligible: false, itemId: 2);
        $lines = [$packageEligible, $packageIneligible];

        $conditions = [
            'applicable_to' => [
                'type' => 'line_types',
                'line_type' => 'package',
                'ids' => [],
            ],
        ];

        $enum = ApplicableTo::fromConditions($conditions);

        $this->assertTrue($enum->matches($packageEligible, $conditions));
        $this->assertFalse($enum->matches($packageIneligible, $conditions));

        // Subtotal excludes the ineligible package
        $this->assertSame(50.0, $enum->sumApplicableSubtotal($lines, $conditions, 120.0));
    }

    public function test_applies_to_is_false_when_cart_has_no_matching_lines(): void
    {
        $product = $this->makeProductLine(productId: 1, subtotal: 50.0, couponEligible: true);
        $ineligiblePackage = $this->makePackageLine(packageId: 2, subtotal: 60.0, couponEligible: false);

        $conditions = [
            'applicable_to' => [
                'type' => 'line_types',
                'line_type' => 'package',
                'ids' => [99],
            ],
        ];

        $enum = ApplicableTo::fromConditions($conditions);

        // Cart with only products has no matching package line
        $this->assertFalse($enum->appliesTo([$product], $conditions));

        // Cart with only ineligible package has no matching eligible package line
        $this->assertFalse($enum->appliesTo([$ineligiblePackage], $conditions));

        // Cart with non-matching package ID
        $eligibleOtherPackage = $this->makePackageLine(packageId: 5, subtotal: 60.0, couponEligible: true);
        $this->assertFalse($enum->appliesTo([$eligibleOtherPackage], $conditions));
    }

    public function test_min_order_rule_uses_full_cart_subtotal_regardless_of_line_eligibility(): void
    {
        $coupon = Coupon::factory()->create([
            'is_active' => true,
            'conditions' => [
                'min_order_total' => 100.0,
                'applicable_to' => [
                    'type' => 'line_types',
                    'line_type' => 'package',
                    'ids' => [],
                ],
            ],
        ]);

        $product = $this->makeProductLine(productId: 1, subtotal: 40.0, couponEligible: true);
        $package = $this->makePackageLine(packageId: 5, subtotal: 60.0, couponEligible: true);
        $lines = [$product, $package];

        // Total order subtotal is 100.0 (40 + 60), which meets the 100.0 minimum
        $context = new CouponContext(
            lines: $lines,
            subtotal: 100.0
        );

        $rule = new MinOrderTotalCouponRule;
        $this->assertNull($rule->check($coupon, $context));

        // When order subtotal is below minimum, error is returned
        $contextBelowMin = new CouponContext(
            lines: $lines,
            subtotal: 99.0
        );
        $this->assertSame('Minimum order total of 100 required', $rule->check($coupon, $contextBelowMin));
    }

    public function test_coupon_service_calculates_discount_on_line_types(): void
    {
        $coupon = Coupon::factory()->create([
            'type' => DiscountType::PERCENTAGE,
            'value' => 20.0,
            'conditions' => [
                'applicable_to' => [
                    'type' => 'line_types',
                    'line_type' => 'package',
                    'ids' => [5],
                ],
            ],
        ]);

        $packageMatch = $this->makePackageLine(packageId: 5, subtotal: 100.0, couponEligible: true, itemId: 1);
        $packageOther = $this->makePackageLine(packageId: 6, subtotal: 100.0, couponEligible: true, itemId: 2);
        $lines = [$packageMatch, $packageOther];

        /** @var CouponService $service */
        $service = app(CouponService::class);
        $discount = $service->calculateDiscount($coupon, $lines, 200.0);

        // 20% of 100.0 (only the matching package) = 20.0
        $this->assertSame(20.0, $discount);
    }

    private function makeProductLine(
        int $productId = 1,
        float $subtotal = 40.0,
        ?int $categoryId = 10,
        bool $couponEligible = true,
        int $itemId = 1
    ): CartLine {
        return new CartLine(
            itemId: $itemId,
            productId: $productId,
            categoryId: $categoryId,
            variantId: null,
            weightOptionValueId: null,
            weightMultiplier: 1,
            quantity: 1.0,
            unitPrice: $subtotal,
            extrasUnitTotal: 0.0,
            subtotal: $subtotal,
            productName: 'Product '.$productId,
            productNameAr: null,
            variantName: null,
            variantNameAr: null,
            weightLabel: null,
            type: 'product',
            referenceId: null,
            couponEligible: $couponEligible
        );
    }

    private function makePackageLine(
        int $packageId = 5,
        float $subtotal = 60.0,
        bool $couponEligible = true,
        int $itemId = 2
    ): CartLine {
        return new CartLine(
            itemId: $itemId,
            productId: null,
            categoryId: null,
            variantId: null,
            weightOptionValueId: null,
            weightMultiplier: 1,
            quantity: 1.0,
            unitPrice: $subtotal,
            extrasUnitTotal: 0.0,
            subtotal: $subtotal,
            productName: 'Combo Package '.$packageId,
            productNameAr: null,
            variantName: null,
            variantNameAr: null,
            weightLabel: null,
            type: 'package',
            referenceId: $packageId,
            couponEligible: $couponEligible
        );
    }
}
