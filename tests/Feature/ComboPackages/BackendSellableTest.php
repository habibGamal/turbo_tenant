<?php

declare(strict_types=1);

namespace Tests\Feature\ComboPackages;

use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use App\Plugins\ComboPackages\src\Models\Package;
use App\Plugins\ComboPackages\src\Models\PackageGroup;
use App\Plugins\ComboPackages\src\Models\PackageItem;

beforeEach(function (): void {
    $this->category = Category::factory()->create();
    $this->product = Product::factory()->create([
        'category_id' => $this->category->id,
        'name' => 'Burger',
        'name_ar' => 'برجر',
        'image' => 'products/burger.jpg',
        'base_price' => 50.00,
        'is_active' => true,
    ]);
});

function createCompletePackage(array $packageAttributes = [], string $selectionType = 'all', int $itemCount = 1): Package
{
    $package = Package::factory()->create(array_merge([
        'name' => 'Complete Combo',
        'name_ar' => 'باقة كاملة',
        'price' => 100.00,
        'original_price' => 150.00,
        'discount_percentage' => 33,
        'is_active' => true,
        'sort_order' => 1,
    ], $packageAttributes));

    $group = PackageGroup::create([
        'package_id' => $package->id,
        'name' => 'Group 1',
        'name_ar' => 'المجموعة 1',
        'selection_type' => $selectionType,
        'min_selections' => $selectionType === 'choose_multiple' ? 1 : null,
        'max_selections' => $selectionType === 'choose_multiple' ? $itemCount : null,
        'sort_order' => 1,
    ]);

    for ($i = 0; $i < $itemCount; $i++) {
        $product = Product::factory()->create([
            'is_active' => true,
            'name' => "Product {$i}",
            'name_ar' => "منتج {$i}",
        ]);

        PackageItem::create([
            'package_group_id' => $group->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price_adjustment' => 0.00,
            'is_default' => true,
            'sort_order' => $i + 1,
        ]);
    }

    return $package;
}

it('enforces the sellable matrix: inactive, future, past, null dates, inside window', function (): void {
    // 1. Inactive package
    $inactive = createCompletePackage(['name' => 'Inactive Combo', 'is_active' => false]);

    // 2. Future package (valid_from in future)
    $future = createCompletePackage([
        'name' => 'Future Combo',
        'is_active' => true,
        'valid_from' => now()->addDays(5),
        'valid_until' => now()->addDays(10),
    ]);

    // 3. Past package (valid_until in past)
    $past = createCompletePackage([
        'name' => 'Past Combo',
        'is_active' => true,
        'valid_from' => now()->subDays(10),
        'valid_until' => now()->subDays(2),
    ]);

    // 4. Both dates null (always sellable)
    $bothNull = createCompletePackage([
        'name' => 'Always Active Combo',
        'is_active' => true,
        'valid_from' => null,
        'valid_until' => null,
    ]);

    // 5. Inside window
    $insideWindow = createCompletePackage([
        'name' => 'Current Window Combo',
        'is_active' => true,
        'valid_from' => now()->subDays(2),
        'valid_until' => now()->addDays(5),
    ]);

    $response = $this->getJson('/api/packages');
    $response->assertOk();

    $names = collect($response->json('data'))->pluck('name');
    expect($names)->toContain('Always Active Combo')
        ->and($names)->toContain('Current Window Combo')
        ->and($names)->not->toContain('Inactive Combo')
        ->and($names)->not->toContain('Future Combo')
        ->and($names)->not->toContain('Past Combo');
});

it('returns 404 for inactive, expired, future or unfulfillable packages in show() and 200 for sellable ones', function (): void {
    $inactive = createCompletePackage(['is_active' => false]);
    $this->getJson("/api/packages/{$inactive->id}")->assertNotFound();

    $expired = createCompletePackage([
        'is_active' => true,
        'valid_from' => now()->subDays(10),
        'valid_until' => now()->subDays(1),
    ]);
    $this->getJson("/api/packages/{$expired->id}")->assertNotFound();

    $future = createCompletePackage([
        'is_active' => true,
        'valid_from' => now()->addDays(2),
        'valid_until' => now()->addDays(10),
    ]);
    $this->getJson("/api/packages/{$future->id}")->assertNotFound();

    $unfulfillable = Package::factory()->create(['is_active' => true]);
    $this->getJson("/api/packages/{$unfulfillable->id}")->assertNotFound();

    $sellable = createCompletePackage([
        'is_active' => true,
        'valid_from' => now()->subHour(),
        'valid_until' => now()->addHour(),
    ]);
    $this->getJson("/api/packages/{$sellable->id}")->assertOk();
});

