<?php

declare(strict_types=1);

use App\Models\User;
use App\Plugins\CartEngine\src\Services\Contracts\CartServiceInterface;
use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use App\Plugins\ComboPackages\src\Models\Package;
use App\Plugins\ComboPackages\src\Models\PackageGroup;
use App\Plugins\ComboPackages\src\Models\PackageItem;
use App\Plugins\ComboPackages\src\Pipelines\ValidatePackageLinesPipe;
use App\Plugins\LocationsDelivery\src\Models\Address;
use App\Plugins\LocationsDelivery\src\Models\Area;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\LocationsDelivery\src\Models\Governorate;
use App\Plugins\OrderManagement\src\DTOs\OrderTotals;
use App\Plugins\OrderManagement\src\DTOs\PlaceOrderData;
use App\Plugins\OrderManagement\src\Enums\PaymentMethod;
use App\Plugins\OrderManagement\src\Exceptions\OrderValidationException;
use App\Plugins\OrderManagement\src\Pipelines\Context\OrderValidationContext;
use App\Plugins\OrderManagement\src\Services\Repositories\OrderRepository;

// End-to-end through the real HTTP cart endpoint and the real "package" line handler,
// which the CartEngine and ComboPackages tests only exercise separately.

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $category = Category::factory()->create();

    $this->burger = Product::factory()->create(['category_id' => $category->id, 'name' => 'Burger', 'base_price' => 100, 'price_after_discount' => null, 'is_active' => true]);
    $this->fries = Product::factory()->create(['category_id' => $category->id, 'name' => 'Fries', 'base_price' => 30, 'price_after_discount' => null, 'is_active' => true]);
    $this->soda = Product::factory()->create(['category_id' => $category->id, 'name' => 'Soda', 'base_price' => 20, 'price_after_discount' => null, 'is_active' => true]);
    $this->largeSoda = ProductVariant::create(['product_id' => $this->soda->id, 'name' => 'Large', 'price' => 25, 'is_available' => true]);

    $this->package = Package::factory()->create(['name' => 'Family Box', 'price' => 150, 'is_active' => true, 'valid_from' => null, 'valid_until' => null]);

    $fixed = PackageGroup::create(['package_id' => $this->package->id, 'name' => 'Included', 'selection_type' => 'all', 'sort_order' => 0]);
    $this->burgerItem = PackageItem::create(['package_group_id' => $fixed->id, 'product_id' => $this->burger->id, 'quantity' => 2, 'price_adjustment' => 0]);

    $sides = PackageGroup::create(['package_id' => $this->package->id, 'name' => 'Side', 'selection_type' => 'choose_one', 'min_selections' => 1, 'max_selections' => 1, 'sort_order' => 1]);
    $this->friesOption = PackageItem::create(['package_group_id' => $sides->id, 'product_id' => $this->fries->id, 'quantity' => 1, 'price_adjustment' => 10]);

    $drinks = PackageGroup::create(['package_id' => $this->package->id, 'name' => 'Drink', 'selection_type' => 'choose_one', 'min_selections' => 1, 'max_selections' => 1, 'sort_order' => 2]);
    $this->sodaOption = PackageItem::create(['package_group_id' => $drinks->id, 'product_id' => $this->soda->id, 'variant_mode' => 'customer_choice', 'quantity' => 1, 'price_adjustment' => 0]);

    $this->payload = fn (array $overrides = []): array => array_merge([
        'type' => 'package',
        'quantity' => 2,
        'package_id' => $this->package->id,
        'selections' => [
            ['package_item_id' => $this->friesOption->id, 'quantity' => 1],
            ['package_item_id' => $this->sodaOption->id, 'quantity' => 1, 'variant_id' => $this->largeSoda->id],
        ],
    ], $overrides);
});

it('adds a configured combo through the cart API with server-side pricing and components', function (): void {
    $response = $this->actingAs($this->user)->postJson('/api/cart/items', ($this->payload)());

    $response->assertStatus(201)
        ->assertJsonPath('data.items.0.type', 'package')
        ->assertJsonPath('data.items.0.reference_id', $this->package->id)
        ->assertJsonPath('data.items.0.product_id', null)
        ->assertJsonPath('data.items.0.product.name', 'Family Box');

    // 150 base + 10 (fries upcharge) + 5 (large soda delta over the 20 base) = 165 per combo, × 2.
    expect((float) $response->json('data.items.0.price'))->toBe(165.0)
        ->and((float) $response->json('data.items.0.subtotal'))->toBe(330.0);

    $components = collect($response->json('data.items.0.components'));
    expect($components)->toHaveCount(3)
        ->and($components->firstWhere('product_id', $this->burger->id)['quantity'])->toEqual(2)
        ->and($components->firstWhere('product_id', $this->soda->id)['variant_id'])->toBe($this->largeSoda->id);
});

it('merges an identical combo into the existing line', function (): void {
    $this->actingAs($this->user)->postJson('/api/cart/items', ($this->payload)())->assertStatus(201);
    $response = $this->actingAs($this->user)->postJson('/api/cart/items', ($this->payload)(['quantity' => 1]))->assertStatus(201);

    expect($response->json('data.items'))->toHaveCount(1)
        ->and((float) $response->json('data.items.0.quantity'))->toBe(3.0);
});

