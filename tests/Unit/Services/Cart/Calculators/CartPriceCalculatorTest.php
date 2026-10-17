<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Cart\Calculators;

use App\Plugins\CartEngine\src\Models\Cart;
use App\Plugins\CartEngine\src\Models\CartItem;
use App\Plugins\CartEngine\src\Models\CartItemExtra;
use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\ExtraOption;
use App\Plugins\CatalogProducts\src\Models\ExtraOptionItem;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use App\Plugins\CatalogProducts\src\Models\WeightOption;
use App\Plugins\CatalogProducts\src\Models\WeightOptionValue;
use App\Plugins\CartEngine\src\Services\Calculators\CartPriceCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CartPriceCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private CartPriceCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new CartPriceCalculator();
    }

    public function test_get_effective_price_returns_base_price_when_no_discount(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'base_price' => 50.00,
            'price_after_discount' => null,
            'is_active' => true,
        ]);
        $cart = Cart::factory()->create();
        $item = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'variant_id' => null,
        ]);
        $item->load(['product', 'variant']);

        $this->assertEquals(50.00, $this->calculator->getEffectivePrice($item));
    }

    public function test_get_effective_price_returns_discount_price_when_available(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'base_price' => 50.00,
            'price_after_discount' => 35.00,
            'is_active' => true,
        ]);
        $cart = Cart::factory()->create();
        $item = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'variant_id' => null,
        ]);
        $item->load(['product', 'variant']);

        $this->assertEquals(35.00, $this->calculator->getEffectivePrice($item));
    }

    public function test_get_effective_price_prefers_variant_price(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'base_price' => 50.00,
            'price_after_discount' => 35.00,
            'is_active' => true,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'price' => 45.00,
        ]);
        $cart = Cart::factory()->create();
        $item = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
        ]);
        $item->load(['product', 'variant']);

        $this->assertEquals(45.00, $this->calculator->getEffectivePrice($item));
    }

    public function test_calculate_extras_total(): void
    {
        $category = Category::factory()->create();
        $extraOption = ExtraOption::factory()->create();
        $extraItem1 = ExtraOptionItem::factory()->create(['extra_option_id' => $extraOption->id, 'price' => 5.00]);
        $extraItem2 = ExtraOptionItem::factory()->create(['extra_option_id' => $extraOption->id, 'price' => 2.50]);

        $product = Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);
        $cart = Cart::factory()->create();
        $item = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'variant_id' => null,
        ]);
        CartItemExtra::create(['cart_item_id' => $item->id, 'extra_option_item_id' => $extraItem1->id, 'quantity' => 2]);
        CartItemExtra::create(['cart_item_id' => $item->id, 'extra_option_item_id' => $extraItem2->id, 'quantity' => 1]);

        $item->load('extras.extraOptionItem');

        // (5.00 * 2) + (2.50 * 1) = 12.50
        $this->assertEquals(12.50, $this->calculator->calculateExtrasTotal($item));
    }

    public function test_calculate_final_quantity_for_standard_product(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'sell_by_weight' => false,
        ]);
        $cart = Cart::factory()->create();
        $item = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'variant_id' => null,
            'quantity' => '3.500',
        ]);
        $item->load(['product', 'weightOptionValue']);

        $this->assertEquals(3.5, $this->calculator->calculateFinalQuantity($item));
    }

    public function test_calculate_final_quantity_for_weight_based_product(): void
    {
        $category = Category::factory()->create();
        $weightOption = WeightOption::factory()->create();
        $weightValue = WeightOptionValue::factory()->create([
            'weight_option_id' => $weightOption->id,
            'value' => '0.25',
        ]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'sell_by_weight' => true,
            'weight_options_id' => $weightOption->id,
        ]);
        $cart = Cart::factory()->create();
        $item = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'variant_id' => null,
            'weight_option_value_id' => $weightValue->id,
            'weight_multiplier' => 3,
        ]);
        $item->load(['product', 'weightOptionValue']);

        // 3 * 0.25 = 0.75
        $this->assertEquals(0.75, $this->calculator->calculateFinalQuantity($item));
    }

    public function test_calculate_total_sums_all_subtotals(): void
    {
        $items = [
            ['subtotal' => 25.50],
            ['subtotal' => 14.50],
            ['subtotal' => 10.00],
        ];

        $this->assertEquals(50.00, $this->calculator->calculateTotal($items));
    }
}
