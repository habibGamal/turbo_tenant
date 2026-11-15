<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Catalog;

use App\Plugins\CatalogProducts\src\DTOs\SelectionRules;
use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\ExtraOption;
use App\Plugins\CatalogProducts\src\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProductSelectionRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_null_when_product_has_no_extra_option(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'extra_option_id' => null,
        ]);

        $this->assertNull($product->effectiveSelectionRules());
    }

    public function test_falls_back_to_extra_option_defaults_when_no_product_overrides(): void
    {
        $category = Category::factory()->create();
        $extraOption = ExtraOption::factory()->create([
            'min_selections' => 1,
            'max_selections' => 3,
            'allow_multiple' => false,
        ]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'extra_option_id' => $extraOption->id,
            'min_selections' => null,
            'max_selections' => null,
            'allow_multiple' => null,
        ]);

        $rules = $product->effectiveSelectionRules();

        $this->assertInstanceOf(SelectionRules::class, $rules);
        $this->assertSame(1, $rules->minSelections);
        $this->assertSame(3, $rules->maxSelections);
        $this->assertFalse($rules->allowMultiple);
    }

    public function test_overrides_extra_option_defaults_with_product_values(): void
    {
        $category = Category::factory()->create();
        $extraOption = ExtraOption::factory()->create([
            'min_selections' => 1,
            'max_selections' => 2,
            'allow_multiple' => false,
        ]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'extra_option_id' => $extraOption->id,
            'min_selections' => 3,
            'max_selections' => 5,
            'allow_multiple' => true,
        ]);

        $rules = $product->effectiveSelectionRules();

        $this->assertInstanceOf(SelectionRules::class, $rules);
        $this->assertSame(3, $rules->minSelections);
        $this->assertSame(5, $rules->maxSelections);
        $this->assertTrue($rules->allowMultiple);
    }
}
