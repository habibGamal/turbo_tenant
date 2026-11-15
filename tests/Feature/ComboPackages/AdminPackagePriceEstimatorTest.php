<?php

declare(strict_types=1);

use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use App\Plugins\ComboPackages\src\Services\PackagePriceEstimator;

beforeEach(function () {
    $this->category = Category::factory()->create();

    $this->productA = Product::factory()->create([
        'category_id' => $this->category->id,
        'base_price' => 50.00,
        'price_after_discount' => null,
        'is_active' => true,
    ]);

    $this->productB = Product::factory()->create([
        'category_id' => $this->category->id,
        'base_price' => 70.00,
        'price_after_discount' => null,
        'is_active' => true,
    ]);

    $this->productDiscounted = Product::factory()->create([
        'category_id' => $this->category->id,
        'base_price' => 100.00,
        'price_after_discount' => 80.00,
        'is_active' => true,
    ]);

    $this->variantA = ProductVariant::create([
        'product_id' => $this->productA->id,
        'name' => 'Upsize',
        'price' => 65.00,
        'is_available' => true,
    ]);

    $this->estimator = new PackagePriceEstimator();
});

it('calculates estimate for all-only groups summing every item', function () {
    $state = [
        'groups' => [
            [
                'selection_type' => 'all',
                'items' => [
                    ['product_id' => $this->productA->id, 'quantity' => 1],
                    ['product_id' => $this->productB->id, 'quantity' => 1],
                ],
            ],
        ],
    ];

    // 50 + 70 = 120.00
    expect($this->estimator->estimate($state))->toBe(120.00)
        ->and($this->estimator->calculate($state))->toBe(120.00);
});

it('uses variant price when variant_id is set instead of base price', function () {
    $state = [
        'groups' => [
            [
                'selection_type' => 'all',
                'items' => [
                    ['product_id' => $this->productA->id, 'variant_id' => $this->variantA->id, 'quantity' => 1],
                ],
            ],
        ],
    ];

    // variantA price is 65.00 (productA base_price is 50.00)
    expect($this->estimator->estimate($state))->toBe(65.00);
});

it('uses price_after_discount when set and greater than 0 instead of base_price', function () {
    $state = [
        'groups' => [
            [
                'selection_type' => 'all',
                'items' => [
                    ['product_id' => $this->productDiscounted->id, 'quantity' => 1],
                ],
            ],
        ],
    ];

    // productDiscounted base_price is 100.00, price_after_discount is 80.00
    expect($this->estimator->estimate($state))->toBe(80.00);
});

it('multiplies unit price by quantity', function () {
    $state = [
        'groups' => [
            [
                'selection_type' => 'all',
                'items' => [
                    ['product_id' => $this->productA->id, 'quantity' => 3], // 50 * 3 = 150
                    ['product_id' => $this->productDiscounted->id, 'quantity' => 2], // 80 * 2 = 160
                ],
            ],
        ],
    ];

    // 150 + 160 = 310.00
    expect($this->estimator->estimate($state))->toBe(310.00);
});

it('sums default items in conditional groups when defaults count >= min', function () {
    $prod10 = Product::factory()->create(['category_id' => $this->category->id, 'base_price' => 10.00, 'price_after_discount' => null]);
    $prod20 = Product::factory()->create(['category_id' => $this->category->id, 'base_price' => 20.00, 'price_after_discount' => null]);
    $prod30 = Product::factory()->create(['category_id' => $this->category->id, 'base_price' => 30.00, 'price_after_discount' => null]);

    $state = [
        'groups' => [
            [
                'selection_type' => 'choose_multiple',
                'min_selections' => 2,
                'max_selections' => 3,
                'items' => [
                    ['product_id' => $prod10->id, 'quantity' => 1, 'is_default' => false],
                    ['product_id' => $prod20->id, 'quantity' => 1, 'is_default' => true],
                    ['product_id' => $prod30->id, 'quantity' => 1, 'is_default' => true],
                ],
            ],
        ],
    ];

    // Defaults count is 2 (prod20 + prod30), which is >= min (2)
    // 20 + 30 = 50.00 (even though prod10 is cheaper, defaults take priority when count >= min)
    expect($this->estimator->estimate($state))->toBe(50.00);
});

it('falls back to cheapest min items in conditional groups when defaults count < min', function () {
    $prod10 = Product::factory()->create(['category_id' => $this->category->id, 'base_price' => 10.00, 'price_after_discount' => null]);
    $prod20 = Product::factory()->create(['category_id' => $this->category->id, 'base_price' => 20.00, 'price_after_discount' => null]);
    $prod40 = Product::factory()->create(['category_id' => $this->category->id, 'base_price' => 40.00, 'price_after_discount' => null]);

    $state = [
        'groups' => [
            [
                'selection_type' => 'choose_multiple',
                'min_selections' => 2,
                'max_selections' => 3,
                'items' => [
                    ['product_id' => $prod40->id, 'quantity' => 1, 'is_default' => true], // 1 default only
                    ['product_id' => $prod20->id, 'quantity' => 1, 'is_default' => false],
                    ['product_id' => $prod10->id, 'quantity' => 1, 'is_default' => false],
                ],
            ],
        ],
    ];

    // Defaults count is 1, which is < min (2).
    // Fallback: cheapest 2 items among all items: prod10 (10) + prod20 (20) = 30.00
    expect($this->estimator->estimate($state))->toBe(30.00);
});

it('handles choose_one group defaults vs fallback', function () {
    $prodChoice1 = Product::factory()->create(['category_id' => $this->category->id, 'base_price' => 25.00, 'price_after_discount' => null]);
    $prodChoice2 = Product::factory()->create(['category_id' => $this->category->id, 'base_price' => 15.00, 'price_after_discount' => null]);

    // Case 1: with default item
    $stateWithDefault = [
        'groups' => [
            [
                'selection_type' => 'choose_one',
                'min_selections' => 1,
                'max_selections' => 1,
                'items' => [
                    ['product_id' => $prodChoice1->id, 'quantity' => 1, 'is_default' => true],
                    ['product_id' => $prodChoice2->id, 'quantity' => 1, 'is_default' => false],
                ],
            ],
        ],
    ];
    expect($this->estimator->estimate($stateWithDefault))->toBe(25.00);

    // Case 2: without default item -> cheapest item
    $stateWithoutDefault = [
        'groups' => [
            [
                'selection_type' => 'choose_one',
                'min_selections' => 1,
                'max_selections' => 1,
                'items' => [
                    ['product_id' => $prodChoice1->id, 'quantity' => 1, 'is_default' => false],
                    ['product_id' => $prodChoice2->id, 'quantity' => 1, 'is_default' => false],
                ],
            ],
        ],
    ];
    expect($this->estimator->estimate($stateWithoutDefault))->toBe(15.00);
});
