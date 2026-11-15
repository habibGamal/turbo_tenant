<?php

declare(strict_types=1);

use App\Models\User;
use App\Plugins\CartEngine\src\DTOs\CartLine;
use App\Plugins\CartEngine\src\DTOs\CartLineComponent;
use App\Plugins\CartEngine\src\DTOs\CartLineExtra;
use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\ExtraOption;
use App\Plugins\CatalogProducts\src\Models\ExtraOptionItem;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use App\Plugins\CouponsPromotions\src\Enums\ApplicableTo;
use App\Plugins\LocationsDelivery\src\Models\Address;
use App\Plugins\LocationsDelivery\src\Models\Area;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\LocationsDelivery\src\Models\Governorate;
use App\Plugins\OrderManagement\src\DTOs\OrderTotals;
use App\Plugins\OrderManagement\src\DTOs\PlaceOrderData;
use App\Plugins\OrderManagement\src\Enums\PaymentMethod;
use App\Plugins\OrderManagement\src\Filament\Resources\Orders\Pages\ViewOrder;
use App\Plugins\OrderManagement\src\Http\Resources\OrderItemResource;
use App\Plugins\OrderManagement\src\Models\OrderItemComponent;
use App\Plugins\OrderManagement\src\Pipelines\Context\OrderValidationContext;
use App\Plugins\OrderManagement\src\Pipelines\Pipes\Validation\ValidateProductsActivePipe;
use App\Plugins\OrderManagement\src\Services\Repositories\OrderRepository;
use Illuminate\Http\Request;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->user = User::factory()->create(['is_admin' => true]);
    $this->actingAs($this->user);

    $this->branch = Branch::factory()->create();
    $governorate = Governorate::factory()->create();
    $area = Area::factory()->create(['governorate_id' => $governorate->id]);
    $this->address = Address::factory()->create([
        'user_id' => $this->user->id,
        'area_id' => $area->id,
    ]);
});

function createCompositeCartLine(Product $p1, ProductVariant $v1, Product $p2): CartLine
{
    return new CartLine(
        itemId: 1,
        productId: null,
        categoryId: null,
        variantId: null,
        weightOptionValueId: null,
        weightMultiplier: 1,
        quantity: 1.0,
        unitPrice: 150.00,
        extrasUnitTotal: 0.0,
        subtotal: 150.00,
        productName: 'Family Combo',
        productNameAr: 'باقة العائلة',
        variantName: null,
        variantNameAr: null,
        weightLabel: null,
        notes: 'Extra ketchup please',
        extras: [],
        productImage: 'https://example.com/combo.jpg',
        sellByWeight: false,
        weightValue: null,
        type: 'package',
        referenceId: 42,
        components: [
            new CartLineComponent(
                sourceId: 101,
                groupName: 'Burgers',
                groupNameAr: 'برجر',
                productId: $p1->id,
                variantId: $v1->id,
                productName: 'Original Burger',
                productNameAr: 'برجر أصلي',
                variantName: 'Double',
                variantNameAr: 'دبل',
                quantity: 2.0,
                unitPriceAdjustment: 10.00,
                sortOrder: 1
            ),
            new CartLineComponent(
                sourceId: 102,
                groupName: 'Drinks',
                groupNameAr: 'مشروبات',
                productId: $p2->id,
                variantId: null,
                productName: 'Cola Can',
                productNameAr: 'كانز كولا',
                variantName: null,
                variantNameAr: null,
                quantity: 1.0,
                unitPriceAdjustment: 0.00,
                sortOrder: 2
            ),
        ],
        couponEligible: true
    );
}

