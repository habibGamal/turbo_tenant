<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Cart\Validators;

use App\Models\Category;
use App\Models\Product;
use App\Services\Cart\Validators\ProductValidator;
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
        $this->validator = new ProductValidator();
    }

    public function test_passes_when_product_is_active(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
        ]);

        $this->validator->ensureProductIsActive($product->id);
        $this->assertTrue(true);
    }

    public function test_throws_validation_exception_when_product_is_inactive(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => false,
        ]);

        $this->expectException(ValidationException::class);
        $this->validator->ensureProductIsActive($product->id);
    }

    public function test_throws_validation_exception_when_product_does_not_exist(): void
    {
        $this->expectException(ValidationException::class);
        $this->validator->ensureProductIsActive(999999);
    }
}
