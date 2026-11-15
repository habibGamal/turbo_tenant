<?php

declare(strict_types=1);

use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use App\Plugins\ComboPackages\src\Services\PackageRulesValidator;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->category = Category::factory()->create();

    $this->product1 = Product::factory()->create([
        'category_id' => $this->category->id,
        'base_price' => 50.00,
        'is_active' => true,
        'sell_by_weight' => false,
    ]);

    $this->product2 = Product::factory()->create([
        'category_id' => $this->category->id,
        'base_price' => 70.00,
        'is_active' => true,
        'sell_by_weight' => false,
    ]);

    $this->weightProduct = Product::factory()->create([
        'category_id' => $this->category->id,
        'base_price' => 40.00,
        'is_active' => true,
        'sell_by_weight' => true,
    ]);

    $this->variant1 = ProductVariant::create([
        'product_id' => $this->product1->id,
        'name' => 'Large',
        'price' => 60.00,
        'is_available' => true,
    ]);

    $this->variant2 = ProductVariant::create([
        'product_id' => $this->product2->id,
        'name' => 'Spicy',
        'price' => 80.00,
        'is_available' => true,
    ]);

    $this->productNoVariants = Product::factory()->create([
        'category_id' => $this->category->id,
        'base_price' => 30.00,
        'is_active' => true,
        'sell_by_weight' => false,
    ]);

    $this->validator = new PackageRulesValidator();
});

