<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Cart\Validators\Rules;

use App\Plugins\CartEngine\src\Services\Validators\Rules\VariantRule;
use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class VariantRuleTest extends TestCase
{
    use RefreshDatabase;

    private VariantRule $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new VariantRule();
    }

    public function test_variant_is_required_when_product_has_variants(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
        ]);
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_available' => true,
        ]);

        $product->load('variants');

        $errors = $this->rule->validate($product, null);

        $this->assertSame([
            'variant_id' => ['Variant is required for this product.'],
        ], $errors);
    }

    public function test_rejects_variant_belonging_to_another_product(): void
    {
        $category = Category::factory()->create();
        $product1 = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
        ]);
        $product2 = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
        ]);
        ProductVariant::factory()->create([
            'product_id' => $product1->id,
            'is_available' => true,
        ]);
        $variant2 = ProductVariant::factory()->create([
            'product_id' => $product2->id,
            'is_available' => true,
        ]);

        $product1->load('variants');

        $errors = $this->rule->validate($product1, $variant2->id);

        $this->assertSame([
            'variant_id' => ['Selected variant does not belong to this product.'],
        ], $errors);
    }

    public function test_rejects_unavailable_variant(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_available' => false,
        ]);

        $product->load('variants');

        $errors = $this->rule->validate($product, $variant->id);

        $this->assertSame([
            'variant_id' => ['Selected variant is currently unavailable.'],
        ], $errors);
    }

    public function test_rejects_variant_when_product_has_no_variants(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $product->load('variants');

        $errors = $this->rule->validate($product, 123);

        $this->assertSame([
            'variant_id' => ['This product does not have variants.'],
        ], $errors);
    }

    public function test_passes_when_variant_is_valid(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_available' => true,
        ]);

        $product->load('variants');

        $errors = $this->rule->validate($product, $variant->id);

        $this->assertSame([], $errors);
    }
}
