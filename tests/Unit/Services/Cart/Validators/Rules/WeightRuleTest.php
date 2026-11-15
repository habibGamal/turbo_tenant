<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Cart\Validators\Rules;

use App\Plugins\CartEngine\src\Services\Validators\Rules\WeightRule;
use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\WeightOption;
use App\Plugins\CatalogProducts\src\Models\WeightOptionValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WeightRuleTest extends TestCase
{
    use RefreshDatabase;

    private WeightRule $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new WeightRule();
    }

    public function test_weight_option_is_required_when_sell_by_weight_is_true(): void
    {
        $category = Category::factory()->create();
        $weightOption = WeightOption::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'sell_by_weight' => true,
            'weight_options_id' => $weightOption->id,
        ]);
        $product->load('weightOption.values');

        $errors = $this->rule->validate($product, null);

        $this->assertSame([
            'weight_option_value_id' => ['Weight option is required for this product.'],
        ], $errors);
    }

    public function test_rejects_weight_option_not_belonging_to_product(): void
    {
        $category = Category::factory()->create();
        $weightOption1 = WeightOption::factory()->create();
        $weightOption2 = WeightOption::factory()->create();
        $otherValue = WeightOptionValue::factory()->create([
            'weight_option_id' => $weightOption2->id,
        ]);

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'sell_by_weight' => true,
            'weight_options_id' => $weightOption1->id,
        ]);
        $product->load('weightOption.values');

        $errors = $this->rule->validate($product, $otherValue->id);

        $this->assertSame([
            'weight_option_value_id' => ['Selected weight option does not belong to this product.'],
        ], $errors);
    }

    public function test_rejects_weight_option_when_not_sold_by_weight(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'sell_by_weight' => false,
        ]);
        $product->load('weightOption.values');

        $errors = $this->rule->validate($product, 123);

        $this->assertSame([
            'weight_option_value_id' => ['This product is not sold by weight.'],
        ], $errors);
    }

    public function test_passes_when_weight_option_is_valid(): void
    {
        $category = Category::factory()->create();
        $weightOption = WeightOption::factory()->create();
        $value = WeightOptionValue::factory()->create([
            'weight_option_id' => $weightOption->id,
        ]);

        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'sell_by_weight' => true,
            'weight_options_id' => $weightOption->id,
        ]);
        $product->load('weightOption.values');

        $errors = $this->rule->validate($product, $value->id);

        $this->assertSame([], $errors);
    }
}
