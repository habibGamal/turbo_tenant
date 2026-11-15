<?php

declare(strict_types=1);

namespace Tests\Feature\ComboPackages;

use App\Plugins\CartEngine\src\Models\Cart;
use App\Plugins\CartEngine\src\Models\CartItem;
use App\Plugins\CartEngine\src\Models\CartItemComponent;
use App\Plugins\CartEngine\src\Services\Contracts\CartContextResolverInterface;
use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\ExtraOption;
use App\Plugins\CatalogProducts\src\Models\ExtraOptionItem;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use App\Plugins\ComboPackages\src\Cart\PackageCartLineHandler;
use App\Plugins\ComboPackages\src\Models\Package;
use App\Plugins\ComboPackages\src\Models\PackageGroup;
use App\Plugins\ComboPackages\src\Models\PackageItem;
use App\Plugins\ComboPackages\src\Services\PackageAvailabilityService;
use App\Plugins\ComboPackages\src\Services\PackageLinePricer;
use App\Plugins\ComboPackages\src\Services\PackageSelectionValidator;
use App\Plugins\ComboPackages\src\Services\SellablePackageFilter;

beforeEach(function (): void {
    $this->category = Category::factory()->create();

    $this->productMain = Product::factory()->create([
        'category_id' => $this->category->id,
        'name' => 'Burger',
        'name_ar' => 'برجر',
        'base_price' => 100.0,
        'price_after_discount' => null,
        'is_active' => true,
    ]);

    $this->productDrink = Product::factory()->create([
        'category_id' => $this->category->id,
        'name' => 'Soda',
        'name_ar' => 'مشروب غازي',
        'base_price' => 20.0,
        'price_after_discount' => null,
        'is_active' => true,
    ]);

    $this->variantDrinkLarge = ProductVariant::create([
        'product_id' => $this->productDrink->id,
        'name' => 'Large',
        'name_ar' => 'كبير',
        'price' => 25.0,
        'is_available' => true,
    ]);

    $this->productSide = Product::factory()->create([
        'category_id' => $this->category->id,
        'name' => 'Fries',
        'name_ar' => 'بطاطس',
        'base_price' => 30.0,
        'price_after_discount' => null,
        'is_active' => true,
    ]);

    $this->package = Package::factory()->create([
        'name' => 'Mega Combo',
        'name_ar' => 'كومبو ميجا',
        'price' => 130.0,
        'allow_coupons' => true,
        'is_active' => true,
    ]);

    $this->groupAll = PackageGroup::create([
        'package_id' => $this->package->id,
        'name' => 'Burger Group',
        'selection_type' => 'all',
        'sort_order' => 1,
    ]);
    $this->itemBurger = PackageItem::create([
        'package_group_id' => $this->groupAll->id,
        'product_id' => $this->productMain->id,
        'variant_mode' => 'fixed',
        'quantity' => 2, // 2 burgers per combo
        'price_adjustment' => 0.0,
        'sort_order' => 1,
    ]);

    $this->groupDrink = PackageGroup::create([
        'package_id' => $this->package->id,
        'name' => 'Drinks',
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
        'price_adjustment' => 2.0,
        'sort_order' => 1,
    ]);

    $this->groupSides = PackageGroup::create([
        'package_id' => $this->package->id,
        'name' => 'Sides',
        'selection_type' => 'choose_multiple',
        'min_selections' => 1,
        'max_selections' => 2,
        'allow_repeats' => true,
        'sort_order' => 3,
    ]);
    $this->itemFries = PackageItem::create([
        'package_group_id' => $this->groupSides->id,
        'product_id' => $this->productSide->id,
        'variant_mode' => 'fixed',
        'quantity' => 1,
        'price_adjustment' => 3.0,
        'sort_order' => 1,
    ]);

    $this->handler = new PackageCartLineHandler(
        new SellablePackageFilter(),
        new PackageSelectionValidator(),
        new PackageLinePricer(),
        app(PackageAvailabilityService::class),
        app(CartContextResolverInterface::class)
    );

    $this->cart = Cart::create(['session_id' => 'test-sess-123']);
});

