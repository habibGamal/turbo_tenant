<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Cart\Validators\Rules;

use App\Plugins\CartEngine\src\Services\Validators\Rules\ExtrasRule;
use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\ExtraOption;
use App\Plugins\CatalogProducts\src\Models\ExtraOptionItem;
use App\Plugins\CatalogProducts\src\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ExtrasRuleTest extends TestCase
{
    use RefreshDatabase;

    private ExtrasRule $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new ExtrasRule();
    }

    public function test_rejects_extras_when_not_allowed_on_product(): void
    {
        $category = Category::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'extra_option_id' => null,
        ]);
        $product->load('extraOption.items');

        $errors = $this->rule->validate($product, [
            ['id' => 1, 'quantity' => 1],
        ]);

        $this->assertSame([
            'extras' => ['This product does not allow extras.'],
        ], $errors);
    }

    public function test_rejects_duplicate_extras(): void
    {
        $category = Category::factory()->create();
        $extraOption = ExtraOption::factory()->create(['allow_multiple' => true]);
        $item = ExtraOptionItem::factory()->create(['extra_option_id' => $extraOption->id]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'extra_option_id' => $extraOption->id,
        ]);
        $product->load('extraOption.items');

        $errors = $this->rule->validate($product, [
            ['id' => $item->id, 'quantity' => 1],
            ['id' => $item->id, 'quantity' => 1],
        ]);

        $this->assertSame([
            'extras' => ['Duplicate extras are not allowed.'],
        ], $errors);
    }

    public function test_rejects_multiple_extras_when_not_allowed(): void
    {
        $category = Category::factory()->create();
        $extraOption = ExtraOption::factory()->create(['allow_multiple' => false]);
        $item1 = ExtraOptionItem::factory()->create(['extra_option_id' => $extraOption->id]);
        $item2 = ExtraOptionItem::factory()->create(['extra_option_id' => $extraOption->id]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'extra_option_id' => $extraOption->id,
        ]);
        $product->load('extraOption.items');

        $errors = $this->rule->validate($product, [
            ['id' => $item1->id, 'quantity' => 1],
            ['id' => $item2->id, 'quantity' => 1],
        ]);

        $this->assertSame([
            'extras' => ['Multiple extras are not allowed for this product.'],
        ], $errors);
    }

    public function test_enforces_min_selections_when_empty_or_fewer_than_min(): void
    {
        $category = Category::factory()->create();
        $extraOption = ExtraOption::factory()->create([
            'allow_multiple' => true,
            'min_selections' => 2,
            'max_selections' => 4,
        ]);
        $item1 = ExtraOptionItem::factory()->create(['extra_option_id' => $extraOption->id]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'extra_option_id' => $extraOption->id,
        ]);
        $product->load('extraOption.items');

        // Empty input
        $emptyErrors = $this->rule->validate($product, []);
        $this->assertSame([
            'extras' => ['Minimum 2 extra selection(s) required.'],
        ], $emptyErrors);

        // One item (less than 2)
        $fewErrors = $this->rule->validate($product, [
            ['id' => $item1->id, 'quantity' => 1],
        ]);
        $this->assertSame([
            'extras' => ['Minimum 2 extra selection(s) required.'],
        ], $fewErrors);
    }

    public function test_enforces_max_selections(): void
    {
        $category = Category::factory()->create();
        $extraOption = ExtraOption::factory()->create([
            'allow_multiple' => true,
            'min_selections' => 1,
            'max_selections' => 2,
        ]);
        $item1 = ExtraOptionItem::factory()->create(['extra_option_id' => $extraOption->id]);
        $item2 = ExtraOptionItem::factory()->create(['extra_option_id' => $extraOption->id]);
        $item3 = ExtraOptionItem::factory()->create(['extra_option_id' => $extraOption->id]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'extra_option_id' => $extraOption->id,
        ]);
        $product->load('extraOption.items');

        $errors = $this->rule->validate($product, [
            ['id' => $item1->id, 'quantity' => 1],
            ['id' => $item2->id, 'quantity' => 1],
            ['id' => $item3->id, 'quantity' => 1],
        ]);

        $this->assertSame([
            'extras' => ['Maximum 2 extra selection(s) allowed.'],
        ], $errors);
    }

    public function test_rejects_extra_not_belonging_to_product(): void
    {
        $category = Category::factory()->create();
        $extraOption1 = ExtraOption::factory()->create(['allow_multiple' => true]);
        $extraOption2 = ExtraOption::factory()->create(['allow_multiple' => true]);
        $otherItem = ExtraOptionItem::factory()->create(['extra_option_id' => $extraOption2->id]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'extra_option_id' => $extraOption1->id,
        ]);
        $product->load('extraOption.items');

        $errors = $this->rule->validate($product, [
            ['id' => $otherItem->id, 'quantity' => 1],
        ]);

        $this->assertSame([
            'extras' => ['One or more selected extras do not belong to this product.'],
        ], $errors);
    }

    public function test_rejects_quantity_greater_than_one_when_not_allowed(): void
    {
        $category = Category::factory()->create();
        $extraOption = ExtraOption::factory()->create(['allow_multiple' => true]);
        $item = ExtraOptionItem::factory()->create([
            'extra_option_id' => $extraOption->id,
            'name' => 'Cheese',
            'allow_quantity' => false,
        ]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'extra_option_id' => $extraOption->id,
        ]);
        $product->load('extraOption.items');

        $errors = $this->rule->validate($product, [
            ['id' => $item->id, 'quantity' => 2],
        ]);

        $this->assertSame([
            'extras' => ["Quantity greater than 1 is not allowed for extra 'Cheese'."],
        ], $errors);
    }

    public function test_passes_when_extras_are_valid(): void
    {
        $category = Category::factory()->create();
        $extraOption = ExtraOption::factory()->create(['allow_multiple' => true]);
        $item1 = ExtraOptionItem::factory()->create([
            'extra_option_id' => $extraOption->id,
            'allow_quantity' => true,
        ]);
        $item2 = ExtraOptionItem::factory()->create([
            'extra_option_id' => $extraOption->id,
            'allow_quantity' => false,
        ]);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'is_active' => true,
            'extra_option_id' => $extraOption->id,
        ]);
        $product->load('extraOption.items');

        $errors = $this->rule->validate($product, [
            ['id' => $item1->id, 'quantity' => 3],
            ['id' => $item2->id, 'quantity' => 1],
        ]);

        $this->assertSame([], $errors);
    }
}
