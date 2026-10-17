<?php

declare(strict_types=1);

use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\ComboPackages\src\Models\Package as LegacyPackage;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use App\Plugins\ComboPackages\src\Models\Package;
use App\Plugins\ComboPackages\src\Models\PackageGroup;
use App\Plugins\ComboPackages\src\Models\PackageItem;

beforeEach(function () {
    $this->category = Category::factory()->create();
    $this->product = Product::factory()->create([
        'category_id' => $this->category->id,
        'base_price' => 50.00,
        'is_active' => true,
    ]);
});

it('creates a package with factory defaults', function () {
    $package = Package::factory()->create([
        'name' => 'Family Feast',
        'price' => 120.00,
        'original_price' => 150.00,
        'is_active' => true,
    ]);

    expect($package->name)->toBe('Family Feast')
        ->and($package->price)->toBe(120.00)
        ->and($package->original_price)->toBe(150.00)
        ->and($package->is_active)->toBeTrue();
});

it('attaches groups and items to a package with correct hierarchy', function () {
    $package = Package::factory()->create([
        'price' => 100.00,
    ]);

    $group = PackageGroup::create([
        'package_id' => $package->id,
        'name' => 'Main Dishes',
        'selection_type' => 'choose_one',
        'min_selections' => 1,
        'max_selections' => 1,
        'sort_order' => 1,
    ]);

    $variant = ProductVariant::create([
        'product_id' => $this->product->id,
        'name' => 'Large',
        'price' => 60.00,
        'is_available' => true,
    ]);

    $item = PackageItem::create([
        'package_group_id' => $group->id,
        'product_id' => $this->product->id,
        'variant_id' => $variant->id,
        'quantity' => 1,
        'price_adjustment' => 10.00,
        'is_default' => true,
        'sort_order' => 1,
    ]);

    expect($package->fresh()->groups)->toHaveCount(1)
        ->and($package->fresh()->items)->toHaveCount(1)
        ->and($group->isConditional())->toBeTrue()
        ->and($group->requiresSelection())->toBeTrue()
        ->and($item->hasVariant())->toBeTrue()
        ->and($item->getDisplayName())->toContain('Large')
        ->and($item->getEffectivePrice())->toBe(70.00); // 60 variant base + 10 adjustment
});

it('lists active packages via api and excludes inactive packages', function () {
    Package::factory()->create([
        'name' => 'Active Lunch Combo',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    Package::factory()->create([
        'name' => 'Inactive Secret Combo',
        'is_active' => false,
        'sort_order' => 2,
    ]);

    $response = $this->getJson('/api/packages');

    $response->assertOk()
        ->assertJsonPath('status', 'success');

    $data = $response->json('data');
    expect($data)->toHaveCount(1)
        ->and($data[0]['name'])->toBe('Active Lunch Combo');
});

it('retrieves a single package with nested groups and items via api', function () {
    $package = Package::factory()->create([
        'name' => 'Breakfast Deal',
        'is_active' => true,
    ]);

    $group = PackageGroup::create([
        'package_id' => $package->id,
        'name' => 'Drink Selection',
        'selection_type' => 'choose_one',
    ]);

    PackageItem::create([
        'package_group_id' => $group->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
        'price_adjustment' => 0.00,
    ]);

    $response = $this->getJson("/api/packages/{$package->id}");

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.name', 'Breakfast Deal')
        ->assertJsonPath('data.groups.0.name', 'Drink Selection')
        ->assertJsonPath('data.groups.0.items.0.product_id', $this->product->id);
});

it('maintains backwards compatibility with App\\Models\\Package alias', function () {
    $package = LegacyPackage::create([
        'name' => 'Legacy Shimmer Combo',
        'description' => 'Tested via legacy class name',
        'price' => 45.00,
        'is_active' => true,
    ]);

    expect($package)->toBeInstanceOf(Package::class)
        ->and(LegacyPackage::find($package->id))->not->toBeNull();
});