it('prepares composite cart line including all items and picks with correct quantities', function (): void {
    $payload = [
        'package_id' => $this->package->id,
        'selections' => [
            ['package_item_id' => $this->itemDrink->id, 'quantity' => 1, 'variant_id' => $this->variantDrinkLarge->id],
            ['package_item_id' => $this->itemFries->id, 'quantity' => 2],
        ],
    ];

    $prepared = $this->handler->prepare($payload);

    expect($prepared->type)->toBe('package')
        ->and($prepared->referenceId)->toBe($this->package->id)
        ->and($prepared->components)->toHaveCount(3); // 1 all item + 2 picks

    // all item: burger quantity = 2
    $burgerComp = collect($prepared->components)->firstWhere('sourceId', $this->itemBurger->id);
    expect($burgerComp->quantity)->toBe(2.0)
        ->and($burgerComp->unitPriceAdjustment)->toBe(0.0);

    // drink: adjustment = 2 (item adjustment) + (25 - 20 = 5 upsize delta) = 7
    $drinkComp = collect($prepared->components)->firstWhere('sourceId', $this->itemDrink->id);
    expect($drinkComp->quantity)->toBe(1.0)
        ->and($drinkComp->unitPriceAdjustment)->toBe(7.0);

    // fries: qty 2 * item adjustment 3 = 6
    $friesComp = collect($prepared->components)->firstWhere('sourceId', $this->itemFries->id);
    expect($friesComp->quantity)->toBe(2.0)
        ->and($friesComp->unitPriceAdjustment)->toBe(6.0);
});

it('produces a stable signature regardless of selection order', function (): void {
    $payload1 = [
        'package_id' => $this->package->id,
        'selections' => [
            ['package_item_id' => $this->itemDrink->id, 'quantity' => 1, 'variant_id' => $this->variantDrinkLarge->id],
            ['package_item_id' => $this->itemFries->id, 'quantity' => 1],
        ],
    ];

    $payload2 = [
        'package_id' => $this->package->id,
        'selections' => [
            ['package_item_id' => $this->itemFries->id, 'quantity' => 1],
            ['package_item_id' => $this->itemDrink->id, 'quantity' => 1, 'variant_id' => $this->variantDrinkLarge->id],
        ],
    ];

    $prepared1 = $this->handler->prepare($payload1);
    $prepared2 = $this->handler->prepare($payload2);

    expect($prepared1->signature)->toBe($prepared2->signature);
});

it('buildLine prices from live catalog and reflects later price changes', function (): void {
    $cartItem = CartItem::create([
        'cart_id' => $this->cart->id,
        'line_type' => 'package',
        'line_reference_id' => $this->package->id,
        'quantity' => 1,
        'selection_signature' => 'sig-test',
    ]);

    CartItemComponent::create([
        'cart_item_id' => $cartItem->id,
        'source_id' => $this->itemBurger->id,
        'product_id' => $this->productMain->id,
        'quantity' => 2,
        'unit_price_adjustment' => 0.0,
        'sort_order' => 1,
    ]);

    CartItemComponent::create([
        'cart_item_id' => $cartItem->id,
        'source_id' => $this->itemDrink->id,
        'product_id' => $this->productDrink->id,
        'variant_id' => $this->variantDrinkLarge->id,
        'quantity' => 1,
        'unit_price_adjustment' => 7.0,
        'sort_order' => 2,
    ]);

    // Initial build: package price 130 + drink upsize adjustment 7 = 137
    $line = $this->handler->buildLine($cartItem->fresh());
    expect($line->unitPrice)->toBe(137.0);

    // Now update the live catalog: increase package item price_adjustment from 2 to 6
    $this->itemDrink->update(['price_adjustment' => 6.0]);

    // Rebuild line: now drink adjustment is 6 + 5 = 11, unit price is 130 + 11 = 141
    $rebuiltLine = $this->handler->buildLine($cartItem->fresh());
    expect($rebuiltLine->unitPrice)->toBe(141.0);
});

it('buildLine on a soft-deleted package does not crash', function (): void {
    $cartItem = CartItem::create([
        'cart_id' => $this->cart->id,
        'line_type' => 'package',
        'line_reference_id' => $this->package->id,
        'quantity' => 1,
    ]);

    $this->package->delete(); // soft delete

    $line = $this->handler->buildLine($cartItem);

    expect($line)->not->toBeNull()
        ->and($line->productName)->toBe('Mega Combo')
        ->and($line->type)->toBe('package');
});

