<?php

declare(strict_types=1);

namespace Tests\Feature\ComboPackages;

use App\Models\User;
use App\Plugins\CartEngine\src\DTOs\CartLine;
use App\Plugins\CartEngine\src\Models\Cart;
use App\Plugins\CartEngine\src\Models\CartItem;
use App\Plugins\CartEngine\src\Models\CartItemComponent;
use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\ComboPackages\src\Models\Package;
use App\Plugins\ComboPackages\src\Models\PackageGroup;
use App\Plugins\ComboPackages\src\Models\PackageItem;
use App\Plugins\ComboPackages\src\Pipelines\ValidatePackageLinesPipe;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\OrderManagement\src\DTOs\PlaceOrderData;
use App\Plugins\OrderManagement\src\Enums\OrderStatus;
use App\Plugins\OrderManagement\src\Enums\PaymentMethod;
use App\Plugins\OrderManagement\src\Enums\PaymentStatus;
use App\Plugins\OrderManagement\src\Exceptions\OrderValidationException;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\OrderManagement\src\Models\OrderItem;
use App\Plugins\OrderManagement\src\Pipelines\Context\OrderValidationContext;
use Carbon\Carbon;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->category = Category::factory()->create();

    $this->product = Product::factory()->create([
        'category_id' => $this->category->id,
        'base_price' => 50.0,
        'is_active' => true,
    ]);

    $this->branchAllowed = Branch::factory()->create(['name' => 'Downtown']);
    $this->branchOther = Branch::factory()->create(['name' => 'Airport']);

    $this->package = Package::factory()->create([
        'name' => 'Rules Combo',
        'is_active' => true,
        'price' => 70.0,
        'max_per_order' => 4,
        'daily_limit' => 6,
        'allowed_order_types' => ['web_delivery'],
    ]);

    $this->package->branches()->attach($this->branchAllowed->id);

    $this->group = PackageGroup::create([
        'package_id' => $this->package->id,
        'selection_type' => 'all',
        'sort_order' => 0,
    ]);

    $this->item = PackageItem::create([
        'package_group_id' => $this->group->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
        'price_adjustment' => 0,
    ]);

    $this->pipe = app(ValidatePackageLinesPipe::class);
    $this->cart = Cart::create(['session_id' => 'checkout-pipe-session']);
});

function createPackageLine(Cart $cart, Package $package, int $quantity, int $lineItemId = 100): array
{
    $cartItem = CartItem::create([
        'cart_id' => $cart->id,
        'line_type' => 'package',
        'line_reference_id' => $package->id,
        'quantity' => $quantity,
    ]);

    CartItemComponent::create([
        'cart_item_id' => $cartItem->id,
        'source_id' => $package->groups->first()->items->first()->id,
        'product_id' => $package->groups->first()->items->first()->product_id,
        'quantity' => 1,
        'unit_price_adjustment' => 0,
        'sort_order' => 0,
    ]);

    $line = new CartLine(
        itemId: $cartItem->id,
        productId: null,
        categoryId: null,
        variantId: null,
        weightOptionValueId: null,
        weightMultiplier: 1,
        quantity: (float) $quantity,
        unitPrice: (float) $package->price,
        extrasUnitTotal: 0.0,
        subtotal: (float) ($package->price * $quantity),
        productName: $package->name,
        productNameAr: $package->name_ar,
        variantName: null,
        variantNameAr: null,
        weightLabel: null,
        type: 'package'
    );

    return [$cartItem, $line];
}

it('passes checkout when everything is valid and fine', function (): void {
    Carbon::setTestNow('2026-11-09 13:00:00');

    [$cartItem, $line] = createPackageLine($this->cart, $this->package, 2);

    $placeOrderData = new PlaceOrderData(
        user: $this->user,
        branchId: $this->branchAllowed->id,
        paymentMethod: PaymentMethod::COD,
        type: 'web_delivery'
    );

    $context = new OrderValidationContext($placeOrderData, [$line]);
    $result = $this->pipe->handle($context, fn () => 'success');

    expect($result)->toBe('success');
});

it('rejects checkout when branch is not allowed', function (): void {
    Carbon::setTestNow('2026-11-09 13:00:00');

    [$cartItem, $line] = createPackageLine($this->cart, $this->package, 1);

    $placeOrderData = new PlaceOrderData(
        user: $this->user,
        branchId: $this->branchOther->id, // Not allowed!
        paymentMethod: PaymentMethod::COD,
        type: 'web_delivery'
    );

    $context = new OrderValidationContext($placeOrderData, [$line]);

    expect(fn () => $this->pipe->handle($context, fn () => 'success'))
        ->toThrow(OrderValidationException::class);
});

it('rejects checkout when order type is not allowed', function (): void {
    Carbon::setTestNow('2026-11-09 13:00:00');

    [$cartItem, $line] = createPackageLine($this->cart, $this->package, 1);

    $placeOrderData = new PlaceOrderData(
        user: $this->user,
        branchId: $this->branchAllowed->id,
        paymentMethod: PaymentMethod::COD,
        type: 'web_takeaway' // Package only allows web_delivery!
    );

    $context = new OrderValidationContext($placeOrderData, [$line]);

    expect(fn () => $this->pipe->handle($context, fn () => 'success'))
        ->toThrow(OrderValidationException::class);
});

it('rejects checkout when sum of quantity across multiple lines exceeds max_per_order', function (): void {
    Carbon::setTestNow('2026-11-09 13:00:00');

    // Two lines of the same package: qty 3 and qty 2 -> sum = 5 > max_per_order (4)
    [$item1, $line1] = createPackageLine($this->cart, $this->package, 3);
    [$item2, $line2] = createPackageLine($this->cart, $this->package, 2);

    $placeOrderData = new PlaceOrderData(
        user: $this->user,
        branchId: $this->branchAllowed->id,
        paymentMethod: PaymentMethod::COD,
        type: 'web_delivery'
    );

    $context = new OrderValidationContext($placeOrderData, [$line1, $line2]);

    expect(fn () => $this->pipe->handle($context, fn () => 'success'))
        ->toThrow(OrderValidationException::class);
});

it('rejects checkout when total quantity exceeds remaining daily limit', function (): void {
    Carbon::setTestNow('2026-11-09 13:00:00');

    // 5 already sold today out of daily_limit 6
    $prevOrder = Order::create([
        'order_number' => 'ORD-PIPE-LIMIT',
        'user_id' => $this->user->id,
        'branch_id' => $this->branchAllowed->id,
        'status' => OrderStatus::DELIVERED->value,
        'payment_status' => PaymentStatus::COMPLETED->value,
        'type' => 'web_delivery',
        'created_at' => now(),
    ]);
    OrderItem::create([
        'order_id' => $prevOrder->id,
        'line_type' => 'package',
        'line_reference_id' => $this->package->id,
        'product_name' => 'Rules Combo',
        'quantity' => 5,
        'unit_price' => 70,
        'total' => 350,
    ]);

    // Remaining today is 1. Cart has qty 2 -> rejected!
    [$cartItem, $line] = createPackageLine($this->cart, $this->package, 2);

    $placeOrderData = new PlaceOrderData(
        user: $this->user,
        branchId: $this->branchAllowed->id,
        paymentMethod: PaymentMethod::COD,
        type: 'web_delivery'
    );

    $context = new OrderValidationContext($placeOrderData, [$line]);

    expect(fn () => $this->pipe->handle($context, fn () => 'success'))
        ->toThrow(OrderValidationException::class);
});