it('removes unavailable items and hides unfulfillable packages according to selection type rules', function (): void {
    // 1. Inactive product in an 'all' group hides the package
    $pkgAll = Package::factory()->create(['is_active' => true, 'name' => 'All Group Combo']);
    $groupAll = PackageGroup::create([
        'package_id' => $pkgAll->id,
        'name' => 'All Items',
        'selection_type' => 'all',
    ]);
    $activeProd = Product::factory()->create(['is_active' => true]);
    $inactiveProd = Product::factory()->create(['is_active' => false]);
    PackageItem::create([
        'package_group_id' => $groupAll->id,
        'product_id' => $activeProd->id,
        'quantity' => 1,
    ]);
    PackageItem::create([
        'package_group_id' => $groupAll->id,
        'product_id' => $inactiveProd->id,
        'quantity' => 1,
    ]);

    $this->getJson('/api/packages')->assertJsonMissing(['name' => 'All Group Combo']);
    $this->getJson("/api/packages/{$pkgAll->id}")->assertNotFound();

    // 2. Unavailable variant in an 'all' group hides the package
    $pkgVariant = Package::factory()->create(['is_active' => true, 'name' => 'Variant Combo']);
    $groupVariant = PackageGroup::create([
        'package_id' => $pkgVariant->id,
        'name' => 'Variant Items',
        'selection_type' => 'all',
    ]);
    $variantProd = Product::factory()->create(['is_active' => true]);
    $unavailVariant = ProductVariant::create([
        'product_id' => $variantProd->id,
        'name' => 'Out of stock size',
        'price' => 10.00,
        'is_available' => false,
    ]);
    PackageItem::create([
        'package_group_id' => $groupVariant->id,
        'product_id' => $variantProd->id,
        'variant_id' => $unavailVariant->id,
        'quantity' => 1,
    ]);

    $this->getJson('/api/packages')->assertJsonMissing(['name' => 'Variant Combo']);
    $this->getJson("/api/packages/{$pkgVariant->id}")->assertNotFound();

    // 3. choose_multiple with min 2 and only 1 available item hides the package
    $pkgChooseMult = Package::factory()->create(['is_active' => true, 'name' => 'Choose Multiple Combo']);
    $groupMult = PackageGroup::create([
        'package_id' => $pkgChooseMult->id,
        'name' => 'Pick 2',
        'selection_type' => 'choose_multiple',
        'min_selections' => 2,
        'max_selections' => 2,
    ]);
    $prod1 = Product::factory()->create(['is_active' => true]);
    $prod2 = Product::factory()->create(['is_active' => false]);
    PackageItem::create(['package_group_id' => $groupMult->id, 'product_id' => $prod1->id, 'quantity' => 1]);
    PackageItem::create(['package_group_id' => $groupMult->id, 'product_id' => $prod2->id, 'quantity' => 1]);

    $this->getJson('/api/packages')->assertJsonMissing(['name' => 'Choose Multiple Combo']);
    $this->getJson("/api/packages/{$pkgChooseMult->id}")->assertNotFound();

    // 4. choose_one with 0 available items hides the package
    $pkgChooseOne = Package::factory()->create(['is_active' => true, 'name' => 'Choose One Combo']);
    $groupOne = PackageGroup::create([
        'package_id' => $pkgChooseOne->id,
        'name' => 'Pick 1',
        'selection_type' => 'choose_one',
    ]);
    $prodInactive = Product::factory()->create(['is_active' => false]);
    PackageItem::create(['package_group_id' => $groupOne->id, 'product_id' => $prodInactive->id, 'quantity' => 1]);

    $this->getJson('/api/packages')->assertJsonMissing(['name' => 'Choose One Combo']);
    $this->getJson("/api/packages/{$pkgChooseOne->id}")->assertNotFound();

    // 5. choose_multiple with min 1 and 2 items where 1 is inactive: package remains and only active item returned
    $pkgPartial = Package::factory()->create(['is_active' => true, 'name' => 'Partial Combo']);
    $groupPartial = PackageGroup::create([
        'package_id' => $pkgPartial->id,
        'name' => 'Pick 1 or 2',
        'selection_type' => 'choose_multiple',
        'min_selections' => 1,
        'max_selections' => 2,
    ]);
    $prodActive1 = Product::factory()->create(['is_active' => true, 'name' => 'Available Side']);
    $prodInactive2 = Product::factory()->create(['is_active' => false, 'name' => 'Unavailable Side']);
    PackageItem::create(['package_group_id' => $groupPartial->id, 'product_id' => $prodActive1->id, 'quantity' => 1]);
    PackageItem::create(['package_group_id' => $groupPartial->id, 'product_id' => $prodInactive2->id, 'quantity' => 1]);

    $response = $this->getJson("/api/packages/{$pkgPartial->id}");
    $response->assertOk();
    $items = $response->json('data.groups.0.items');
    expect($items)->toHaveCount(1)
        ->and($items[0]['product']['name'])->toBe('Available Side');
});

