<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Cart\Validators;

use App\Plugins\CartEngine\src\Services\Validators\ProductValidator;
use App\Plugins\CartEngine\src\Services\Validators\Rules\ExtrasRule;
use App\Plugins\CartEngine\src\Services\Validators\Rules\VariantRule;
use App\Plugins\CartEngine\src\Services\Validators\Rules\WeightRule;
use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\WeightOption;
use App\Plugins\CatalogProducts\src\Models\WeightOptionValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class ProductValidatorTest extends TestCase
{
    use RefreshDatabase;

    private ProductValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ProductValidator(
            new VariantRule(),
            new WeightRule(),
            new ExtrasRule()
        );
    }

    public function test_passes_when_product_is_active(): void
    {
        $this->expectNotToPerformAssertions();

        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'sell_by_weight' => false,
        ]);

        $this->validator->validateOptions($product->id, null, null);
    }

    public function test_throws_validation_exception_when_product_is_inactive(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => false,
        ]);

        $this->expectException(ValidationException::class);
        $this->validator->validateOptions($product->id, null, null);
    }

    public function test_throws_validation_exception_when_product_does_not_exist(): void
    {
        $this->expectException(ValidationException::class);
        $this->validator->validateOptions(999999, null, null);
    }

    public function test_validates_weight_option_required_when_sell_by_weight_is_true(): void
    {
        $category = Category::factory()->create();
        $weightOption = WeightOption::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'sell_by_weight' => true,
            'weight_options_id' => $weightOption->id,
        ]);

        try {
            $this->validator->validateOptions($product->id, null, null);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('weight_option_value_id', $e->errors());
            $this->assertSame('Weight option is required for this product.', $e->errors()['weight_option_value_id'][0]);
        }
    }

    public function test_validates_weight_option_rejected_when_not_sold_by_weight(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'sell_by_weight' => false,
        ]);

        try {
            $this->validator->validateOptions($product->id, null, 123);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('weight_option_value_id', $e->errors());
            $this->assertSame('This product is not sold by weight.', $e->errors()['weight_option_value_id'][0]);
        }
    }

    public function test_validate_update_weight_option_verifies_valid_and_invalid_weights(): void
    {
        $category = Category::factory()->create();
        $weightOption = WeightOption::factory()->create();
        $validValue = WeightOptionValue::factory()->create([
            'weight_option_id' => $weightOption->id,
        ]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'sell_by_weight' => true,
            'weight_options_id' => $weightOption->id,
        ]);

        // Valid weight option passes
        $this->validator->validateUpdateWeightOption($product->id, $validValue->id);

        // Invalid weight option throws
        try {
            $this->validator->validateUpdateWeightOption($product->id, 999999);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('weight_option_value_id', $e->errors());
            $this->assertSame('Selected weight option does not belong to this product.', $e->errors()['weight_option_value_id'][0]);
        }
    }
}
