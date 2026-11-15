<?php

declare(strict_types=1);

namespace Tests\Feature\ComboPackages;

use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use App\Plugins\ComboPackages\src\Models\Package;
use App\Plugins\ComboPackages\src\Models\PackageGroup;
use App\Plugins\ComboPackages\src\Models\PackageItem;
use App\Plugins\ComboPackages\src\Services\PackageLinePricer;

beforeEach(function (): void {
    $this->category = Category::factory()->create();

    $this->product = Product::factory()->create([
        'category_id' => $this->category->id,
        'base_price' => 50.0,
        'price_after_discount' => null,
        'is_active' => true,
    ]);

    $this->productDiscounted = Product::factory()->create([
        'category_id' => $this->category->id,
        'base_price' => 60.0,
        'price_after_discount' => 45.0,
        'is_active' => true,
    ]);

    $this->variantUpsize = ProductVariant::create([
        'product_id' => $this->product->id,
        'name' => 'Large',
        'price' => 65.0,
        'is_available' => true,
    ]);

    $this->variantCheaper = ProductVariant::create([
        'product_id' => $this->product->id,
        'name' => 'Small',
        'price' => 40.0,
        'is_available' => true,
    ]);

    $this->variantDiscountedUpsize = ProductVariant::create([
        'product_id' => $this->productDiscounted->id,
        'name' => 'Upsize',
        'price' => 55.0,
        'is_available' => true,
    ]);

    $this->package = Package::factory()->create([
        'price' => 120.0,
    ]);

    $this->groupChoose = PackageGroup::create([
        'package_id' => $this->package->id,
        'selection_type' => 'choose_one',
    ]);

    $this->groupAll = PackageGroup::create([
        'package_id' => $this->package->id,
        'selection_type' => 'all',
    ]);

    $this->pricer = new PackageLinePricer();
});

it('uses price_after_discount as base effective price when greater than 0, else base_price', function (): void {
    expect($this->pricer->productBaseEffectivePrice($this->product))->toBe(50.0)
        ->and($this->pricer->productBaseEffectivePrice($this->productDiscounted))->toBe(45.0);
});

it('computes positive variant upsize delta correctly', function (): void {
    // 65 - 50 = 15
    $delta = $this->pricer->calculateVariantPriceDelta($this->product, $this->variantUpsize);
    expect($delta)->toBe(15.0);

    // 55 - 45 = 10 (relative to price_after_discount)
    $deltaDiscounted = $this->pricer->calculateVariantPriceDelta($this->productDiscounted, $this->variantDiscountedUpsize);
    expect($deltaDiscounted)->toBe(10.0);
});

it('does not allow negative variant deltas', function (): void {
    // 40 - 50 = -10 => should be 0.0
    $delta = $this->pricer->calculateVariantPriceDelta($this->product, $this->variantCheaper);
    expect($delta)->toBe(0.0);
});

it('calculates component adjustments multiplied by selection quantity', function (): void {
    $item = PackageItem::create([
        'package_group_id' => $this->groupChoose->id,
        'product_id' => $this->product->id,
        'variant_mode' => 'customer_choice',
        'price_adjustment' => 5.0,
        'quantity' => 1,
    ]);
    $item->load(['group', 'product']);

    // single adjustment = price_adjustment (5) + variant_delta (15) = 20
    // for 3 units: 20 * 3 = 60
    $adj = $this->pricer->calculateComponentAdjustment($item, $this->variantUpsize, 3);
    expect($adj)->toBe(60.0);
});

it('keeps all items price adjustment unaffected by variants', function (): void {
    $item = PackageItem::create([
        'package_group_id' => $this->groupAll->id,
        'product_id' => $this->product->id,
        'variant_mode' => 'fixed',
        'price_adjustment' => 10.0,
        'quantity' => 1,
    ]);
    $item->load(['group', 'product']);

    $adj = $this->pricer->calculateComponentAdjustment($item, null, 1);
    expect($adj)->toBe(10.0);
});

it('calculates combo unit price correctly', function (): void {
    $components = [
        (object) ['unitPriceAdjustment' => 15.0],
        (object) ['unitPriceAdjustment' => 5.0],
    ];

    // package price 120 + 15 + 5 = 140
    $unitPrice = $this->pricer->calculateComboUnitPrice($this->package, $components);
    expect($unitPrice)->toBe(140.0);
});
