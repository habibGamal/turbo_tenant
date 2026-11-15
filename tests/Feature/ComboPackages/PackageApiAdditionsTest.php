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
use App\Plugins\ComboPackages\src\Services\SellablePackageFilter;

beforeEach(function (): void {
    $this->category = Category::factory()->create();

    $this->product = Product::factory()->create([
        'category_id' => $this->category->id,
        'name' => 'Burger',
        'name_ar' => 'برجر',
        'base_price' => 50.0,
        'price_after_discount' => 40.0,
        'is_active' => true,
    ]);

    $this->variantAvailable1 = ProductVariant::create([
        'product_id' => $this->product->id,
        'name' => 'Small',
        'name_ar' => 'صغير',
        'price' => 40.0, // delta = 40 - 40 = 0
        'is_available' => true,
    ]);

    $this->variantAvailable2 = ProductVariant::create([
        'product_id' => $this->product->id,
        'name' => 'Large',
        'name_ar' => 'كبير',
        'price' => 55.0, // delta = 55 - 40 = 15
        'is_available' => true,
    ]);

    $this->variantUnavailable = ProductVariant::create([
        'product_id' => $this->product->id,
        'name' => 'Unavailable Size',
        'name_ar' => 'غير متوفر',
        'price' => 70.0,
        'is_available' => false,
    ]);

    $this->package = Package::factory()->create([
        'name' => 'Burger Feast',
        'name_ar' => 'وليمة البرجر',
        'allow_coupons' => true,
        'is_active' => true,
    ]);

    $this->group = PackageGroup::create([
        'package_id' => $this->package->id,
        'name' => 'Main Dish',
        'name_ar' => 'الطبق الرئيسي',
        'description' => 'Select your burger size',
        'description_ar' => 'اختر حجم البرجر',
        'selection_type' => 'choose_one',
        'allow_repeats' => false,
        'min_selections' => 1,
        'max_selections' => 1,
    ]);

    $this->item = PackageItem::create([
        'package_group_id' => $this->group->id,
        'product_id' => $this->product->id,
        'variant_mode' => 'customer_choice',
        'quantity' => 1,
        'price_adjustment' => 0.0,
    ]);
});

it('returns new storefront API fields on package listing and show endpoints', function (): void {
    $response = $this->getJson("/api/packages/{$this->package->id}");

    $response->assertOk()
        ->assertJsonPath('data.allow_coupons', true)
        ->assertJsonPath('data.groups.0.allow_repeats', false)
        ->assertJsonPath('data.groups.0.description', 'Select your burger size')
        ->assertJsonPath('data.groups.0.description_ar', 'اختر حجم البرجر')
        ->assertJsonPath('data.groups.0.items.0.variant_mode', 'customer_choice');

    $variantOptions = $response->json('data.groups.0.items.0.variant_options');
    expect($variantOptions)->toHaveCount(2);

    // Assert only available variants are included
    $variantIds = collect($variantOptions)->pluck('id')->all();
    expect($variantIds)->toContain($this->variantAvailable1->id)
        ->and($variantIds)->toContain($this->variantAvailable2->id)
        ->and($variantIds)->not->toContain($this->variantUnavailable->id);

    // Assert price deltas
    $smallOpt = collect($variantOptions)->firstWhere('id', $this->variantAvailable1->id);
    $largeOpt = collect($variantOptions)->firstWhere('id', $this->variantAvailable2->id);

    expect($smallOpt['price_delta'])->toEqual(0.0)
        ->and($largeOpt['price_delta'])->toEqual(15.0);
});

it('returns empty variant_options for fixed items', function (): void {
    $this->item->update(['variant_mode' => 'fixed', 'variant_id' => $this->variantAvailable1->id]);

    $response = $this->getJson("/api/packages/{$this->package->id}");

    $response->assertOk()
        ->assertJsonPath('data.groups.0.items.0.variant_mode', 'fixed')
        ->assertJsonPath('data.groups.0.items.0.variant_options', []);
});

it('SellablePackageFilter marks customer_choice item with zero available variants as unavailable', function (): void {
    $filter = new SellablePackageFilter();

    // Currently package is sellable because 2 variants are available
    $filtered1 = $filter->filterOne($this->package->fresh());
    expect($filtered1)->not->toBeNull();

    // Now disable all available variants
    $this->variantAvailable1->update(['is_available' => false]);
    $this->variantAvailable2->update(['is_available' => false]);

    // Now customer_choice item has 0 available variants => item unavailable => choose_one group has 0 items => package null
    $filtered2 = $filter->filterOne($this->package->fresh());
    expect($filtered2)->toBeNull();
});

it('exposes allow_extras and extra_option in package api with extra_option null when allow_extras is false', function (): void {
    $extraOption = ExtraOption::create([
        'name' => 'Burger Extras',
        'is_active' => true,
        'min_selections' => 0,
        'max_selections' => 2,
        'allow_multiple' => true,
    ]);
    $extraItem = ExtraOptionItem::create([
        'extra_option_id' => $extraOption->id,
        'name' => 'Cheese Slice',
        'price' => 5.0,
        'allow_quantity' => true,
    ]);

    $this->product->update(['extra_option_id' => $extraOption->id]);

    // When allow_extras is false:
    $this->item->update(['allow_extras' => false]);
    $responseFalse = $this->getJson("/api/packages/{$this->package->id}");
    $responseFalse->assertOk()
        ->assertJsonPath('data.groups.0.items.0.allow_extras', false)
        ->assertJsonPath('data.groups.0.items.0.extra_option', null);

    // When allow_extras is true:
    $this->item->update(['allow_extras' => true]);
    $responseTrue = $this->getJson("/api/packages/{$this->package->id}");
    $responseTrue->assertOk()
        ->assertJsonPath('data.groups.0.items.0.allow_extras', true)
        ->assertJsonPath('data.groups.0.items.0.extra_option.id', $extraOption->id)
        ->assertJsonPath('data.groups.0.items.0.extra_option.items.0.id', $extraItem->id);

    // Listing endpoint also has them
    $indexResponse = $this->getJson('/api/packages');
    $indexResponse->assertOk()
        ->assertJsonPath('data.0.groups.0.items.0.allow_extras', true)
        ->assertJsonPath('data.0.groups.0.items.0.extra_option.id', $extraOption->id);
});