it('sets couponEligible according to allow_coupons', function (): void {
    $cartItem = CartItem::create([
        'cart_id' => $this->cart->id,
        'line_type' => 'package',
        'line_reference_id' => $this->package->id,
        'quantity' => 1,
    ]);

    $line1 = $this->handler->buildLine($cartItem);
    expect($line1->couponEligible)->toBeTrue();

    $this->package->update(['allow_coupons' => false]);
    $line2 = $this->handler->buildLine($cartItem);
    expect($line2->couponEligible)->toBeFalse();
});

it('unavailableReason returns null when package and selection are valid', function (): void {
    $cartItem = CartItem::create([
        'cart_id' => $this->cart->id,
        'line_type' => 'package',
        'line_reference_id' => $this->package->id,
        'quantity' => 1,
    ]);

    CartItemComponent::create([
        'cart_item_id' => $cartItem->id,
        'source_id' => $this->itemBurger->id,
        'product_id' => $this->productMain->id,
        'quantity' => 2,
        'unit_price_adjustment' => 0.0,
        'sort_order' => 0,
    ]);

    CartItemComponent::create([
        'cart_item_id' => $cartItem->id,
        'source_id' => $this->itemDrink->id,
        'product_id' => $this->productDrink->id,
        'variant_id' => $this->variantDrinkLarge->id,
        'quantity' => 1,
        'unit_price_adjustment' => 7.0,
        'sort_order' => 1,
    ]);

    CartItemComponent::create([
        'cart_item_id' => $cartItem->id,
        'source_id' => $this->itemFries->id,
        'product_id' => $this->productSide->id,
        'quantity' => 1,
        'unit_price_adjustment' => 3.0,
        'sort_order' => 2,
    ]);

    $reason = $this->handler->unavailableReason($cartItem->fresh());
    expect($reason)->toBeNull();
});

it('unavailableReason returns a message when package is expired or inactive', function (): void {
    $cartItem = CartItem::create([
        'cart_id' => $this->cart->id,
        'line_type' => 'package',
        'line_reference_id' => $this->package->id,
        'quantity' => 1,
    ]);

    $this->package->update(['valid_until' => now()->subDay()]);

    $reason = $this->handler->unavailableReason($cartItem);
    expect($reason)->not->toBeNull();
});

it('unavailableReason returns a message when a picked option product is deactivated', function (): void {
    $cartItem = CartItem::create([
        'cart_id' => $this->cart->id,
        'line_type' => 'package',
        'line_reference_id' => $this->package->id,
        'quantity' => 1,
    ]);

    CartItemComponent::create([
        'cart_item_id' => $cartItem->id,
        'source_id' => $this->itemBurger->id,
        'product_id' => $this->productMain->id,
        'quantity' => 2,
        'unit_price_adjustment' => 0.0,
        'sort_order' => 0,
    ]);

    CartItemComponent::create([
        'cart_item_id' => $cartItem->id,
        'source_id' => $this->itemDrink->id,
        'product_id' => $this->productDrink->id,
        'variant_id' => $this->variantDrinkLarge->id,
        'quantity' => 1,
        'unit_price_adjustment' => 7.0,
        'sort_order' => 1,
    ]);

    CartItemComponent::create([
        'cart_item_id' => $cartItem->id,
        'source_id' => $this->itemFries->id,
        'product_id' => $this->productSide->id,
        'quantity' => 1,
        'unit_price_adjustment' => 3.0,
        'sort_order' => 2,
    ]);

    // Deactivate side product
    $this->productSide->update(['is_active' => false]);

    $reason = $this->handler->unavailableReason($cartItem->fresh());
    expect($reason)->not->toBeNull();
});

