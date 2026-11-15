<?php

declare(strict_types=1);

use App\Plugins\CatalogProducts\src\Models\ExtraOptionItem;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use App\Plugins\CatalogProducts\src\Models\WeightOptionValue;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Models\OrderItem;
use App\Plugins\OrderManagement\src\Models\OrderItemExtra;
use App\Plugins\PosIntegration\src\Models\ProductPosMapping;
use App\Plugins\PosIntegration\src\Services\POS\POSProductResolver;

beforeEach(function () {
    $this->resolver = new POSProductResolver();
});

describe('POSProductResolver', function () {
    it('returns null when main product has no POS mapping', function () {
        $product = Product::factory()->create();
        $order = Order::factory()->create();
        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $result = $this->resolver->resolveOrderItem($item, $order->branch_id);

        expect($result)->toBeNull();
    });

    it('resolves simple 1-to-1 product mapping with posRefObj conforming to API doc', function () {
        $branch = Branch::factory()->create();
        $product = Product::factory()->create();
        ProductPosMapping::factory()->create([
            'product_id' => $product->id,
            'pos_item_id' => 'BURGER-SINGLE',
            'branch_id' => $branch->id,
        ]);

        $order = Order::factory()->create(['branch_id' => $branch->id]);
        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'notes' => 'No onions',
        ]);

        $result = $this->resolver->resolveOrderItem($item, $branch->id);

        expect($result)->not->toBeNull()
            ->and($result['quantity'])->toBe(2.0)
            ->and($result['notes'])->toBe('No onions')
            ->and($result['posRefObj'])->toBe([
                [
                    'productRef' => 'BURGER-SINGLE',
                    'quantity' => 1,
                ],
            ]);
    });

    it('resolves variant-specific mapping when available', function () {
        $branch = Branch::factory()->create();
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        ProductPosMapping::factory()->create([
            'product_id' => $product->id,
            'variant_id' => null,
            'pos_item_id' => 'BASE-PRODUCT',
            'branch_id' => $branch->id,
        ]);

        ProductPosMapping::factory()->create([
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'pos_item_id' => 'VARIANT-PRODUCT',
            'branch_id' => $branch->id,
        ]);

        $order = Order::factory()->create(['branch_id' => $branch->id]);
        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        $result = $this->resolver->resolveOrderItem($item, $branch->id);

        expect($result['posRefObj'])->toBe([
            [
                'productRef' => 'VARIANT-PRODUCT',
                'quantity' => 1,
            ],
        ]);
    });

    it('falls back to base product mapping and adds variant name to notes when variant has no mapping', function () {
        $branch = Branch::factory()->create();
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Large Size',
        ]);

        ProductPosMapping::factory()->create([
            'product_id' => $product->id,
            'variant_id' => null,
            'pos_item_id' => 'BASE-PRODUCT',
            'branch_id' => $branch->id,
        ]);

        $order = Order::factory()->create(['branch_id' => $branch->id]);
        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        $result = $this->resolver->resolveOrderItem($item, $branch->id);

        expect($result['posRefObj'][0]['productRef'])->toBe('BASE-PRODUCT')
            ->and($result['notes'])->toBe('json::'.json_encode(['النوع' => 'Large Size'], JSON_UNESCAPED_UNICODE));
    });

    it('includes mapped extras inside posRefObj with accurate multiplier quantities', function () {
        $branch = Branch::factory()->create();
        $product = Product::factory()->create();
        $extraItem = ExtraOptionItem::factory()->create(['name' => 'Extra Cheese']);

        ProductPosMapping::factory()->create([
            'product_id' => $product->id,
            'pos_item_id' => 'MAIN-BURGER',
            'branch_id' => $branch->id,
        ]);

        ProductPosMapping::factory()->create([
            'extra_option_item_id' => $extraItem->id,
            'pos_item_id' => 'EXTRA-CHEESE',
            'branch_id' => $branch->id,
        ]);

        $order = Order::factory()->create(['branch_id' => $branch->id]);
        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        OrderItemExtra::factory()->create([
            'order_item_id' => $item->id,
            'extra_option_item_id' => $extraItem->id,
            'quantity' => 3,
        ]);

        $result = $this->resolver->resolveOrderItem($item, $branch->id);

        expect($result['posRefObj'])->toHaveCount(2)
            ->and($result['posRefObj'][0])->toBe([
                'productRef' => 'MAIN-BURGER',
                'quantity' => 1,
            ])
            ->and($result['posRefObj'][1])->toBe([
                'productRef' => 'EXTRA-CHEESE',
                'quantity' => 3.0,
            ]);
    });

    it('handles weighted item multiplier correctly for extra quantities and notes', function () {
        $branch = Branch::factory()->create();
        $product = Product::factory()->create();
        $extraItem = ExtraOptionItem::factory()->create(['name' => 'Sauce']);
        $weightOption = WeightOptionValue::factory()->create([
            'value' => '0.500',
            'label' => '500g',
        ]);

        ProductPosMapping::factory()->create([
            'product_id' => $product->id,
            'pos_item_id' => 'STEAK-MEAT',
            'branch_id' => $branch->id,
        ]);

        ProductPosMapping::factory()->create([
            'extra_option_item_id' => $extraItem->id,
            'pos_item_id' => 'EXTRA-SAUCE',
            'branch_id' => $branch->id,
        ]);

        $order = Order::factory()->create(['branch_id' => $branch->id]);
        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'weight_option_value_id' => $weightOption->id,
            'weight_multiplier' => 2,
            'quantity' => 1,
        ]);

        OrderItemExtra::factory()->create([
            'order_item_id' => $item->id,
            'extra_option_item_id' => $extraItem->id,
            'extra_name' => 'Sauce',
            'quantity' => 2,
        ]);

        $result = $this->resolver->resolveOrderItem($item, $branch->id);

        expect($result['posRefObj'][1]['quantity'])->toBe(4.0) // 2 * 2 multiplier
            ->and($result['notes'])->toBe('json::'.json_encode(['الوزن' => '(2 x) 500g', 'اضافات' => 'Sauce (x2)'], JSON_UNESCAPED_UNICODE));
    });

    it('resolves variant mapping even when base product has no mapping', function () {
        $branch = Branch::factory()->create();
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

        // Only create mapping for variant, no base product mapping
        ProductPosMapping::factory()->create([
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'pos_item_id' => 'VARIANT-ONLY-REF',
            'branch_id' => $branch->id,
        ]);

        $order = Order::factory()->create(['branch_id' => $branch->id]);
        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        $result = $this->resolver->resolveOrderItem($item, $branch->id);

        expect($result)->not->toBeNull()
            ->and($result['posRefObj'][0]['productRef'])->toBe('VARIANT-ONLY-REF');
    });

    it('prioritizes branch-specific mapping over global fallback mapping', function () {
        $branch = Branch::factory()->create();
        $product = Product::factory()->create();

        // Global fallback mapping (branch_id = null)
        ProductPosMapping::factory()->create([
            'product_id' => $product->id,
            'variant_id' => null,
            'pos_item_id' => 'GLOBAL-REF',
            'branch_id' => null,
        ]);

        // Branch-specific mapping
        ProductPosMapping::factory()->create([
            'product_id' => $product->id,
            'variant_id' => null,
            'pos_item_id' => 'BRANCH-REF',
            'branch_id' => $branch->id,
        ]);

        $order = Order::factory()->create(['branch_id' => $branch->id]);
        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
        ]);

        $result = $this->resolver->resolveOrderItem($item, $branch->id);

        expect($result)->not->toBeNull()
            ->and($result['posRefObj'][0]['productRef'])->toBe('BRANCH-REF');
    });

    it('ignores extras without extra_option_item_id when building posRefObj', function () {
        $branch = Branch::factory()->create();
        $product = Product::factory()->create();

        ProductPosMapping::factory()->create([
            'product_id' => $product->id,
            'pos_item_id' => 'MAIN-BURGER',
            'branch_id' => $branch->id,
        ]);

        $order = Order::factory()->create(['branch_id' => $branch->id]);
        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'variant_id' => null,
            'quantity' => 1,
        ]);

        OrderItemExtra::factory()->create([
            'order_item_id' => $item->id,
            'extra_option_item_id' => null,
            'extra_name' => 'Custom Spice',
            'quantity' => 1,
        ]);

        $result = $this->resolver->resolveOrderItem($item, $branch->id);

        expect($result['posRefObj'])->toHaveCount(1)
            ->and($result['posRefObj'][0]['productRef'])->toBe('MAIN-BURGER')
            ->and($result['notes'])->toBe('json::'.json_encode(['اضافات' => 'Custom Spice (x1)'], JSON_UNESCAPED_UNICODE));
    });

    it('returns a clone carrying preloaded mappings without mutating original instance', function () {
        $branch = Branch::factory()->create();
        $product = Product::factory()->create();

        $mapping = ProductPosMapping::factory()->make([
            'product_id' => $product->id,
            'pos_item_id' => 'PRELOADED-REF',
            'branch_id' => $branch->id,
        ]);

        $mappings = collect([$mapping]);

        $clonedResolver = $this->resolver->withMappings($mappings);

        expect($clonedResolver)->not->toBe($this->resolver)
            ->and($clonedResolver)->toBeInstanceOf(POSProductResolver::class);

        expect(method_exists($this->resolver, 'setMappings'))->toBeFalse()
            ->and(method_exists($this->resolver, 'getMappings'))->toBeFalse();

        $order = Order::factory()->create(['branch_id' => $branch->id]);
        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'variant_id' => null,
            'quantity' => 1,
        ]);

        // Resolving with cloned resolver uses preloaded mappings (in-memory mapping)
        $result = $clonedResolver->resolveOrderItem($item, $branch->id);

        expect($result)->not->toBeNull()
            ->and($result['posRefObj'][0]['productRef'])->toBe('PRELOADED-REF');

        // Resolving with original resolver does not have preloaded mappings and finds nothing in DB
        $originalResult = $this->resolver->resolveOrderItem($item, $branch->id);
        expect($originalResult)->toBeNull();
    });

    it('respects the branch filter when both productIds and extraOptionItemIds are present', function () {
        $branch1 = Branch::factory()->create();
        $branch2 = Branch::factory()->create();

        $product1 = Product::factory()->create();
        $extraOptionItem1 = ExtraOptionItem::factory()->create();

        // Branch 1 mapping for product 1
        ProductPosMapping::factory()->create([
            'product_id' => $product1->id,
            'extra_option_item_id' => null,
            'pos_item_id' => 'BRANCH1-PROD',
            'branch_id' => $branch1->id,
        ]);

        // Branch 2 mapping for extra option item 1
        ProductPosMapping::factory()->create([
            'product_id' => null,
            'extra_option_item_id' => $extraOptionItem1->id,
            'pos_item_id' => 'BRANCH2-EXTRA',
            'branch_id' => $branch2->id,
        ]);

        $order = Order::factory()->create(['branch_id' => $branch1->id]);
        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'product_id' => $product1->id,
            'quantity' => 1,
        ]);
        $itemExtra = OrderItemExtra::factory()->create([
            'order_item_id' => $item->id,
            'extra_option_item_id' => $extraOptionItem1->id,
            'quantity' => 1,
        ]);
        $item->setRelation('extras', collect([$itemExtra]));

        $mappings = $this->resolver->loadMappingsForItems([$item], $branch1->id);

        expect($mappings->pluck('pos_item_id')->toArray())->toBe(['BRANCH1-PROD'])
            ->and($mappings->pluck('pos_item_id')->toArray())->not->toContain('BRANCH2-EXTRA');
    });
});
