<?php

declare(strict_types=1);

namespace Tests\Feature\ComboPackages;

use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\ExtraOption;
use App\Plugins\CatalogProducts\src\Models\ExtraOptionItem;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use App\Plugins\ComboPackages\src\Models\Package;
use App\Plugins\ComboPackages\src\Models\PackageGroup;
use App\Plugins\ComboPackages\src\Models\PackageItem;
use App\Plugins\ComboPackages\src\Services\PackageSelectionValidator;
use App\Plugins\ComboPackages\src\Services\SellablePackageFilter;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->category = Category::factory()->create();

    $this->productMain = Product::factory()->create([
        'category_id' => $this->category->id,
        'name' => 'Burger',
        'base_price' => 100.0,
        'is_active' => true,
        'sell_by_weight' => false,
    ]);

    $this->productDrink = Product::factory()->create([
        'category_id' => $this->category->id,
        'name' => 'Soda',
        'base_price' => 20.0,
        'is_active' => true,
        'sell_by_weight' => false,
    ]);

    $this->variantDrinkSmall = ProductVariant::create([
        'product_id' => $this->productDrink->id,
        'name' => 'Small',
        'price' => 20.0,
        'is_available' => true,
        'sort_order' => 1,
    ]);

    $this->variantDrinkLarge = ProductVariant::create([
        'product_id' => $this->productDrink->id,
        'name' => 'Large',
        'price' => 25.0,
        'is_available' => true,
        'sort_order' => 2,
    ]);

    $this->variantDrinkUnavailable = ProductVariant::create([
        'product_id' => $this->productDrink->id,
        'name' => 'Huge',
        'price' => 30.0,
        'is_available' => false,
        'sort_order' => 3,
    ]);

    $this->productSide1 = Product::factory()->create([
        'category_id' => $this->category->id,
        'name' => 'Fries',
        'base_price' => 30.0,
        'is_active' => true,
        'sell_by_weight' => false,
    ]);

    $this->productSide2 = Product::factory()->create([
        'category_id' => $this->category->id,
        'name' => 'Onion Rings',
        'base_price' => 35.0,
        'is_active' => true,
        'sell_by_weight' => false,
    ]);

    $this->package = Package::factory()->create([
        'name' => 'Value Combo',
        'name_ar' => 'كومبو التوفير',
        'price' => 140.0,
        'is_active' => true,
    ]);

    // Group 1: all
    $this->groupAll = PackageGroup::create([
        'package_id' => $this->package->id,
        'name' => 'Main Sandwich',
        'selection_type' => 'all',
        'sort_order' => 1,
    ]);
    $this->itemBurger = PackageItem::create([
        'package_group_id' => $this->groupAll->id,
        'product_id' => $this->productMain->id,
        'variant_mode' => 'fixed',
        'quantity' => 1,
        'price_adjustment' => 0,
        'sort_order' => 1,
    ]);

    // Group 2: choose_one with customer_choice
    $this->groupDrink = PackageGroup::create([
        'package_id' => $this->package->id,
        'name' => 'Drink',
        'selection_type' => 'choose_one',
        'min_selections' => 1,
        'max_selections' => 1,
        'sort_order' => 2,
    ]);
    $this->itemDrink = PackageItem::create([
        'package_group_id' => $this->groupDrink->id,
        'product_id' => $this->productDrink->id,
        'variant_mode' => 'customer_choice',
        'quantity' => 1,
        'price_adjustment' => 0,
        'sort_order' => 1,
    ]);

    // Group 3: choose_multiple
    $this->groupSides = PackageGroup::create([
        'package_id' => $this->package->id,
        'name' => 'Sides',
        'selection_type' => 'choose_multiple',
        'min_selections' => 1,
        'max_selections' => 2,
        'allow_repeats' => false,
        'sort_order' => 3,
    ]);
    $this->itemFries = PackageItem::create([
        'package_group_id' => $this->groupSides->id,
        'product_id' => $this->productSide1->id,
        'variant_mode' => 'fixed',
        'quantity' => 1,
        'price_adjustment' => 0,
        'sort_order' => 1,
    ]);
    $this->itemRings = PackageItem::create([
        'package_group_id' => $this->groupSides->id,
        'product_id' => $this->productSide2->id,
        'variant_mode' => 'fixed',
        'quantity' => 1,
        'price_adjustment' => 5.0,
        'sort_order' => 2,
    ]);

    $this->validator = new PackageSelectionValidator();
    $filter = new SellablePackageFilter();
    $this->sellablePackage = $filter->filterOne($this->package);
});