it('unavailableReason returns a message when stored selection no longer satisfies min/max', function (): void {
    $cartItem = CartItem::create([
        'cart_id' => $this->cart->id,
        'line_type' => 'package',
        'line_reference_id' => $this->package->id,
        'quantity' => 1,
    ]);

    CartItemComponent::create([
        'cart_item_id' => $cartItem->id,
        'source_id' => $this->itemBurger->id,
        'product_id' => $this->productMain->id,
        'quantity' => 2,
        'unit_price_adjustment' => 0.0,
        'sort_order' => 0,
    ]);

    CartItemComponent::create([
        'cart_item_id' => $cartItem->id,
        'source_id' => $this->itemDrink->id,
        'product_id' => $this->productDrink->id,
        'variant_id' => $this->variantDrinkLarge->id,
        'quantity' => 1,
        'unit_price_adjustment' => 7.0,
        'sort_order' => 1,
    ]);

    // Sides omitted, but groupSides requires min 1
    $reason = $this->handler->unavailableReason($cartItem->fresh());
    expect($reason)->not->toBeNull();
});

it('adds extras total once per combo and multiplies by line quantity', function (): void {
    $extraOption = ExtraOption::create([
        'name' => 'Burger Extras',
        'is_active' => true,
        'min_selections' => 0,
        'max_selections' => 2,
        'allow_multiple' => true,
    ]);
    $extraCheese = ExtraOptionItem::create([
        'extra_option_id' => $extraOption->id,
        'name' => 'Cheese',
        'price' => 5.0,
        'allow_quantity' => true,
    ]);
    $this->productMain->update(['extra_option_id' => $extraOption->id]);
    $this->itemBurger->update(['allow_extras' => true]);

    $cartItem = CartItem::create([
        'cart_id' => $this->cart->id,
        'line_type' => 'package',
        'line_reference_id' => $this->package->id,
        'quantity' => 3,
    ]);

    CartItemComponent::create([
        'cart_item_id' => $cartItem->id,
        'source_id' => $this->itemBurger->id,
        'product_id' => $this->productMain->id,
        'quantity' => 2,
        'unit_price_adjustment' => 0.0,
        'sort_order' => 0,
        'extras' => [['id' => $extraCheese->id, 'quantity' => 1]],
        'notes' => 'Extra crispy',
    ]);

    CartItemComponent::create([
        'cart_item_id' => $cartItem->id,
        'source_id' => $this->itemDrink->id,
        'product_id' => $this->productDrink->id,
        'variant_id' => $this->variantDrinkLarge->id,
        'quantity' => 1,
        'unit_price_adjustment' => 7.0,
        'sort_order' => 1,
    ]);

    CartItemComponent::create([
        'cart_item_id' => $cartItem->id,
        'source_id' => $this->itemFries->id,
        'product_id' => $this->productSide->id,
        'quantity' => 1,
        'unit_price_adjustment' => 3.0,
        'sort_order' => 2,
    ]);

    $line = $this->handler->buildLine($cartItem->fresh());
    // Package price 130 + drink upsize 7 + fries upsize 3 + burger extra 5 = 145 per combo
    expect($line->unitPrice)->toBe(145.0)
        // 3 combos * 145 = 435 subtotal
        ->and($line->subtotal)->toBe(435.0);
});