it('matches the exact JSON contract shape and normalizes min/max and empty dates', function (): void {
    $package = Package::factory()->create([
        'name' => 'Super Saver Box',
        'name_ar' => 'صندوق التوفير الخارق',
        'description' => 'A great combo box',
        'description_ar' => 'صندوق توفير رائع',
        'image' => 'packages/super-saver.jpg',
        'price' => 80.00,
        'original_price' => 120.00,
        'discount_percentage' => 33,
        'badge' => 'Value',
        'badge_ar' => 'توفير',
        'icon' => 'gift',
        'gradient' => 'from-orange-500 to-red-500',
        'is_active' => true,
        'is_featured' => true,
        'sort_order' => 1,
        'valid_from' => null,
        'valid_until' => null,
    ]);

    // Group 1: 'all'
    $groupAll = PackageGroup::create([
        'package_id' => $package->id,
        'name' => 'Mandatory Main',
        'name_ar' => 'الرئيسي الإجباري',
        'selection_type' => 'all',
        'sort_order' => 1,
    ]);

    PackageItem::create([
        'package_group_id' => $groupAll->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
        'price_adjustment' => 0.00,
        'is_default' => true,
        'sort_order' => 1,
    ]);

    // Group 2: 'choose_one' with variant
    $groupOne = PackageGroup::create([
        'package_id' => $package->id,
        'name' => 'Choose Drink',
        'name_ar' => 'اختر مشروب',
        'selection_type' => 'choose_one',
        'sort_order' => 2,
    ]);

    $drink = Product::factory()->create([
        'name' => 'Soda',
        'name_ar' => 'مشروب غازي',
        'image' => 'products/soda.png',
        'is_active' => true,
    ]);

    $drinkVariant = ProductVariant::create([
        'product_id' => $drink->id,
        'name' => 'Large 500ml',
        'name_ar' => 'كبير 500 مل',
        'price' => 15.00,
        'is_available' => true,
    ]);

    PackageItem::create([
        'package_group_id' => $groupOne->id,
        'product_id' => $drink->id,
        'variant_id' => $drinkVariant->id,
        'quantity' => 1,
        'price_adjustment' => 5.00,
        'is_default' => true,
        'sort_order' => 1,
    ]);

    // Group 3: 'choose_multiple'
    $groupMult = PackageGroup::create([
        'package_id' => $package->id,
        'name' => 'Choose Sides',
        'name_ar' => 'اختر أطباق جانبية',
        'selection_type' => 'choose_multiple',
        'min_selections' => 1,
        'max_selections' => 3,
        'sort_order' => 3,
    ]);

    $sideProduct = Product::factory()->create([
        'name' => 'Fries',
        'name_ar' => 'بطاطس',
        'image' => 'products/fries.png',
        'is_active' => true,
    ]);

    PackageItem::create([
        'package_group_id' => $groupMult->id,
        'product_id' => $sideProduct->id,
        'quantity' => 1,
        'price_adjustment' => 0.00,
        'is_default' => true,
        'sort_order' => 1,
    ]);

    $response = $this->getJson("/api/packages/{$package->id}");
    $response->assertOk();

    // Assert exact JSON structure matching <api_contract>
    $response->assertJsonStructure([
        'status',
        'data' => [
            'id',
            'name',
            'name_ar',
            'description',
            'description_ar',
            'image',
            'price',
            'original_price',
            'discount_percentage',
            'savings_amount',
            'badge',
            'badge_ar',
            'icon',
            'gradient',
            'is_active',
            'is_featured',
            'sort_order',
            'valid_from',
            'valid_until',
            'groups' => [
                '*' => [
                    'id',
                    'package_id',
                    'name',
                    'name_ar',
                    'selection_type',
                    'min_selections',
                    'max_selections',
                    'sort_order',
                    'items' => [
                        '*' => [
                            'id',
                            'package_group_id',
                            'product_id',
                            'variant_id',
                            'quantity',
                            'price_adjustment',
                            'is_default',
                            'sort_order',
                            'product' => [
                                'id',
                                'name',
                                'name_ar',
                                'image',
                            ],
                            'variant',
                        ],
                    ],
                ],
            ],
        ],
    ]);

    $data = $response->json('data');

    // Values verification
    expect($data['id'])->toBe($package->id)
        ->and($data['image'])->toBe('packages/super-saver.jpg')
        ->and($data['savings_amount'])->toEqual(40.0) // 120 - 80
        ->and($data['discount_percentage'])->toBe(33)
        ->and($data['valid_from'])->toBeNull()
        ->and($data['valid_until'])->toBeNull();

    // Group 1: 'all' => min_selections null, max_selections null
    $g1 = $data['groups'][0];
    expect($g1['selection_type'])->toBe('all')
        ->and($g1['min_selections'])->toBeNull()
        ->and($g1['max_selections'])->toBeNull()
        ->and($g1['items'][0]['variant'])->toBeNull();

    // Group 2: 'choose_one' => min_selections 1, max_selections 1
    $g2 = $data['groups'][1];
    expect($g2['selection_type'])->toBe('choose_one')
        ->and($g2['min_selections'])->toBe(1)
        ->and($g2['max_selections'])->toBe(1)
        ->and($g2['items'][0]['variant'])->toEqual([
            'id' => $drinkVariant->id,
            'name' => 'Large 500ml',
            'name_ar' => 'كبير 500 مل',
            'price' => 15.0,
        ]);

    // Group 3: 'choose_multiple' => min_selections 1, max_selections 3
    $g3 = $data['groups'][2];
    expect($g3['selection_type'])->toBe('choose_multiple')
        ->and($g3['min_selections'])->toBe(1)
        ->and($g3['max_selections'])->toBe(3);
});