describe('Composite Orders & OrderManagement', function () {
    it('creates snapshot rows with names for composite order lines', function () {
        $p1 = Product::factory()->create(['name' => 'Original Burger', 'name_ar' => 'برجر أصلي']);
        $v1 = ProductVariant::factory()->create(['product_id' => $p1->id, 'name' => 'Double', 'name_ar' => 'دبل']);
        $p2 = Product::factory()->create(['name' => 'Cola Can', 'name_ar' => 'كانز كولا']);

        $line = createCompositeCartLine($p1, $v1, $p2);

        $totals = new OrderTotals(subTotal: 150.0, discount: 0.0, tax: 0.0, service: 0.0, deliveryFee: 0.0, total: 150.0);
        $data = new PlaceOrderData(user: $this->user, branchId: $this->branch->id, paymentMethod: PaymentMethod::COD, addressId: $this->address->id);

        $repo = app(OrderRepository::class);
        $order = $repo->createOrderWithItems($data, [$line], $totals);

        expect($order->items)->toHaveCount(1);
        $item = $order->items->first();

        expect($item->line_type)->toBe('package')
            ->and($item->line_reference_id)->toBe(42)
            ->and($item->product_id)->toBeNull()
            ->and($item->product_name)->toBe('Family Combo')
            ->and($item->product_name_ar)->toBe('باقة العائلة');

        $components = $item->components;
        expect($components)->toHaveCount(2);

        $c1 = $components->first();
        expect($c1->product_id)->toBe($p1->id)
            ->and($c1->variant_id)->toBe($v1->id)
            ->and($c1->group_name)->toBe('Burgers')
            ->and($c1->group_name_ar)->toBe('برجر')
            ->and($c1->product_name)->toBe('Original Burger')
            ->and($c1->product_name_ar)->toBe('برجر أصلي')
            ->and($c1->variant_name)->toBe('Double')
            ->and($c1->variant_name_ar)->toBe('دبل')
            ->and((float) $c1->quantity)->toBe(2.0)
            ->and((float) $c1->unit_price_adjustment)->toBe(10.0)
            ->and($c1->sort_order)->toBe(1);

        $c2 = $components->last();
        expect($c2->product_id)->toBe($p2->id)
            ->and($c2->variant_id)->toBeNull()
            ->and($c2->product_name)->toBe('Cola Can')
            ->and((float) $c2->quantity)->toBe(1.0)
            ->and((float) $c2->unit_price_adjustment)->toBe(0.0)
            ->and($c2->sort_order)->toBe(2);
    });

    it('preserves order item snapshot names when products are later renamed or deleted', function () {
        $p1 = Product::factory()->create(['name' => 'Original Burger', 'name_ar' => 'برجر أصلي']);
        $v1 = ProductVariant::factory()->create(['product_id' => $p1->id, 'name' => 'Double', 'name_ar' => 'دبل']);
        $p2 = Product::factory()->create(['name' => 'Cola Can', 'name_ar' => 'كانز كولا']);

        $line = createCompositeCartLine($p1, $v1, $p2);
        $totals = new OrderTotals(subTotal: 150.0, discount: 0.0, tax: 0.0, service: 0.0, deliveryFee: 0.0, total: 150.0);
        $data = new PlaceOrderData(user: $this->user, branchId: $this->branch->id, paymentMethod: PaymentMethod::COD, addressId: $this->address->id);

        $repo = app(OrderRepository::class);
        $order = $repo->createOrderWithItems($data, [$line], $totals);

        // Rename product in live catalog
        $p1->update(['name' => 'Renamed Mega Burger', 'name_ar' => 'برجر معدل']);
        $v1->update(['name' => 'Triple', 'name_ar' => 'تربل']);

        $c1 = OrderItemComponent::where('product_id', $p1->id)->first();
        expect($c1->product_name)->toBe('Original Burger')
            ->and($c1->product_name_ar)->toBe('برجر أصلي')
            ->and($c1->variant_name)->toBe('Double')
            ->and($c1->variant_name_ar)->toBe('دبل');
    });

    it('leaves product-line orders completely unchanged', function () {
        $p = Product::factory()->create(['name' => 'Simple Fries']);
        $line = new CartLine(
            itemId: 2,
            productId: $p->id,
            categoryId: null,
            variantId: null,
            weightOptionValueId: null,
            weightMultiplier: 1,
            quantity: 1.0,
            unitPrice: 30.00,
            extrasUnitTotal: 0.0,
            subtotal: 30.00,
            productName: 'Simple Fries',
            productNameAr: null,
            variantName: null,
            variantNameAr: null,
            weightLabel: null,
            notes: null
        );

        $totals = new OrderTotals(subTotal: 30.0, discount: 0.0, tax: 0.0, service: 0.0, deliveryFee: 0.0, total: 30.0);
        $data = new PlaceOrderData(user: $this->user, branchId: $this->branch->id, paymentMethod: PaymentMethod::COD, addressId: $this->address->id);

        $repo = app(OrderRepository::class);
        $order = $repo->createOrderWithItems($data, [$line], $totals);

        $item = $order->items->first();
        expect($item->line_type)->toBe('product')
            ->and($item->line_reference_id)->toBeNull()
            ->and($item->product_id)->toBe($p->id)
            ->and($item->components)->toHaveCount(0);
    });

    it('formats OrderItemResource with type, reference_id, and component keys conforming to contract', function () {
        $p1 = Product::factory()->create(['name' => 'Burger']);
        $v1 = ProductVariant::factory()->create(['product_id' => $p1->id, 'name' => 'Double']);
        $p2 = Product::factory()->create(['name' => 'Cola']);

        $line = createCompositeCartLine($p1, $v1, $p2);
        $totals = new OrderTotals(subTotal: 150.0, discount: 0.0, tax: 0.0, service: 0.0, deliveryFee: 0.0, total: 150.0);
        $data = new PlaceOrderData(user: $this->user, branchId: $this->branch->id, paymentMethod: PaymentMethod::COD, addressId: $this->address->id);

        $repo = app(OrderRepository::class);
        $order = $repo->createOrderWithItems($data, [$line], $totals);
        $item = $order->items->first();

        $resource = (new OrderItemResource($item))->resolve(Request::create('/api/orders/1'));

        expect($resource)->toHaveKeys(['id', 'order_id', 'type', 'reference_id', 'product_id', 'components'])
            ->and($resource['type'])->toBe('package')
            ->and($resource['reference_id'])->toBe(42)
            ->and($resource['product_id'])->toBeNull()
            ->and($resource['components'])->toBeArray()
            ->and($resource['components'])->toHaveCount(2)
            ->and($resource['components'][0])->toHaveKeys([
                'source_id',
                'group_name',
                'group_name_ar',
                'product_id',
                'variant_id',
                'product_name',
                'product_name_ar',
                'variant_name',
                'variant_name_ar',
                'quantity',
                'unit_price_adjustment',
            ])
            ->and($resource['components'][0]['product_name'])->toBe('Original Burger')
            ->and($resource['components'][0]['unit_price_adjustment'])->toBe(10.0);
    });

    it('renders composite item badge and components in Filament ViewOrder', function () {
        $p1 = Product::factory()->create(['name' => 'Burger', 'name_ar' => 'برجر']);
        $v1 = ProductVariant::factory()->create(['product_id' => $p1->id, 'name' => 'Double', 'name_ar' => 'دبل']);
        $p2 = Product::factory()->create(['name' => 'Cola', 'name_ar' => 'كولا']);

        $line = createCompositeCartLine($p1, $v1, $p2);
        $totals = new OrderTotals(subTotal: 150.0, discount: 0.0, tax: 0.0, service: 0.0, deliveryFee: 0.0, total: 150.0);
        $data = new PlaceOrderData(user: $this->user, branchId: $this->branch->id, paymentMethod: PaymentMethod::COD, addressId: $this->address->id);

        $repo = app(OrderRepository::class);
        $order = $repo->createOrderWithItems($data, [$line], $totals);

        livewire(ViewOrder::class, [
            'record' => $order->id,
        ])
            ->assertOk()
            ->assertSee('Family Combo')
            ->assertSee('باقة')
            ->assertSee('2×');
    });

    it('ignores composite lines with null productId in ValidateProductsActivePipe', function () {
        $inactiveProduct = Product::factory()->create(['is_active' => false]);
        $activeProduct = Product::factory()->create(['is_active' => true]);

        // Composite line has productId null
        $compositeLine = new CartLine(
            itemId: 1,
            productId: null,
            categoryId: null,
            variantId: null,
            weightOptionValueId: null,
            weightMultiplier: 1,
            quantity: 1.0,
            unitPrice: 50.0,
            extrasUnitTotal: 0.0,
            subtotal: 50.0,
            productName: 'Combo Line',
            productNameAr: null,
            variantName: null,
            variantNameAr: null,
            weightLabel: null,
            notes: null,
            type: 'package',
            referenceId: 99
        );

        $activeProductLine = new CartLine(
            itemId: 2,
            productId: $activeProduct->id,
            categoryId: null,
            variantId: null,
            weightOptionValueId: null,
            weightMultiplier: 1,
            quantity: 1.0,
            unitPrice: 20.0,
            extrasUnitTotal: 0.0,
            subtotal: 20.0,
            productName: 'Active Prod',
            productNameAr: null,
            variantName: null,
            variantNameAr: null,
            weightLabel: null,
            notes: null
        );

        $placeOrderData = new PlaceOrderData(user: $this->user, branchId: $this->branch->id, paymentMethod: PaymentMethod::COD);
        $context = new OrderValidationContext($placeOrderData, [$compositeLine, $activeProductLine]);

        $pipe = new ValidateProductsActivePipe();
        $passed = false;
        $pipe->handle($context, function ($ctx) use (&$passed) {
            $passed = true;

            return $ctx;
        });

        expect($passed)->toBeTrue();
    });

    it('ensures ApplicableTo never matches a composite line for PRODUCTS or CATEGORIES', function () {
        $category = Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id]);

        $compositeLine = new CartLine(
            itemId: 1,
            productId: null,
            categoryId: null,
            variantId: null,
            weightOptionValueId: null,
            weightMultiplier: 1,
            quantity: 1.0,
            unitPrice: 100.0,
            extrasUnitTotal: 0.0,
            subtotal: 100.0,
            productName: 'Package Line',
            productNameAr: null,
            variantName: null,
            variantNameAr: null,
            weightLabel: null,
            notes: null,
            type: 'package',
            referenceId: 1
        );

        $conditionsProducts = [
            'applicable_to' => [
                'type' => 'products',
                'product_ids' => [$product->id],
            ],
        ];

        $conditionsCategories = [
            'applicable_to' => [
                'type' => 'categories',
                'category_ids' => [$category->id],
            ],
        ];

        expect(ApplicableTo::PRODUCTS->matches($compositeLine, $conditionsProducts))->toBeFalse()
            ->and(ApplicableTo::CATEGORIES->matches($compositeLine, $conditionsCategories))->toBeFalse()
            ->and(ApplicableTo::ALL->matches($compositeLine, []))->toBeTrue();
    });

    it('snapshots component extras and notes and preserves snapshot after extra is renamed', function () {
        $product = Product::factory()->create(['name' => 'Burger Component', 'name_ar' => 'برجر']);
        $extraOption = ExtraOption::factory()->create();
        $extraItem = ExtraOptionItem::factory()->create([
            'extra_option_id' => $extraOption->id,
            'name' => 'Original Cheese',
            'name_ar' => 'جبنة أصلية',
            'price' => 15.0,
        ]);

        $line = new CartLine(
            itemId: 10,
            productId: null,
            categoryId: null,
            variantId: null,
            weightOptionValueId: null,
            weightMultiplier: 1,
            quantity: 1.0,
            unitPrice: 100.0,
            extrasUnitTotal: 30.0,
            subtotal: 130.0,
            productName: 'Combo with Extras',
            productNameAr: 'باقة مع إضافات',
            variantName: null,
            variantNameAr: null,
            weightLabel: null,
            notes: null,
            extras: [],
            type: 'package',
            referenceId: 88,
            components: [
                new CartLineComponent(
                    sourceId: 501,
                    groupName: 'Meals',
                    groupNameAr: 'وجبات',
                    productId: $product->id,
                    variantId: null,
                    productName: 'Burger Component',
                    productNameAr: 'برجر',
                    variantName: null,
                    variantNameAr: null,
                    quantity: 1.0,
                    unitPriceAdjustment: 0.0,
                    sortOrder: 1,
                    extras: [
                        new CartLineExtra(
                            extraOptionItemId: $extraItem->id,
                            name: 'Original Cheese',
                            nameAr: 'جبنة أصلية',
                            unitPrice: 15.0,
                            quantity: 2
                        ),
                    ],
                    notes: 'No pickles please'
                ),
            ],
            couponEligible: true
        );

        $totals = new OrderTotals(subTotal: 130.0, discount: 0.0, tax: 0.0, service: 0.0, deliveryFee: 0.0, total: 130.0);
        $data = new PlaceOrderData(user: $this->user, branchId: $this->branch->id, paymentMethod: PaymentMethod::COD, addressId: $this->address->id);

        $repo = app(OrderRepository::class);
        $order = $repo->createOrderWithItems($data, [$line], $totals);

        $orderItem = $order->items->first();
        $c = $orderItem->components->first();

        expect($c->notes)->toBe('No pickles please')
            ->and($c->extras)->toBeArray()
            ->and($c->extras)->toHaveCount(1)
            ->and($c->extras[0]['extra_option_item_id'])->toBe($extraItem->id)
            ->and($c->extras[0]['name'])->toBe('Original Cheese')
            ->and($c->extras[0]['name_ar'])->toBe('جبنة أصلية')
            ->and((float) $c->extras[0]['price'])->toBe(15.0)
            ->and($c->extras[0]['quantity'])->toBe(2);

        // Rename the extra in the live catalog
        $extraItem->update(['name' => 'Renamed Cheese', 'name_ar' => 'جبنة معدلة']);

        $cFresh = OrderItemComponent::find($c->id);
        expect($cFresh->extras[0]['name'])->toBe('Original Cheese')
            ->and($cFresh->extras[0]['name_ar'])->toBe('جبنة أصلية');
    });

    it('includes extras and notes in OrderItemResource components', function () {
        $product = Product::factory()->create(['name' => 'Burger', 'name_ar' => 'برجر']);
        $extraItem = ExtraOptionItem::factory()->create(['name' => 'Sauce', 'name_ar' => 'صوص']);

        $line = new CartLine(
            itemId: 11,
            productId: null,
            categoryId: null,
            variantId: null,
            weightOptionValueId: null,
            weightMultiplier: 1,
            quantity: 1.0,
            unitPrice: 50.0,
            extrasUnitTotal: 10.0,
            subtotal: 60.0,
            productName: 'Combo',
            productNameAr: 'باقة',
            variantName: null,
            variantNameAr: null,
            weightLabel: null,
            notes: null,
            extras: [],
            type: 'package',
            referenceId: 89,
            components: [
                new CartLineComponent(
                    sourceId: 502,
                    groupName: 'Meals',
                    groupNameAr: 'وجبات',
                    productId: $product->id,
                    variantId: null,
                    productName: 'Burger',
                    productNameAr: 'برجر',
                    variantName: null,
                    variantNameAr: null,
                    quantity: 1.0,
                    unitPriceAdjustment: 0.0,
                    sortOrder: 1,
                    extras: [
                        new CartLineExtra(
                            extraOptionItemId: $extraItem->id,
                            name: 'Sauce',
                            nameAr: 'صوص',
                            unitPrice: 10.0,
                            quantity: 1
                        ),
                    ],
                    notes: 'Extra crispy'
                ),
            ],
            couponEligible: true
        );

        $totals = new OrderTotals(subTotal: 60.0, discount: 0.0, tax: 0.0, service: 0.0, deliveryFee: 0.0, total: 60.0);
        $data = new PlaceOrderData(user: $this->user, branchId: $this->branch->id, paymentMethod: PaymentMethod::COD, addressId: $this->address->id);

        $repo = app(OrderRepository::class);
        $order = $repo->createOrderWithItems($data, [$line], $totals);

        $resource = (new OrderItemResource($order->items->first()))->resolve(Request::create('/api/orders/1'));

        expect($resource['components'][0])->toHaveKeys(['extras', 'notes'])
            ->and($resource['components'][0]['notes'])->toBe('Extra crispy')
            ->and($resource['components'][0]['extras'])->toBe([
                [
                    'id' => $extraItem->id,
                    'name' => 'Sauce',
                    'name_ar' => 'صوص',
                    'price' => 10.0,
                    'quantity' => 1,
                ],
            ]);
    });

    it('renders component extras and notes in Filament ViewOrder', function () {
        app()->setLocale('ar');
        $p = Product::factory()->create(['name' => 'Burger', 'name_ar' => 'برجر']);
        $extraItem = ExtraOptionItem::factory()->create(['name' => 'Extra Cheese', 'name_ar' => 'جبنة إضافية']);

        $line = new CartLine(
            itemId: 12,
            productId: null,
            categoryId: null,
            variantId: null,
            weightOptionValueId: null,
            weightMultiplier: 1,
            quantity: 1.0,
            unitPrice: 70.0,
            extrasUnitTotal: 20.0,
            subtotal: 90.0,
            productName: 'Combo ViewOrder',
            productNameAr: 'باقة العرض',
            variantName: null,
            variantNameAr: null,
            weightLabel: null,
            notes: null,
            extras: [],
            type: 'package',
            referenceId: 90,
            components: [
                new CartLineComponent(
                    sourceId: 503,
                    groupName: 'Meals',
                    groupNameAr: 'وجبات',
                    productId: $p->id,
                    variantId: null,
                    productName: 'Burger',
                    productNameAr: 'برجر',
                    variantName: null,
                    variantNameAr: null,
                    quantity: 1.0,
                    unitPriceAdjustment: 0.0,
                    sortOrder: 1,
                    extras: [
                        new CartLineExtra(
                            extraOptionItemId: $extraItem->id,
                            name: 'Extra Cheese',
                            nameAr: 'جبنة إضافية',
                            unitPrice: 10.0,
                            quantity: 2
                        ),
                    ],
                    notes: 'بدون مخلل'
                ),
            ],
            couponEligible: true
        );

        $totals = new OrderTotals(subTotal: 90.0, discount: 0.0, tax: 0.0, service: 0.0, deliveryFee: 0.0, total: 90.0);
        $data = new PlaceOrderData(user: $this->user, branchId: $this->branch->id, paymentMethod: PaymentMethod::COD, addressId: $this->address->id);

        $repo = app(OrderRepository::class);
        $order = $repo->createOrderWithItems($data, [$line], $totals);

        livewire(ViewOrder::class, [
            'record' => $order->id,
        ])
            ->assertOk()
            ->assertSee('+ جبنة إضافية ×2')
            ->assertSee('بدون مخلل');
    });
});