it('produces different signatures for different extras or notes and identical signatures merge', function (): void {
    $extraOption = ExtraOption::create([
        'name' => 'Burger Extras',
        'is_active' => true,
        'min_selections' => 0,
        'max_selections' => 2,
        'allow_multiple' => true,
    ]);
    $extraCheese = ExtraOptionItem::create([
        'extra_option_id' => $extraOption->id,
        'name' => 'Cheese',
        'price' => 5.0,
        'allow_quantity' => true,
    ]);
    $extraPickle = ExtraOptionItem::create([
        'extra_option_id' => $extraOption->id,
        'name' => 'Pickle',
        'price' => 2.0,
        'allow_quantity' => true,
    ]);
    $this->productMain->update(['extra_option_id' => $extraOption->id]);
    $this->itemBurger->update(['allow_extras' => true]);

    $payloadBase = [
        'package_id' => $this->package->id,
        'selections' => [
            ['package_item_id' => $this->itemDrink->id, 'quantity' => 1, 'variant_id' => $this->variantDrinkLarge->id],
            ['package_item_id' => $this->itemFries->id, 'quantity' => 1],
        ],
    ];

    $payloadNoteA = array_merge_recursive($payloadBase, [
        'selections' => [
            ['package_item_id' => $this->itemBurger->id, 'notes' => 'No pickles'],
        ],
    ]);
    $payloadNoteB = array_merge_recursive($payloadBase, [
        'selections' => [
            ['package_item_id' => $this->itemBurger->id, 'notes' => 'Extra onions'],
        ],
    ]);

    $prepA = $this->handler->prepare($payloadNoteA);
    $prepB = $this->handler->prepare($payloadNoteB);
    expect($prepA->signature)->not->toBe($prepB->signature);

    $payloadExtraA = array_merge_recursive($payloadBase, [
        'selections' => [
            ['package_item_id' => $this->itemBurger->id, 'extras' => [['id' => $extraCheese->id, 'quantity' => 1]]],
        ],
    ]);
    $payloadExtraB = array_merge_recursive($payloadBase, [
        'selections' => [
            ['package_item_id' => $this->itemBurger->id, 'extras' => [['id' => $extraPickle->id, 'quantity' => 1]]],
        ],
    ]);
    $prepExtraA = $this->handler->prepare($payloadExtraA);
    $prepExtraB = $this->handler->prepare($payloadExtraB);
    expect($prepExtraA->signature)->not->toBe($prepExtraB->signature);

    // Identical selections produce same signature
    $prepExtraA2 = $this->handler->prepare($payloadExtraA);
    expect($prepExtraA->signature)->toBe($prepExtraA2->signature);
});

it('reprices extras when an extra price changes in live catalog', function (): void {
    $extraOption = ExtraOption::create([
        'name' => 'Burger Extras',
        'is_active' => true,
        'min_selections' => 0,
        'max_selections' => 2,
        'allow_multiple' => true,
    ]);
    $extraCheese = ExtraOptionItem::create([
        'extra_option_id' => $extraOption->id,
        'name' => 'Cheese',
        'price' => 5.0,
        'allow_quantity' => true,
    ]);
    $this->productMain->update(['extra_option_id' => $extraOption->id]);
    $this->itemBurger->update(['allow_extras' => true]);

    $cartItem = CartItem::create([
        'cart_id' => $this->cart->id,
        'line_type' => 'package',
        'line_reference_id' => $this->package->id,
        'quantity' => 1,
    ]);

    CartItemComponent::create([
        'cart_item_id' => $cartItem->id,
        'source_id' => $this->itemBurger->id,
        'product_id' => $this->productMain->id,
        'quantity' => 2,
        'unit_price_adjustment' => 0.0,
        'sort_order' => 0,
        'extras' => [['id' => $extraCheese->id, 'quantity' => 1]],
    ]);

    CartItemComponent::create([
        'cart_item_id' => $cartItem->id,
        'source_id' => $this->itemDrink->id,
        'product_id' => $this->productDrink->id,
        'variant_id' => $this->variantDrinkLarge->id,
        'quantity' => 1,
        'unit_price_adjustment' => 7.0,
        'sort_order' => 1,
    ]);

    CartItemComponent::create([
        'cart_item_id' => $cartItem->id,
        'source_id' => $this->itemFries->id,
        'product_id' => $this->productSide->id,
        'quantity' => 1,
        'unit_price_adjustment' => 3.0,
        'sort_order' => 2,
    ]);

    $lineInitial = $this->handler->buildLine($cartItem->fresh());
    expect($lineInitial->unitPrice)->toBe(145.0);

    // Live catalog update: Cheese becomes 9.0
    $extraCheese->update(['price' => 9.0]);

    $lineUpdated = $this->handler->buildLine($cartItem->fresh());
    expect($lineUpdated->unitPrice)->toBe(149.0);
});