it('validates package rules correctly across rule dataset', function (callable $stateBuilder, ?string $expectedErrorKey, bool $shouldPass) {
    $state = $stateBuilder($this);
    $errors = $this->validator->validate($state);

    if ($shouldPass) {
        expect($errors)->toBeEmpty();
        $this->validator->validateOrFail($state);
    } else {
        expect($errors)->toHaveKey($expectedErrorKey);
        expect(fn () => $this->validator->validateOrFail($state))->toThrow(ValidationException::class);
    }
})->with([
    'valid package with all group passes' => [
        fn ($ctx) => [
            'name' => 'Valid Combo',
            'price' => 100.00,
            'groups' => [
                [
                    'name' => 'Items Group',
                    'selection_type' => 'all',
                    'items' => [
                        [
                            'product_id' => $ctx->product1->id,
                            'variant_id' => $ctx->variant1->id,
                            'quantity' => 1,
                        ],
                    ],
                ],
            ],
        ],
        null,
        true,
    ],

    'valid package with arabic name only passes' => [
        fn ($ctx) => [
            'name' => null,
            'name_ar' => 'باقة صالحة',
            'price' => 100.00,
            'groups' => [
                [
                    'name' => 'Main',
                    'selection_type' => 'choose_one',
                    'min_selections' => 1,
                    'max_selections' => 1,
                    'items' => [
                        [
                            'product_id' => $ctx->product1->id,
                            'quantity' => 1,
                            'is_default' => true,
                        ],
                    ],
                ],
            ],
        ],
        null,
        true,
    ],

    'both name and name_ar empty fails' => [
        fn ($ctx) => [
            'name' => '',
            'name_ar' => '',
            'price' => 100.00,
            'groups' => [
                [
                    'name' => 'Group 1',
                    'selection_type' => 'all',
                    'items' => [['product_id' => $ctx->product1->id]],
                ],
            ],
        ],
        'name',
        false,
    ],

    'price zero or negative fails' => [
        fn ($ctx) => [
            'name' => 'Zero Price Combo',
            'price' => 0.00,
            'groups' => [
                [
                    'name' => 'Group 1',
                    'selection_type' => 'all',
                    'items' => [['product_id' => $ctx->product1->id]],
                ],
            ],
        ],
        'price',
        false,
    ],

    'valid_until earlier than valid_from fails' => [
        fn ($ctx) => [
            'name' => 'Expired Combo',
            'price' => 50.00,
            'valid_from' => '2026-11-10 10:00:00',
            'valid_until' => '2026-11-05 10:00:00',
            'groups' => [
                [
                    'name' => 'Group 1',
                    'selection_type' => 'all',
                    'items' => [['product_id' => $ctx->product1->id]],
                ],
            ],
        ],
        'valid_until',
        false,
    ],

    'no groups fails' => [
        fn ($ctx) => [
            'name' => 'Empty Package',
            'price' => 50.00,
            'groups' => [],
        ],
        'groups',
        false,
    ],

    'group with no items fails' => [
        fn ($ctx) => [
            'name' => 'Package With Empty Group',
            'price' => 50.00,
            'groups' => [
                [
                    'name' => 'Empty Group',
                    'selection_type' => 'all',
                    'items' => [],
                ],
            ],
        ],
        'groups.0.items',
        false,
    ],

    'choose_one with min not equal to 1 fails' => [
        fn ($ctx) => [
            'name' => 'Choose One Bad Min',
            'price' => 50.00,
            'groups' => [
                [
                    'name' => 'Choose 1',
                    'selection_type' => 'choose_one',
                    'min_selections' => 2,
                    'max_selections' => 1,
                    'items' => [
                        ['product_id' => $ctx->product1->id],
                        ['product_id' => $ctx->product2->id],
                    ],
                ],
            ],
        ],
        'groups.0.min_selections',
        false,
    ],

    'choose_one with max not equal to 1 fails' => [
        fn ($ctx) => [
            'name' => 'Choose One Bad Max',
            'price' => 50.00,
            'groups' => [
                [
                    'name' => 'Choose 1',
                    'selection_type' => 'choose_one',
                    'min_selections' => 1,
                    'max_selections' => 2,
                    'items' => [
                        ['product_id' => $ctx->product1->id],
                        ['product_id' => $ctx->product2->id],
                    ],
                ],
            ],
        ],
        'groups.0.max_selections',
        false,
    ],

    'choose_multiple with min less than 0 fails' => [
        fn ($ctx) => [
            'name' => 'Choose Multiple Negative Min',
            'price' => 50.00,
            'groups' => [
                [
                    'name' => 'Choose M',
                    'selection_type' => 'choose_multiple',
                    'min_selections' => -1,
                    'max_selections' => 1,
                    'items' => [['product_id' => $ctx->product1->id]],
                ],
            ],
        ],
        'groups.0.min_selections',
        false,
    ],

    'choose_multiple with min greater than max fails' => [
        fn ($ctx) => [
            'name' => 'Choose Multiple Min > Max',
            'price' => 50.00,
            'groups' => [
                [
                    'name' => 'Choose M',
                    'selection_type' => 'choose_multiple',
                    'min_selections' => 3,
                    'max_selections' => 1,
                    'items' => [
                        ['product_id' => $ctx->product1->id],
                        ['product_id' => $ctx->product2->id],
                    ],
                ],
            ],
        ],
        'groups.0.min_selections',
        false,
    ],

    'choose_multiple with max greater than items count fails' => [
        fn ($ctx) => [
            'name' => 'Choose Multiple Max > Items',
            'price' => 50.00,
            'groups' => [
                [
                    'name' => 'Choose M',
                    'selection_type' => 'choose_multiple',
                    'min_selections' => 1,
                    'max_selections' => 3,
                    'items' => [
                        ['product_id' => $ctx->product1->id],
                        ['product_id' => $ctx->product2->id],
                    ],
                ],
            ],
        ],
        'groups.0.max_selections',
        false,
    ],

    'choose_one with more than one default item fails' => [
        fn ($ctx) => [
            'name' => 'Choose One Too Many Defaults',
            'price' => 50.00,
            'groups' => [
                [
                    'name' => 'Choose 1',
                    'selection_type' => 'choose_one',
                    'min_selections' => 1,
                    'max_selections' => 1,
                    'items' => [
                        ['product_id' => $ctx->product1->id, 'is_default' => true],
                        ['product_id' => $ctx->product2->id, 'is_default' => true],
                    ],
                ],
            ],
        ],
        'groups.0.items',
        false,
    ],

    'choose_multiple with defaults count not within min and max fails' => [
        fn ($ctx) => [
            'name' => 'Choose Multiple Defaults Out Of Bounds',
            'price' => 50.00,
            'groups' => [
                [
                    'name' => 'Choose M',
                    'selection_type' => 'choose_multiple',
                    'min_selections' => 2,
                    'max_selections' => 2,
                    'items' => [
                        ['product_id' => $ctx->product1->id, 'is_default' => true],
                        ['product_id' => $ctx->product2->id, 'is_default' => false],
                    ],
                ],
            ],
        ],
        'groups.0.items',
        false,
    ],

    'all group defaults are ignored and do not fail' => [
        fn ($ctx) => [
            'name' => 'All Group With Defaults Ignored',
            'price' => 50.00,
            'groups' => [
                [
                    'name' => 'All Group',
                    'selection_type' => 'all',
                    'items' => [
                        ['product_id' => $ctx->product1->id, 'is_default' => true],
                        ['product_id' => $ctx->product2->id, 'is_default' => true],
                    ],
                ],
            ],
        ],
        null,
        true,
    ],

    'duplicate product_id and variant_id in same group fails' => [
        fn ($ctx) => [
            'name' => 'Duplicate Items Combo',
            'price' => 50.00,
            'groups' => [
                [
                    'name' => 'Group 1',
                    'selection_type' => 'all',
                    'items' => [
                        ['product_id' => $ctx->product1->id, 'variant_id' => $ctx->variant1->id],
                        ['product_id' => $ctx->product1->id, 'variant_id' => $ctx->variant1->id],
                    ],
                ],
            ],
        ],
        'groups.0.items.1.product_id',
        false,
    ],

    'variant does not belong to selected product fails' => [
        fn ($ctx) => [
            'name' => 'Mismatch Variant Combo',
            'price' => 50.00,
            'groups' => [
                [
                    'name' => 'Group 1',
                    'selection_type' => 'all',
                    'items' => [
                        ['product_id' => $ctx->product1->id, 'variant_id' => $ctx->variant2->id], // variant2 belongs to product2
                    ],
                ],
            ],
        ],
        'groups.0.items.0.variant_id',
        false,
    ],

    'product with sell_by_weight true fails (B14)' => [
        fn ($ctx) => [
            'name' => 'Weight Product Combo',
            'price' => 50.00,
            'groups' => [
                [
                    'name' => 'Group 1',
                    'selection_type' => 'all',
                    'items' => [
                        ['product_id' => $ctx->weightProduct->id],
                    ],
                ],
            ],
        ],
        'groups.0.items.0.product_id',
        false,
    ],

    'with allow_repeats, max may exceed item count passes' => [
        fn ($ctx) => [
            'name' => 'Repeats Allowed Combo',
            'price' => 50.00,
            'groups' => [
                [
                    'name' => 'Sides',
                    'selection_type' => 'choose_multiple',
                    'allow_repeats' => true,
                    'min_selections' => 1,
                    'max_selections' => 5, // only 1 item in group, but allow_repeats is true
                    'items' => [
                        ['product_id' => $ctx->product1->id],
                    ],
                ],
            ],
        ],
        null,
        true,
    ],

    'customer_choice item with variant_id specified fails' => [
        fn ($ctx) => [
            'name' => 'Customer Choice With Variant Combo',
            'price' => 50.00,
            'groups' => [
                [
                    'name' => 'Drinks',
                    'selection_type' => 'choose_one',
                    'min_selections' => 1,
                    'max_selections' => 1,
                    'items' => [
                        [
                            'product_id' => $ctx->product1->id,
                            'variant_mode' => 'customer_choice',
                            'variant_id' => $ctx->variant1->id,
                        ],
                    ],
                ],
            ],
        ],
        'groups.0.items.0.variant_id',
        false,
    ],

    'customer_choice item for product with no variants fails' => [
        fn ($ctx) => [
            'name' => 'Customer Choice No Variants Combo',
            'price' => 50.00,
            'groups' => [
                [
                    'name' => 'Main',
                    'selection_type' => 'choose_one',
                    'min_selections' => 1,
                    'max_selections' => 1,
                    'items' => [
                        [
                            'product_id' => $ctx->productNoVariants->id,
                            'variant_mode' => 'customer_choice',
                        ],
                    ],
                ],
            ],
        ],
        'groups.0.items.0.variant_mode',
        false,
    ],

    'customer_choice item for product with variants passes' => [
        fn ($ctx) => [
            'name' => 'Customer Choice Valid Combo',
            'price' => 50.00,
            'groups' => [
                [
                    'name' => 'Main',
                    'selection_type' => 'choose_one',
                    'min_selections' => 1,
                    'max_selections' => 1,
                    'items' => [
                        [
                            'product_id' => $ctx->product1->id,
                            'variant_mode' => 'customer_choice',
                        ],
                    ],
                ],
            ],
        ],
        null,
        true,
    ],
]);
