<?php

declare(strict_types=1);

use App\Models\User;
use App\Plugins\CartEngine\src\Services\Contracts\CartServiceInterface;
use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\ExtraOption;
use App\Plugins\CatalogProducts\src\Models\ExtraOptionItem;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\ComboPackages\src\Models\Package;
use App\Plugins\ComboPackages\src\Models\PackageGroup;
use App\Plugins\ComboPackages\src\Models\PackageItem;
use App\Plugins\LocationsDelivery\src\Models\Address;
use App\Plugins\LocationsDelivery\src\Models\Area;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\LocationsDelivery\src\Models\Governorate;
use App\Plugins\OrderManagement\src\DTOs\OrderTotals;
use App\Plugins\OrderManagement\src\DTOs\PlaceOrderData;
use App\Plugins\OrderManagement\src\Enums\PaymentMethod;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Services\Repositories\OrderRepository;
use App\Plugins\PosIntegration\src\Models\PosLineMapping;
use App\Plugins\PosIntegration\src\Models\ProductPosMapping;
use App\Plugins\PosIntegration\src\Services\POS\POSProductResolver;

// One combo with a component extra and note, followed from the storefront cart API through the
// order snapshot to the POS payload — the path the per-plugin tests only cover in pieces.

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->branch = Branch::factory()->create();
    $category = Category::factory()->create();

    $extraOption = ExtraOption::create(['name' => 'Burger Extras', 'is_active' => true, 'min_selections' => 0, 'max_selections' => 2, 'allow_multiple' => true]);
    $this->cheese = ExtraOptionItem::create(['extra_option_id' => $extraOption->id, 'name' => 'Cheese', 'price' => 8, 'allow_quantity' => true, 'pos_mapping_type' => 'pos_item']);

    $this->burger = Product::factory()->create(['category_id' => $category->id, 'name' => 'Burger', 'base_price' => 100, 'price_after_discount' => null, 'is_active' => true, 'extra_option_id' => $extraOption->id]);
    $this->fries = Product::factory()->create(['category_id' => $category->id, 'name' => 'Fries', 'base_price' => 30, 'price_after_discount' => null, 'is_active' => true]);

    $this->package = Package::factory()->create(['name' => 'Burger Box', 'price' => 120, 'is_active' => true, 'valid_from' => null, 'valid_until' => null]);
    $fixed = PackageGroup::create(['package_id' => $this->package->id, 'name' => 'Main', 'selection_type' => 'all', 'sort_order' => 0]);
    $this->burgerItem = PackageItem::create(['package_group_id' => $fixed->id, 'product_id' => $this->burger->id, 'quantity' => 1, 'price_adjustment' => 0, 'allow_extras' => true]);
    $sides = PackageGroup::create(['package_id' => $this->package->id, 'name' => 'Side', 'selection_type' => 'choose_one', 'min_selections' => 1, 'max_selections' => 1, 'sort_order' => 1]);
    $this->friesItem = PackageItem::create(['package_group_id' => $sides->id, 'product_id' => $this->fries->id, 'quantity' => 1, 'price_adjustment' => 0]);

    ProductPosMapping::factory()->create(['product_id' => $this->burger->id, 'branch_id' => $this->branch->id, 'pos_item_id' => 'POS-BURGER']);
    ProductPosMapping::factory()->create(['product_id' => $this->fries->id, 'branch_id' => $this->branch->id, 'pos_item_id' => 'POS-FRIES']);
    ProductPosMapping::factory()->create(['product_id' => null, 'variant_id' => null, 'extra_option_item_id' => $this->cheese->id, 'branch_id' => $this->branch->id, 'pos_item_id' => 'POS-CHEESE']);

    $this->placeComboOrder = function (): Order {
        $this->actingAs($this->user)->postJson('/api/cart/items', [
            'type' => 'package',
            'quantity' => 2,
            'package_id' => $this->package->id,
            'selections' => [
                ['package_item_id' => $this->burgerItem->id, 'extras' => [['id' => $this->cheese->id, 'quantity' => 2]], 'notes' => 'no onions'],
                ['package_item_id' => $this->friesItem->id, 'quantity' => 1],
            ],
        ])->assertStatus(201);

        $lines = app(CartServiceInterface::class)->getLines($this->user);
        $area = Area::factory()->create(['governorate_id' => Governorate::factory()->create()->id]);
        $address = Address::factory()->create(['user_id' => $this->user->id, 'area_id' => $area->id]);

        return app(OrderRepository::class)->createOrderWithItems(
            new PlaceOrderData(user: $this->user, branchId: $this->branch->id, paymentMethod: PaymentMethod::COD, addressId: $address->id),
            $lines,
            new OrderTotals(subTotal: 272.0, discount: 0.0, tax: 0.0, service: 0.0, deliveryFee: 0.0, total: 272.0)
        );
    };
});

it('snapshots component extras and notes and explodes them into the POS payload', function (): void {
    $order = ($this->placeComboOrder)();
    $item = $order->items()->with('components')->first();

    // 120 base + cheese 8 × 2 = 136 per combo.
    expect((float) $item->unit_price)->toBe(136.0);

    $burger = $item->components->firstWhere('product_id', $this->burger->id);
    expect($burger->notes)->toBe('no onions')
        ->and($burger->extras[0]['quantity'])->toEqual(2);

    $resolved = app(POSProductResolver::class)->resolveOrderItem($item, $this->branch->id);

    expect($resolved['quantity'])->toEqual(2)
        ->and(collect($resolved['posRefObj'])->pluck('productRef')->all())->toEqualCanonicalizing(['POS-BURGER', 'POS-FRIES', 'POS-CHEESE'])
        ->and(collect($resolved['posRefObj'])->firstWhere('productRef', 'POS-CHEESE')['quantity'])->toEqual(2)
        ->and($resolved['notes'])->toContain('no onions');
});

it('sends a package-mapped combo as one POS product with its customisation in the notes', function (): void {
    PosLineMapping::create(['line_type' => 'package', 'line_reference_id' => $this->package->id, 'branch_id' => null, 'pos_item_id' => 'POS-BURGER-BOX']);

    $order = ($this->placeComboOrder)();
    $item = $order->items()->with('components')->first();

    $resolved = app(POSProductResolver::class)->resolveOrderItem($item, $this->branch->id);

    expect($resolved['posRefObj'])->toBe([['productRef' => 'POS-BURGER-BOX', 'quantity' => 1]])
        ->and($resolved['quantity'])->toEqual(2)
        ->and($resolved['notes'])->toContain('no onions')
        ->and($resolved['notes'])->toContain('Cheese');
});
