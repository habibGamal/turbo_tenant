<?php

declare(strict_types=1);

use App\Plugins\CatalogProducts\src\Models\ExtraOption;
use App\Plugins\CatalogProducts\src\Models\ExtraOptionItem;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Models\OrderItem;
use App\Plugins\OrderManagement\src\Models\OrderItemComponent;
use App\Plugins\PosIntegration\src\Models\PosLineMapping;
use App\Plugins\PosIntegration\src\Models\ProductPosMapping;
use App\Plugins\PosIntegration\src\Services\OrderDeliveryEligibility;
use App\Plugins\PosIntegration\src\Services\POS\POSProductResolver;
use Illuminate\Support\Facades\DB;

describe('Composite POS Product Resolver', function () {
    it('resolves composite order item with multiple components and precedence rules', function () {
        $branch = Branch::factory()->create();
        $resolver = new POSProductResolver();

        $prod1 = Product::factory()->create();
        $var1 = ProductVariant::factory()->create(['product_id' => $prod1->id]);
        $prod2 = Product::factory()->create();

        // Branch-specific variant mapping for prod1
        ProductPosMapping::factory()->create([
            'product_id' => $prod1->id,
            'variant_id' => $var1->id,
            'branch_id' => $branch->id,
            'pos_item_id' => 'BRANCH-V1',
        ]);

        // Global base mapping for prod2
        ProductPosMapping::factory()->create([
            'product_id' => $prod2->id,
            'variant_id' => null,
            'branch_id' => null,
            'pos_item_id' => 'GLOBAL-P2',
        ]);

        $order = Order::factory()->create(['branch_id' => $branch->id]);
        $compositeItem = OrderItem::factory()->create([
            'order_id' => $order->id,
            'line_type' => 'package',
            'line_reference_id' => 77,
            'product_id' => null,
            'quantity' => 3,
            'notes' => 'No sauce',
        ]);

        OrderItemComponent::create([
            'order_item_id' => $compositeItem->id,
            'group_name' => 'Meals',
            'product_id' => $prod1->id,
            'variant_id' => $v1->id ?? $var1->id,
            'product_name' => 'Burger Item',
            'variant_name' => 'Large',
            'quantity' => 1,
            'sort_order' => 1,
        ]);

        OrderItemComponent::create([
            'order_item_id' => $compositeItem->id,
            'group_name' => 'Drinks',
            'product_id' => $prod2->id,
            'variant_id' => null,
            'product_name' => 'Juice Item',
            'variant_name' => null,
            'quantity' => 2,
            'sort_order' => 2,
        ]);

        $resolved = $resolver->resolveOrderItem($compositeItem, $branch->id);

        expect($resolved)->not->toBeNull()
            ->and($resolved['quantity'])->toBe(3.0)
            ->and($resolved['posRefObj'])->toBe([
                ['productRef' => 'BRANCH-V1', 'quantity' => 1],
                ['productRef' => 'GLOBAL-P2', 'quantity' => 2],
            ]);

        expect($resolved['notes'])->toContain('Meals: Burger Item (Large) x1')
            ->and($resolved['notes'])->toContain('Drinks: Juice Item x2')
            ->and($resolved['notes'])->toContain('No sauce');
    });

    it('returns null when any component in composite item lacks POS mapping', function () {
        $branch = Branch::factory()->create();
        $resolver = new POSProductResolver();

        $prod1 = Product::factory()->create();
        $prod2 = Product::factory()->create(); // unmapped

        ProductPosMapping::factory()->create([
            'product_id' => $prod1->id,
            'branch_id' => $branch->id,
            'pos_item_id' => 'MAPPED-1',
        ]);

        $order = Order::factory()->create(['branch_id' => $branch->id]);
        $compositeItem = OrderItem::factory()->create([
            'order_id' => $order->id,
            'line_type' => 'package',
            'product_id' => null,
            'quantity' => 1,
        ]);

        OrderItemComponent::create([
            'order_item_id' => $compositeItem->id,
            'product_id' => $prod1->id,
            'product_name' => 'Item 1',
            'quantity' => 1,
        ]);

        OrderItemComponent::create([
            'order_item_id' => $compositeItem->id,
            'product_id' => $prod2->id,
            'product_name' => 'Item 2 Unmapped',
            'quantity' => 1,
        ]);

        $resolved = $resolver->resolveOrderItem($compositeItem, $branch->id);
        expect($resolved)->toBeNull();
    });

    it('loads mappings for composite components in a single database query', function () {
        $branch = Branch::factory()->create();
        $resolver = new POSProductResolver();

        $prod1 = Product::factory()->create();
        $prod2 = Product::factory()->create();

        ProductPosMapping::factory()->create([
            'product_id' => $prod1->id,
            'branch_id' => $branch->id,
            'pos_item_id' => 'MAP-1',
        ]);
        ProductPosMapping::factory()->create([
            'product_id' => $prod2->id,
            'branch_id' => $branch->id,
            'pos_item_id' => 'MAP-2',
        ]);

        $order = Order::factory()->create(['branch_id' => $branch->id]);
        $compositeItem = OrderItem::factory()->create([
            'order_id' => $order->id,
            'line_type' => 'package',
            'product_id' => null,
            'quantity' => 1,
        ]);

        OrderItemComponent::create([
            'order_item_id' => $compositeItem->id,
            'product_id' => $prod1->id,
            'product_name' => 'Item 1',
            'quantity' => 1,
        ]);
        OrderItemComponent::create([
            'order_item_id' => $compositeItem->id,
            'product_id' => $prod2->id,
            'product_name' => 'Item 2',
            'quantity' => 1,
        ]);

        DB::connection('tenant')->enableQueryLog();
        DB::connection('tenant')->flushQueryLog();

        $mappings = $resolver->loadMappingsForOrder($order);

        $queries = DB::connection('tenant')->getQueryLog();
        $mappingQueries = array_filter($queries, fn ($q) => str_contains($q['query'], 'product_pos_mappings'));

        expect(count($mappingQueries))->toBe(1)
            ->and($mappings)->toHaveCount(2);
    });

    it('prefers branch mapping over NULL-branch mapping for package-level mapping', function () {
        $branch = Branch::factory()->create();
        $resolver = new POSProductResolver();

        PosLineMapping::create([
            'line_type' => 'package',
            'line_reference_id' => 101,
            'branch_id' => null,
            'pos_item_id' => 'PKG-GLOBAL',
        ]);
        PosLineMapping::create([
            'line_type' => 'package',
            'line_reference_id' => 101,
            'branch_id' => $branch->id,
            'pos_item_id' => 'PKG-BRANCH',
        ]);

        $order = Order::factory()->create(['branch_id' => $branch->id]);
        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'line_type' => 'package',
            'line_reference_id' => 101,
            'product_id' => null,
            'quantity' => 2,
        ]);

        OrderItemComponent::create([
            'order_item_id' => $item->id,
            'product_id' => null,
            'product_name' => 'Burger',
            'quantity' => 1,
        ]);

        $resolved = $resolver->resolveOrderItem($item, $branch->id);

        expect($resolved)->not->toBeNull()
            ->and($resolved['quantity'])->toBe(2.0)
            ->and($resolved['posRefObj'])->toBe([
                ['productRef' => 'PKG-BRANCH', 'quantity' => 1],
            ]);
    });

    it('falls back to NULL-branch mapping when no branch mapping exists', function () {
        $branch = Branch::factory()->create();
        $resolver = new POSProductResolver();

        PosLineMapping::create([
            'line_type' => 'package',
            'line_reference_id' => 102,
            'branch_id' => null,
            'pos_item_id' => 'PKG-FALLBACK',
        ]);

        $order = Order::factory()->create(['branch_id' => $branch->id]);
        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'line_type' => 'package',
            'line_reference_id' => 102,
            'product_id' => null,
            'quantity' => 3,
        ]);

        OrderItemComponent::create([
            'order_item_id' => $item->id,
            'product_id' => null,
            'product_name' => 'Fries',
            'quantity' => 1,
        ]);

        $resolved = $resolver->resolveOrderItem($item, $branch->id);

        expect($resolved)->not->toBeNull()
            ->and($resolved['quantity'])->toBe(3.0)
            ->and($resolved['posRefObj'])->toBe([
                ['productRef' => 'PKG-FALLBACK', 'quantity' => 1],
            ]);
    });

    it('puts components with extras and notes into the item notes for package-level mapping', function () {
        $branch = Branch::factory()->create();
        $resolver = new POSProductResolver();

        PosLineMapping::create([
            'line_type' => 'package',
            'line_reference_id' => 103,
            'branch_id' => null,
            'pos_item_id' => 'PKG-ALL-IN-ONE',
        ]);

        $order = Order::factory()->create(['branch_id' => $branch->id]);
        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'line_type' => 'package',
            'line_reference_id' => 103,
            'product_id' => null,
            'quantity' => 1,
            'notes' => 'Deliver hot',
        ]);

        OrderItemComponent::create([
            'order_item_id' => $item->id,
            'group_name' => 'Mains',
            'product_name' => 'Double Cheeseburger',
            'quantity' => 1,
            'extras' => [
                ['extra_option_item_id' => 999, 'name' => 'Extra Pickles', 'quantity' => 2],
            ],
            'notes' => 'no onions please',
        ]);

        $resolved = $resolver->resolveOrderItem($item, $branch->id);

        expect($resolved)->not->toBeNull()
            ->and($resolved['posRefObj'])->toBe([
                ['productRef' => 'PKG-ALL-IN-ONE', 'quantity' => 1],
            ])
            ->and($resolved['notes'])->toContain('Double Cheeseburger')
            ->and($resolved['notes'])->toContain('Extra Pickles (x2)')
            ->and($resolved['notes'])->toContain('no onions please')
            ->and($resolved['notes'])->toContain('Deliver hot');
    });

    it('treats package-mapped combos with unmapped components as eligible', function () {
        $branch = Branch::factory()->create();
        $resolver = new POSProductResolver();

        PosLineMapping::create([
            'line_type' => 'package',
            'line_reference_id' => 104,
            'branch_id' => null,
            'pos_item_id' => 'PKG-UNMAPPED-COMPONENTS',
        ]);

        $prodUnmapped = Product::factory()->create(['name' => 'Unmapped Sub-Product']);

        $order = Order::factory()->create([
            'branch_id' => $branch->id,
            'type' => 'web_delivery',
            'status' => 'pending',
            'payment_status' => 'pending',
        ]);

        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'line_type' => 'package',
            'line_reference_id' => 104,
            'product_id' => null,
            'quantity' => 1,
        ]);

        OrderItemComponent::create([
            'order_item_id' => $item->id,
            'product_id' => $prodUnmapped->id,
            'product_name' => 'Unmapped Sub-Product',
            'quantity' => 1,
        ]);

        // Verify resolver maps the item successfully even though components have no ProductPosMapping
        $resolved = $resolver->resolveOrderItem($item, $branch->id);
        expect($resolved)->not->toBeNull()
            ->and($resolved['posRefObj'])->toBe([
                ['productRef' => 'PKG-UNMAPPED-COMPONENTS', 'quantity' => 1],
            ]);

        // Verify OrderDeliveryEligibility treats it as eligible
        $eligibility = app(OrderDeliveryEligibility::class);
        expect($eligibility->blockingReason($order))->toBeNull()
            ->and($eligibility->isEligible($order))->toBeTrue();
    });

    it('explodes combo with a pos_item extra as extra posRefObj and a notes-type extra to notes', function () {
        $branch = Branch::factory()->create();
        $resolver = new POSProductResolver();

        $prod = Product::factory()->create();
        ProductPosMapping::factory()->create([
            'product_id' => $prod->id,
            'branch_id' => $branch->id,
            'pos_item_id' => 'POS-BURGER',
        ]);

        $extraOption = ExtraOption::factory()->create();
        $posItemExtra = ExtraOptionItem::factory()->create([
            'extra_option_id' => $extraOption->id,
            'name' => 'Bacon Slice',
            'pos_mapping_type' => 'pos_item',
        ]);
        $notesExtra = ExtraOptionItem::factory()->create([
            'extra_option_id' => $extraOption->id,
            'name' => 'Special Sauce',
            'pos_mapping_type' => 'notes',
        ]);

        // Mapping for the pos_item extra
        ProductPosMapping::factory()->create([
            'product_id' => null,
            'variant_id' => null,
            'extra_option_item_id' => $posItemExtra->id,
            'branch_id' => $branch->id,
            'pos_item_id' => 'POS-BACON',
        ]);

        $order = Order::factory()->create(['branch_id' => $branch->id]);
        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'line_type' => 'package',
            'line_reference_id' => 999, // Unmapped at package level -> will explode
            'product_id' => null,
            'quantity' => 1,
        ]);

        OrderItemComponent::create([
            'order_item_id' => $item->id,
            'group_name' => 'Meals',
            'product_id' => $prod->id,
            'product_name' => 'Burger',
            'quantity' => 1,
            'extras' => [
                ['extra_option_item_id' => $posItemExtra->id, 'name' => 'Bacon Slice', 'quantity' => 2],
                ['extra_option_item_id' => $notesExtra->id, 'name' => 'Special Sauce', 'quantity' => 1],
            ],
            'notes' => 'medium rare',
        ]);

        $resolved = $resolver->resolveOrderItem($item, $branch->id);

        expect($resolved)->not->toBeNull()
            ->and($resolved['posRefObj'])->toBe([
                ['productRef' => 'POS-BURGER', 'quantity' => 1],
                ['productRef' => 'POS-BACON', 'quantity' => 2],
            ]);

        // Notes-type extra and component notes must be in notes; pos_item extra must not be in notes
        expect($resolved['notes'])->toContain('Special Sauce (x1)')
            ->and($resolved['notes'])->toContain('medium rare')
            ->and($resolved['notes'])->not->toContain('Bacon Slice');
    });

    it('ensures component notes appear in the POS notes during explode', function () {
        $branch = Branch::factory()->create();
        $resolver = new POSProductResolver();

        $prod = Product::factory()->create();
        ProductPosMapping::factory()->create([
            'product_id' => $prod->id,
            'branch_id' => $branch->id,
            'pos_item_id' => 'POS-BURGER-2',
        ]);

        $order = Order::factory()->create(['branch_id' => $branch->id]);
        $item = OrderItem::factory()->create([
            'order_id' => $order->id,
            'line_type' => 'package',
            'line_reference_id' => 998,
            'product_id' => null,
            'quantity' => 1,
        ]);

        OrderItemComponent::create([
            'order_item_id' => $item->id,
            'product_id' => $prod->id,
            'product_name' => 'Burger',
            'quantity' => 1,
            'notes' => 'cut into halves please',
        ]);

        $resolved = $resolver->resolveOrderItem($item, $branch->id);

        expect($resolved)->not->toBeNull()
            ->and($resolved['notes'])->toContain('cut into halves please');
    });
});
