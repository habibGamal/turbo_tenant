<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Cart\Formatters;

use App\Plugins\CartEngine\src\Models\Cart;
use App\Plugins\CartEngine\src\Models\CartItem;
use App\Plugins\CartEngine\src\Models\CartItemExtra;
use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\ExtraOption;
use App\Plugins\CatalogProducts\src\Models\ExtraOptionItem;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use App\Plugins\CartEngine\src\Services\Calculators\CartPriceCalculator;
use App\Plugins\CartEngine\src\Services\Formatters\CartItemFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CartItemFormatterTest extends TestCase
{
    use RefreshDatabase;

    private CartItemFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new CartItemFormatter(new CartPriceCalculator());
    }

    public function test_format_cart_returns_empty_when_cart_is_null(): void
    {
        $result = $this->formatter->formatCart(null);

        $this->assertSame(['items' => [], 'total' => 0], $result);
    }

    public function test_format_item_structure(): void
    {
        $category = Category::factory()->create();
        $extraOption = ExtraOption::factory()->create(['name' => 'Toppings']);
        $extraItem = ExtraOptionItem::factory()->create([
            'extra_option_id' => $extraOption->id,
            'name' => 'Cheese',
            'name_ar' => 'جبنة',
            'price' => 5.00,
        ]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'name' => 'Burger',
            'name_ar' => 'برجر',
            'base_price' => 20.00,
            'price_after_discount' => null,
            'is_active' => true,
            'sell_by_weight' => false,
            'extra_option_id' => $extraOption->id,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Large',
            'name_ar' => 'كبير',
            'price' => 25.00,
        ]);

        $cart = Cart::factory()->create();
        $cartItem = CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity' => '2.000',
            'weight_multiplier' => 1,
        ]);
        CartItemExtra::create([
            'cart_item_id' => $cartItem->id,
            'extra_option_item_id' => $extraItem->id,
            'quantity' => 1,
        ]);

        $cartItem->load([
            'product.category',
            'product.extraOption.items',
            'product.weightOption.values',
            'variant',
            'weightOptionValue',
            'extras.extraOptionItem',
        ]);

        $formatted = $this->formatter->formatItem($cartItem);

        $this->assertSame($cartItem->id, $formatted['id']);
        $this->assertSame($product->id, $formatted['product_id']);
        $this->assertSame($variant->id, $formatted['variant_id']);
        $this->assertEquals(2.0, $formatted['quantity']);
        $this->assertSame(1, $formatted['weight_multiplier']);
        $this->assertSame('Burger', $formatted['product']['name']);
        $this->assertSame('Large', $formatted['variant']['name']);
        $this->assertEquals(25.00, $formatted['price']);
        $this->assertEquals(5.00, $formatted['extras_total']);
        // (25 + 5) * 2 = 60.00
        $this->assertEquals(60.00, $formatted['subtotal']);
        $this->assertCount(1, $formatted['extras']);
        $this->assertSame('Cheese', $formatted['extras'][0]['name']);
    }
}