it('unavailableReason flags when an extra is removed from the product option', function (): void {
    $extraOption = ExtraOption::create([
        'name' => 'Burger Extras',
        'is_active' => true,
        'min_selections' => 0,
        'max_selections' => 2,
        'allow_multiple' => true,
    ]);
    $extraCheese = ExtraOptionItem::create([
        'extra_option_id' => $extraOption->id,
        'name' => 'Cheese',
        'price' => 5.0,
        'allow_quantity' => true,
    ]);
    $this->productMain->update(['extra_option_id' => $extraOption->id]);
    $this->itemBurger->update(['allow_extras' => true]);

    $cartItem = CartItem::create([
        'cart_id' => $this->cart->id,
        'line_type' => 'package',
        'line_reference_id' => $this->package->id,
        'quantity' => 1,
    ]);

    CartItemComponent::create([
        'cart_item_id' => $cartItem->id,
        'source_id' => $this->itemBurger->id,
        'product_id' => $this->productMain->id,
        'quantity' => 2,
        'unit_price_adjustment' => 0.0,
        'sort_order' => 0,
        'extras' => [['id' => $extraCheese->id, 'quantity' => 1]],
    ]);

    CartItemComponent::create([
        'cart_item_id' => $cartItem->id,
        'source_id' => $this->itemDrink->id,
        'product_id' => $this->productDrink->id,
        'variant_id' => $this->variantDrinkLarge->id,
        'quantity' => 1,
        'unit_price_adjustment' => 7.0,
        'sort_order' => 1,
    ]);

    CartItemComponent::create([
        'cart_item_id' => $cartItem->id,
        'source_id' => $this->itemFries->id,
        'product_id' => $this->productSide->id,
        'quantity' => 1,
        'unit_price_adjustment' => 3.0,
        'sort_order' => 2,
    ]);

    expect($this->handler->unavailableReason($cartItem->fresh()))->toBeNull();

    // Now remove the extra item from the option
    $extraCheese->delete();

    $reason = $this->handler->unavailableReason($cartItem->fresh());
    expect($reason)->toBe('Some selected extras are no longer available.');
});

it('unavailableReason flags all drift when items added, removed, or quantity changed', function (): void {
    $cartItem = CartItem::create([
        'cart_id' => $this->cart->id,
        'line_type' => 'package',
        'line_reference_id' => $this->package->id,
        'quantity' => 1,
    ]);

    $compBurger = CartItemComponent::create([
        'cart_item_id' => $cartItem->id,
        'source_id' => $this->itemBurger->id,
        'product_id' => $this->productMain->id,
        'quantity' => 2,
        'unit_price_adjustment' => 0.0,
        'sort_order' => 0,
    ]);

    CartItemComponent::create([
        'cart_item_id' => $cartItem->id,
        'source_id' => $this->itemDrink->id,
        'product_id' => $this->productDrink->id,
        'variant_id' => $this->variantDrinkLarge->id,
        'quantity' => 1,
        'unit_price_adjustment' => 7.0,
        'sort_order' => 1,
    ]);

    CartItemComponent::create([
        'cart_item_id' => $cartItem->id,
        'source_id' => $this->itemFries->id,
        'product_id' => $this->productSide->id,
        'quantity' => 1,
        'unit_price_adjustment' => 3.0,
        'sort_order' => 2,
    ]);

    expect($this->handler->unavailableReason($cartItem->fresh()))->toBeNull();

    // Case 1: Quantity changed in 'all' group (from 2 to 3)
    $this->itemBurger->update(['quantity' => 3]);
    expect($this->handler->unavailableReason($cartItem->fresh()))->toBe('Package contents have changed, please re-add it.');
    $this->itemBurger->update(['quantity' => 2]);
    expect($this->handler->unavailableReason($cartItem->fresh()))->toBeNull();

    // Case 2: Item added to 'all' group
    $newItem = PackageItem::create([
        'package_group_id' => $this->groupAll->id,
        'product_id' => $this->productSide->id,
        'variant_mode' => 'fixed',
        'quantity' => 1,
        'price_adjustment' => 0.0,
        'sort_order' => 2,
    ]);
    expect($this->handler->unavailableReason($cartItem->fresh()))->toBe('Package contents have changed, please re-add it.');

    // Case 3: Item removed from 'all' group (the new item was part of cart but removed from package)
    $compExtra = CartItemComponent::create([
        'cart_item_id' => $cartItem->id,
        'source_id' => $newItem->id,
        'product_id' => $this->productSide->id,
        'quantity' => 1,
        'unit_price_adjustment' => 0.0,
        'sort_order' => 3,
    ]);
    $newItem->delete();
    expect($this->handler->unavailableReason($cartItem->fresh()))->not->toBeNull();
});
