<?php

declare(strict_types=1);

namespace Tests\Feature\ComboPackages;

use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\ComboPackages\src\Catalog\ProductComboUpsellContributor;
use App\Plugins\ComboPackages\src\Models\Package;
use App\Plugins\ComboPackages\src\Models\PackageGroup;
use App\Plugins\ComboPackages\src\Models\PackageItem;

beforeEach(function (): void {
    $this->category = Category::factory()->create();

    $this->product = Product::factory()->create([
        'category_id' => $this->category->id,
        'name' => 'Signature Burger',
        'name_ar' => 'برجر مميز',
        'base_price' => 120.0,
        'is_active' => true,
    ]);

    $this->contributor = app(ProductComboUpsellContributor::class);
});

function createPackageWithProduct(
    Product $product,
    array $packageAttributes = [],
    array $groupAttributes = [],
    array $itemAttributes = []
): Package {
    $package = Package::factory()->create(array_merge([
        'name' => 'Meal Package',
        'name_ar' => 'باقة وجبة',
        'price' => 150.0,
        'original_price' => 180.0,
        'is_active' => true,
        'is_featured' => false,
        'sort_order' => 0,
    ], $packageAttributes));

    $group = PackageGroup::create(array_merge([
        'package_id' => $package->id,
        'name' => 'Main Items',
        'name_ar' => 'الأطباق الرئيسية',
        'selection_type' => 'all',
        'sort_order' => 0,
    ], $groupAttributes));

    PackageItem::create(array_merge([
        'package_group_id' => $group->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'sort_order' => 0,
    ], $itemAttributes));

    return $package;
}

it('returns packages containing the specified product', function (): void {
    $matchingPackage = createPackageWithProduct($this->product, ['name' => 'Burger Combo']);

    $otherProduct = Product::factory()->create([
        'category_id' => $this->category->id,
        'name' => 'Pizza',
        'is_active' => true,
    ]);
    $nonMatchingPackage = createPackageWithProduct($otherProduct, ['name' => 'Pizza Combo']);

    $result = $this->contributor->contribute($this->product);

    expect($result)->toHaveKey('combo_packages')
        ->and($result['combo_packages'])->toHaveCount(1)
        ->and($result['combo_packages'][0]['id'])->toBe($matchingPackage->id)
        ->and($result['combo_packages'][0]['name'])->toBe('Burger Combo');
});

it('excludes inactive or expired packages and packages that cannot be fulfilled', function (): void {
    // 1. Inactive package
    createPackageWithProduct($this->product, [
        'name' => 'Inactive Package',
        'is_active' => false,
    ]);

    // 2. Expired package
    createPackageWithProduct($this->product, [
        'name' => 'Expired Package',
        'valid_until' => now()->subDay(),
    ]);

    // 3. Future scheduled package
    createPackageWithProduct($this->product, [
        'name' => 'Future Package',
        'valid_from' => now()->addDay(),
    ]);

    // 4. Unfulfillable package: selection_type 'all' with an inactive required item
    $unfulfillablePkg = Package::factory()->create([
        'name' => 'Unfulfillable Package',
        'is_active' => true,
    ]);
    $group = PackageGroup::create([
        'package_id' => $unfulfillablePkg->id,
        'name' => 'Must Have All',
        'selection_type' => 'all',
        'sort_order' => 0,
    ]);
    // The target product
    PackageItem::create([
        'package_group_id' => $group->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
    ]);
    // An inactive required product
    $inactiveProduct = Product::factory()->create([
        'category_id' => $this->category->id,
        'is_active' => false,
    ]);
    PackageItem::create([
        'package_group_id' => $group->id,
        'product_id' => $inactiveProduct->id,
        'quantity' => 1,
    ]);

    // 5. Valid sellable package
    $validPkg = createPackageWithProduct($this->product, ['name' => 'Valid Package']);

    $result = $this->contributor->contribute($this->product);

    expect($result['combo_packages'])->toHaveCount(1)
        ->and($result['combo_packages'][0]['id'])->toBe($validPkg->id);
});

it('returns at most 3 packages with featured packages first then sort_order', function (): void {
    // Package 1: not featured, sort_order 10
    $p1 = createPackageWithProduct($this->product, [
        'name' => 'Normal Late',
        'is_featured' => false,
        'sort_order' => 10,
    ]);

    // Package 2: featured, sort_order 5
    $p2 = createPackageWithProduct($this->product, [
        'name' => 'Featured Second',
        'is_featured' => true,
        'sort_order' => 5,
    ]);

    // Package 3: not featured, sort_order 1
    $p3 = createPackageWithProduct($this->product, [
        'name' => 'Normal Early',
        'is_featured' => false,
        'sort_order' => 1,
    ]);

    // Package 4: featured, sort_order 2
    $p4 = createPackageWithProduct($this->product, [
        'name' => 'Featured First',
        'is_featured' => true,
        'sort_order' => 2,
    ]);

    // Package 5: not featured, sort_order 20
    $p5 = createPackageWithProduct($this->product, [
        'name' => 'Normal Very Late',
        'is_featured' => false,
        'sort_order' => 20,
    ]);

    $result = $this->contributor->contribute($this->product);

    // Expected order:
    // Featured first: p4 (sort_order 2), p2 (sort_order 5)
    // Non-featured: p3 (sort_order 1)
    // p1 and p5 truncated because max 3
    expect($result['combo_packages'])->toHaveCount(3)
        ->and($result['combo_packages'][0]['id'])->toBe($p4->id)
        ->and($result['combo_packages'][1]['id'])->toBe($p2->id)
        ->and($result['combo_packages'][2]['id'])->toBe($p3->id);
});

it('includes combo_packages in product details JSON endpoint when contributor is tagged', function (): void {
    app()->singleton(ProductComboUpsellContributor::class);
    app()->tag([ProductComboUpsellContributor::class], 'catalog.product.details');

    $pkg = createPackageWithProduct($this->product, [
        'name' => 'Deluxe Meal',
        'name_ar' => 'وجبة ديلوكس',
        'price' => 140.0,
        'original_price' => 170.0,
        'image' => 'https://example.com/deluxe.png',
    ]);

    $response = $this->getJson("/api/catalog-products/{$this->product->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $this->product->id)
        ->assertJsonPath('data.combo_packages.0.id', $pkg->id)
        ->assertJsonPath('data.combo_packages.0.name', 'Deluxe Meal')
        ->assertJsonPath('data.combo_packages.0.name_ar', 'وجبة ديلوكس')
        ->assertJsonPath('data.combo_packages.0.price', fn ($price) => (float) $price === 140.0)
        ->assertJsonPath('data.combo_packages.0.original_price', fn ($price) => (float) $price === 170.0)
        ->assertJsonPath('data.combo_packages.0.image', 'https://example.com/deluxe.png');
});