it('passes validation for valid selections across all groups', function (): void {
    $selections = [
        ['package_item_id' => $this->itemDrink->id, 'quantity' => 1, 'variant_id' => $this->variantDrinkLarge->id],
        ['package_item_id' => $this->itemFries->id, 'quantity' => 1],
    ];

    $picks = $this->validator->validate($this->sellablePackage, $selections);

    expect($picks)->toHaveCount(2)
        ->and($picks[0]['package_item_id'])->toBe($this->itemDrink->id)
        ->and($picks[0]['variant_id'])->toBe($this->variantDrinkLarge->id)
        ->and($picks[1]['package_item_id'])->toBe($this->itemFries->id);
});

it('ignores all group items if sent in client selections', function (): void {
    $selections = [
        ['package_item_id' => $this->itemBurger->id, 'quantity' => 1], // all group
        ['package_item_id' => $this->itemDrink->id, 'quantity' => 1, 'variant_id' => $this->variantDrinkSmall->id],
        ['package_item_id' => $this->itemFries->id, 'quantity' => 1],
    ];

    $picks = $this->validator->validate($this->sellablePackage, $selections);

    // itemBurger is ignored, only drink and fries are in picks
    expect($picks)->toHaveCount(2)
        ->and(collect($picks)->pluck('package_item_id')->all())->not->toContain($this->itemBurger->id);
});

it('rejects an item from another package or invalid id', function (): void {
    $selections = [
        ['package_item_id' => 99999, 'quantity' => 1],
    ];

    try {
        $this->validator->validate($this->sellablePackage, $selections);
        $this->fail('Expected ValidationException not thrown');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('selections.0.package_item_id');
    }
});

it('rejects selection with quantity less than 1', function (): void {
    $selections = [
        ['package_item_id' => $this->itemFries->id, 'quantity' => 0],
    ];

    try {
        $this->validator->validate($this->sellablePackage, $selections);
        $this->fail('Expected ValidationException not thrown');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('selections.0.package_item_id');
    }
});

it('rejects duplicate item when allow_repeats is false', function (): void {
    $selections = [
        ['package_item_id' => $this->itemDrink->id, 'quantity' => 1, 'variant_id' => $this->variantDrinkSmall->id],
        ['package_item_id' => $this->itemFries->id, 'quantity' => 1],
        ['package_item_id' => $this->itemFries->id, 'quantity' => 1], // duplicate fries
    ];

    try {
        $this->validator->validate($this->sellablePackage, $selections);
        $this->fail('Expected ValidationException not thrown');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('selections.2.package_item_id');
    }
});

it('allows stepper quantity greater than 1 when allow_repeats is true', function (): void {
    $this->groupSides->update(['allow_repeats' => true, 'max_selections' => 5]);
    $filter = new SellablePackageFilter();
    $pkg = $filter->filterOne($this->package->fresh());

    $selections = [
        ['package_item_id' => $this->itemDrink->id, 'quantity' => 1, 'variant_id' => $this->variantDrinkSmall->id],
        ['package_item_id' => $this->itemFries->id, 'quantity' => 3], // 3 fries
    ];

    $picks = $this->validator->validate($pkg, $selections);
    $friesPick = collect($picks)->firstWhere('package_item_id', $this->itemFries->id);

    expect($friesPick['quantity'])->toBe(3);
});

it('rejects choose_one group when no pick is made', function (): void {
    $selections = [
        ['package_item_id' => $this->itemFries->id, 'quantity' => 1],
    ];

    try {
        $this->validator->validate($this->sellablePackage, $selections);
        $this->fail('Expected ValidationException not thrown');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey("groups.{$this->groupDrink->id}");
    }
});

it('rejects choose_multiple group when count is less than min_selections', function (): void {
    $selections = [
        ['package_item_id' => $this->itemDrink->id, 'quantity' => 1, 'variant_id' => $this->variantDrinkSmall->id],
        // sides omitted => count 0 < min 1
    ];

    try {
        $this->validator->validate($this->sellablePackage, $selections);
        $this->fail('Expected ValidationException not thrown');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey("groups.{$this->groupSides->id}");
    }
});

it('rejects choose_multiple group when count exceeds max_selections', function (): void {
    $this->groupSides->update(['allow_repeats' => true, 'max_selections' => 2]);
    $filter = new SellablePackageFilter();
    $pkg = $filter->filterOne($this->package->fresh());

    $selections = [
        ['package_item_id' => $this->itemDrink->id, 'quantity' => 1, 'variant_id' => $this->variantDrinkSmall->id],
        ['package_item_id' => $this->itemFries->id, 'quantity' => 2],
        ['package_item_id' => $this->itemRings->id, 'quantity' => 1], // total 3 > max 2
    ];

    try {
        $this->validator->validate($pkg, $selections);
        $this->fail('Expected ValidationException not thrown');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey("groups.{$this->groupSides->id}");
    }
});