it('rejects a selection that breaks the package rules', function (): void {
    $this->actingAs($this->user)->postJson('/api/cart/items', ($this->payload)([
        'selections' => [['package_item_id' => $this->friesOption->id, 'quantity' => 1]],
    ]))->assertStatus(422);

    expect(app(CartServiceInterface::class)->getLines($this->user))->toBe([]);
});

it('ignores client-sent prices and reprices from the live catalog', function (): void {
    $this->actingAs($this->user)->postJson('/api/cart/items', ($this->payload)(['price' => 1, 'unit_price' => 1]))->assertStatus(201);

    $this->package->update(['price' => 200]);

    $lines = app(CartServiceInterface::class)->getLines($this->user);
    expect($lines[0]->unitPrice)->toBe(215.0);
});

it('blocks checkout once the combo is no longer sellable', function (): void {
    $this->actingAs($this->user)->postJson('/api/cart/items', ($this->payload)())->assertStatus(201);
    $lines = app(CartServiceInterface::class)->getLines($this->user);

    $context = new OrderValidationContext(new PlaceOrderData(user: $this->user, branchId: 1, paymentMethod: PaymentMethod::COD), $lines);
    $pipe = app(ValidatePackageLinesPipe::class);

    expect($pipe->handle($context, fn () => 'passed'))->toBe('passed');

    $this->fries->update(['is_active' => false]);

    expect(fn () => $pipe->handle($context, fn () => 'passed'))->toThrow(OrderValidationException::class);
});

it('turns the cart combo into an order snapshot that survives catalog edits', function (): void {
    $this->actingAs($this->user)->postJson('/api/cart/items', ($this->payload)())->assertStatus(201);
    $lines = app(CartServiceInterface::class)->getLines($this->user);

    $branch = Branch::factory()->create();
    $area = Area::factory()->create(['governorate_id' => Governorate::factory()->create()->id]);
    $address = Address::factory()->create(['user_id' => $this->user->id, 'area_id' => $area->id]);

    $order = app(OrderRepository::class)->createOrderWithItems(
        new PlaceOrderData(user: $this->user, branchId: $branch->id, paymentMethod: PaymentMethod::COD, addressId: $address->id),
        $lines,
        new OrderTotals(subTotal: 330.0, discount: 0.0, tax: 0.0, service: 0.0, deliveryFee: 0.0, total: 330.0)
    );

    $item = $order->items()->with('components')->first();
    expect($item->line_type)->toBe('package')
        ->and((int) $item->line_reference_id)->toBe($this->package->id)
        ->and($item->product_id)->toBeNull()
        ->and((float) $item->unit_price)->toBe(165.0)
        ->and($item->components)->toHaveCount(3);

    $this->soda->update(['name' => 'Renamed Soda']);
    $this->package->update(['price' => 999]);

    $item->refresh()->load('components');
    expect($item->components->pluck('product_name'))->toContain('Soda')
        ->and((float) $item->unit_price)->toBe(165.0);
});

it('supports extras and notes on an all group component end-to-end through the cart API', function (): void {
    $extraOption = App\Plugins\CatalogProducts\src\Models\ExtraOption::create([
        'name' => 'Burger Extras',
        'is_active' => true,
        'min_selections' => 0,
        'max_selections' => 2,
        'allow_multiple' => true,
    ]);
    $extraCheese = App\Plugins\CatalogProducts\src\Models\ExtraOptionItem::create([
        'extra_option_id' => $extraOption->id,
        'name' => 'Cheddar Cheese',
        'name_ar' => 'جبنة شيدر',
        'price' => 8.0,
        'allow_quantity' => true,
    ]);
    $this->burger->update(['extra_option_id' => $extraOption->id]);
    $this->burgerItem->update(['allow_extras' => true]);

    $payload = ($this->payload)([
        'quantity' => 1,
        'selections' => [
            [
                'package_item_id' => $this->burgerItem->id,
                'extras' => [['id' => $extraCheese->id, 'quantity' => 1]],
                'notes' => 'Extra pickles, no mustard',
            ],
            ['package_item_id' => $this->friesOption->id, 'quantity' => 1],
            ['package_item_id' => $this->sodaOption->id, 'quantity' => 1, 'variant_id' => $this->largeSoda->id],
        ],
    ]);

    $response = $this->actingAs($this->user)->postJson('/api/cart/items', $payload);

    $response->assertStatus(201);
    // Base 150 + fries upcharge 10 + large soda 5 + burger extra 8 = 173.0 for 1 combo
    expect((float) $response->json('data.items.0.price'))->toBe(173.0)
        ->and((float) $response->json('data.items.0.subtotal'))->toBe(173.0);

    $components = collect($response->json('data.items.0.components'));
    $burgerComp = $components->firstWhere('product_id', $this->burger->id);
    expect($burgerComp)->not->toBeNull()
        ->and($burgerComp['notes'])->toBe('Extra pickles, no mustard')
        ->and($burgerComp['extras'])->toHaveCount(1)
        ->and($burgerComp['extras'][0]['id'])->toBe($extraCheese->id)
        ->and($burgerComp['extras'][0]['name'])->toBe('Cheddar Cheese')
        ->and((float) $burgerComp['extras'][0]['price'])->toBe(8.0)
        ->and($burgerComp['extras'][0]['quantity'])->toBe(1);
});
