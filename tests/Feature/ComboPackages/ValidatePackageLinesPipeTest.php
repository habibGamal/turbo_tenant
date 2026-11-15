<?php

declare(strict_types=1);

namespace Tests\Feature\ComboPackages;

use App\Models\User;
use App\Plugins\CartEngine\src\DTOs\CartLine;
use App\Plugins\CartEngine\src\Models\Cart;
use App\Plugins\CartEngine\src\Models\CartItem;
use App\Plugins\CartEngine\src\Models\CartItemComponent;
use App\Plugins\CartEngine\src\Services\Contracts\CartContextResolverInterface;
use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use App\Plugins\ComboPackages\src\Cart\PackageCartLineHandler;
use App\Plugins\ComboPackages\src\Models\Package;
use App\Plugins\ComboPackages\src\Models\PackageGroup;
use App\Plugins\ComboPackages\src\Models\PackageItem;
use App\Plugins\ComboPackages\src\Pipelines\ValidatePackageLinesPipe;
use App\Plugins\ComboPackages\src\Services\PackageAvailabilityService;
use App\Plugins\ComboPackages\src\Services\PackageLinePricer;
use App\Plugins\ComboPackages\src\Services\PackageSelectionValidator;
use App\Plugins\ComboPackages\src\Services\SellablePackageFilter;
use App\Plugins\OrderManagement\src\DTOs\PlaceOrderData;
use App\Plugins\OrderManagement\src\Enums\PaymentMethod;
use App\Plugins\OrderManagement\src\Exceptions\OrderValidationException;
use App\Plugins\OrderManagement\src\Pipelines\Context\OrderValidationContext;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->category = Category::factory()->create();

    $this->product = Product::factory()->create([
        'category_id' => $this->category->id,
        'base_price' => 50.0,
        'is_active' => true,
    ]);

    $this->variant = ProductVariant::create([
        'product_id' => $this->product->id,
        'name' => 'Regular',
        'price' => 50.0,
        'is_available' => true,
    ]);

    $this->package = Package::factory()->create([
        'price' => 90.0,
        'is_active' => true,
    ]);

    $this->group = PackageGroup::create([
        'package_id' => $this->package->id,
        'selection_type' => 'choose_one',
        'min_selections' => 1,
        'max_selections' => 1,
    ]);

    $this->item = PackageItem::create([
        'package_group_id' => $this->group->id,
        'product_id' => $this->product->id,
        'variant_mode' => 'fixed',
        'quantity' => 1,
        'price_adjustment' => 0.0,
    ]);

    $this->handler = new PackageCartLineHandler(
        new SellablePackageFilter(),
        new PackageSelectionValidator(),
        new PackageLinePricer(),
        app(PackageAvailabilityService::class),
        app(CartContextResolverInterface::class)
    );

    $this->pipe = new ValidatePackageLinesPipe($this->handler, app(PackageAvailabilityService::class));

    $this->cart = Cart::create(['session_id' => 'pipe-test-session']);

    $this->placeOrderData = new PlaceOrderData(
        user: $this->user,
        branchId: 1,
        paymentMethod: PaymentMethod::COD
    );
});

it('passes through product-only carts without inspecting package handler', function (): void {
    $productLine = new CartLine(
        itemId: 101,
        productId: $this->product->id,
        categoryId: $this->category->id,
        variantId: null,
        weightOptionValueId: null,
        weightMultiplier: 1,
        quantity: 2.0,
        unitPrice: 50.0,
        extrasUnitTotal: 0.0,
        subtotal: 100.0,
        productName: 'Regular Product',
        productNameAr: null,
        variantName: null,
        variantNameAr: null,
        weightLabel: null,
        type: 'product'
    );

    $context = new OrderValidationContext($this->placeOrderData, [$productLine]);

    $called = false;
    $result = $this->pipe->handle($context, function (OrderValidationContext $ctx) use (&$called): string {
        $called = true;

        return 'passed';
    });

    expect($called)->toBeTrue()
        ->and($result)->toBe('passed');
});

it('passes through when package cart line is valid and available', function (): void {
    $cartItem = CartItem::create([
        'cart_id' => $this->cart->id,
        'line_type' => 'package',
        'line_reference_id' => $this->package->id,
        'quantity' => 1,
    ]);

    CartItemComponent::create([
        'cart_item_id' => $cartItem->id,
        'source_id' => $this->item->id,
        'product_id' => $this->product->id,
        'quantity' => 1,
        'unit_price_adjustment' => 0.0,
        'sort_order' => 1,
    ]);

    $packageLine = new CartLine(
        itemId: $cartItem->id,
        productId: null,
        categoryId: null,
        variantId: null,
        weightOptionValueId: null,
        weightMultiplier: 1,
        quantity: 1.0,
        unitPrice: 90.0,
        extrasUnitTotal: 0.0,
        subtotal: 90.0,
        productName: 'Combo Package',
        productNameAr: null,
        variantName: null,
        variantNameAr: null,
        weightLabel: null,
        type: 'package',
        referenceId: $this->package->id
    );

    $context = new OrderValidationContext($this->placeOrderData, [$packageLine]);

    $called = false;
    $result = $this->pipe->handle($context, function (OrderValidationContext $ctx) use (&$called): string {
        $called = true;

        return 'passed';
    });

    expect($called)->toBeTrue()
        ->and($result)->toBe('passed');
});

it('throws OrderValidationException with reason when package line is unavailable', function (): void {
    $cartItem = CartItem::create([
        'cart_id' => $this->cart->id,
        'line_type' => 'package',
        'line_reference_id' => $this->package->id,
        'quantity' => 1,
    ]);

    // Deactivate package to make it unavailable
    $this->package->update(['is_active' => false]);

    $packageLine = new CartLine(
        itemId: $cartItem->id,
        productId: null,
        categoryId: null,
        variantId: null,
        weightOptionValueId: null,
        weightMultiplier: 1,
        quantity: 1.0,
        unitPrice: 90.0,
        extrasUnitTotal: 0.0,
        subtotal: 90.0,
        productName: 'Combo Package',
        productNameAr: null,
        variantName: null,
        variantNameAr: null,
        weightLabel: null,
        type: 'package',
        referenceId: $this->package->id
    );

    $context = new OrderValidationContext($this->placeOrderData, [$packageLine]);

    $this->pipe->handle($context, fn () => null);
})->throws(OrderValidationException::class);