it('rejects customer_choice item when variant_id is missing', function (): void {
    $selections = [
        ['package_item_id' => $this->itemDrink->id, 'quantity' => 1], // no variant_id
        ['package_item_id' => $this->itemFries->id, 'quantity' => 1],
    ];

    try {
        $this->validator->validate($this->sellablePackage, $selections);
        $this->fail('Expected ValidationException not thrown');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('selections.0.package_item_id');
    }
});

it('rejects customer_choice item when variant is unavailable', function (): void {
    $selections = [
        ['package_item_id' => $this->itemDrink->id, 'quantity' => 1, 'variant_id' => $this->variantDrinkUnavailable->id],
        ['package_item_id' => $this->itemFries->id, 'quantity' => 1],
    ];

    try {
        $this->validator->validate($this->sellablePackage, $selections);
        $this->fail('Expected ValidationException not thrown');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('selections.0.package_item_id');
    }
});

it('rejects fixed item when variant_id is sent', function (): void {
    $selections = [
        ['package_item_id' => $this->itemDrink->id, 'quantity' => 1, 'variant_id' => $this->variantDrinkSmall->id],
        ['package_item_id' => $this->itemFries->id, 'quantity' => 1, 'variant_id' => 999], // fixed item sent variant
    ];

    try {
        $this->validator->validate($this->sellablePackage, $selections);
        $this->fail('Expected ValidationException not thrown');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('selections.1.package_item_id');
    }
});

it('provides Arabic error messages when app locale is ar', function (): void {
    app()->setLocale('ar');

    $selections = [
        ['package_item_id' => $this->itemFries->id, 'quantity' => 0],
    ];

    try {
        $this->validator->validate($this->sellablePackage, $selections);
        $this->fail('Expected ValidationException not thrown');
    } catch (ValidationException $e) {
        expect($e->errors()['selections.0.package_item_id'][0])->toContain('يجب أن تكون الكمية 1 على الأقل');
    } finally {
        app()->setLocale('en');
    }
});

it('accepts extras on an allow_extras item', function (): void {
    $extraOption = ExtraOption::create([
        'name' => 'Toppings',
        'is_active' => true,
        'min_selections' => 0,
        'max_selections' => 3,
        'allow_multiple' => true,
    ]);
    $extraCheese = ExtraOptionItem::create([
        'extra_option_id' => $extraOption->id,
        'name' => 'Cheese',
        'price' => 5.0,
        'allow_quantity' => true,
    ]);
    $this->productDrink->update(['extra_option_id' => $extraOption->id]);
    $this->itemDrink->update(['allow_extras' => true]);

    $selections = [
        [
            'package_item_id' => $this->itemDrink->id,
            'quantity' => 1,
            'variant_id' => $this->variantDrinkLarge->id,
            'extras' => [['id' => $extraCheese->id, 'quantity' => 2]],
            'notes' => 'Extra cold',
        ],
        ['package_item_id' => $this->itemFries->id, 'quantity' => 1],
    ];

    $picks = $this->validator->validate($this->sellablePackage->fresh(), $selections);
    expect($picks)->toHaveCount(2)
        ->and($picks[0]['extras'])->toHaveCount(1)
        ->and($picks[0]['notes'])->toBe('Extra cold');
});

it('rejects extras on a non-allow_extras item', function (): void {
    $extraOption = ExtraOption::create([
        'name' => 'Toppings',
        'is_active' => true,
        'min_selections' => 0,
        'max_selections' => 3,
        'allow_multiple' => true,
    ]);
    $extraCheese = ExtraOptionItem::create([
        'extra_option_id' => $extraOption->id,
        'name' => 'Cheese',
        'price' => 5.0,
        'allow_quantity' => true,
    ]);
    $this->productDrink->update(['extra_option_id' => $extraOption->id]);
    $this->itemDrink->update(['allow_extras' => false]);

    $selections = [
        [
            'package_item_id' => $this->itemDrink->id,
            'quantity' => 1,
            'variant_id' => $this->variantDrinkLarge->id,
            'extras' => [['id' => $extraCheese->id, 'quantity' => 1]],
        ],
        ['package_item_id' => $this->itemFries->id, 'quantity' => 1],
    ];

    try {
        $this->validator->validate($this->sellablePackage->fresh(), $selections);
        $this->fail('Expected ValidationException was not thrown');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('selections.0.extras');
    }
});

it('rejects extra not in the product option', function (): void {
    $extraOption = ExtraOption::create([
        'name' => 'Toppings',
        'is_active' => true,
        'min_selections' => 0,
        'max_selections' => 3,
        'allow_multiple' => true,
    ]);
    $this->productDrink->update(['extra_option_id' => $extraOption->id]);
    $this->itemDrink->update(['allow_extras' => true]);

    $selections = [
        [
            'package_item_id' => $this->itemDrink->id,
            'quantity' => 1,
            'variant_id' => $this->variantDrinkLarge->id,
            'extras' => [['id' => 999999, 'quantity' => 1]],
        ],
        ['package_item_id' => $this->itemFries->id, 'quantity' => 1],
    ];

    try {
        $this->validator->validate($this->sellablePackage->fresh(), $selections);
        $this->fail('Expected ValidationException was not thrown');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('selections.0.extras');
    }
});

it('rejects quantity above 1 on an extra without allow_quantity', function (): void {
    $extraOption = ExtraOption::create([
        'name' => 'Toppings',
        'is_active' => true,
        'min_selections' => 0,
        'max_selections' => 3,
        'allow_multiple' => true,
    ]);
    $extraItem = ExtraOptionItem::create([
        'extra_option_id' => $extraOption->id,
        'name' => 'Lime',
        'price' => 1.0,
        'allow_quantity' => false,
    ]);
    $this->productDrink->update(['extra_option_id' => $extraOption->id]);
    $this->itemDrink->update(['allow_extras' => true]);

    $selections = [
        [
            'package_item_id' => $this->itemDrink->id,
            'quantity' => 1,
            'variant_id' => $this->variantDrinkLarge->id,
            'extras' => [['id' => $extraItem->id, 'quantity' => 2]],
        ],
        ['package_item_id' => $this->itemFries->id, 'quantity' => 1],
    ];

    try {
        $this->validator->validate($this->sellablePackage->fresh(), $selections);
        $this->fail('Expected ValidationException was not thrown');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('selections.0.extras');
    }
});

it('accepts extras and notes on an all item and keeps counts unchanged', function (): void {
    $extraOption = ExtraOption::create([
        'name' => 'Sauces',
        'is_active' => true,
        'min_selections' => 0,
        'max_selections' => 2,
        'allow_multiple' => true,
    ]);
    $extraSauce = ExtraOptionItem::create([
        'extra_option_id' => $extraOption->id,
        'name' => 'BBQ',
        'price' => 3.0,
        'allow_quantity' => true,
    ]);
    $this->productMain->update(['extra_option_id' => $extraOption->id]);
    $this->itemBurger->update(['allow_extras' => true]);

    $selections = [
        [
            'package_item_id' => $this->itemBurger->id,
            'extras' => [['id' => $extraSauce->id, 'quantity' => 1]],
            'notes' => 'No pickles please',
        ],
        ['package_item_id' => $this->itemDrink->id, 'quantity' => 1, 'variant_id' => $this->variantDrinkLarge->id],
        ['package_item_id' => $this->itemFries->id, 'quantity' => 1],
    ];

    $allSelections = [];
    $picks = $this->validator->validate($this->sellablePackage->fresh(), $selections, $allSelections);

    // Conditional picks count remains exactly 2 (drink and fries)
    expect($picks)->toHaveCount(2)
        ->and($allSelections)->toHaveKey($this->itemBurger->id)
        ->and($allSelections[$this->itemBurger->id]['notes'])->toBe('No pickles please')
        ->and($allSelections[$this->itemBurger->id]['extras'])->toHaveCount(1);
});

it('rejects notes over 200 characters', function (): void {
    $selections = [
        [
            'package_item_id' => $this->itemDrink->id,
            'quantity' => 1,
            'variant_id' => $this->variantDrinkLarge->id,
            'notes' => str_repeat('a', 201),
        ],
        ['package_item_id' => $this->itemFries->id, 'quantity' => 1],
    ];

    try {
        $this->validator->validate($this->sellablePackage->fresh(), $selections);
        $this->fail('Expected ValidationException was not thrown');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('selections.0.notes');
    }
});

it('ignores the product minimum extras when allow_extras is false', function (): void {
    $extraOption = ExtraOption::create([
        'name' => 'Mandatory Extras',
        'is_active' => true,
        'min_selections' => 2,
        'max_selections' => 3,
        'allow_multiple' => true,
    ]);
    $this->productDrink->update(['extra_option_id' => $extraOption->id]);
    // allow_extras is false
    $this->itemDrink->update(['allow_extras' => false]);

    $selections = [
        ['package_item_id' => $this->itemDrink->id, 'quantity' => 1, 'variant_id' => $this->variantDrinkLarge->id],
        ['package_item_id' => $this->itemFries->id, 'quantity' => 1],
    ];

    // No extras passed on itemDrink, and because allow_extras is false, product's min_selections (2) is ignored!
    $picks = $this->validator->validate($this->sellablePackage->fresh(), $selections);
    expect($picks)->toHaveCount(2);
});
