<?php

declare(strict_types=1);

namespace Tests\Unit\CartEngine;

use App\Models\User;
use App\Plugins\CartEngine\src\Contracts\CartLineTypeHandler;
use App\Plugins\CartEngine\src\DTOs\CartLine;
use App\Plugins\CartEngine\src\DTOs\CartLineComponent;
use App\Plugins\CartEngine\src\DTOs\PreparedCartLine;
use App\Plugins\CartEngine\src\Models\Cart;
use App\Plugins\CartEngine\src\Models\CartItem;
use App\Plugins\CartEngine\src\Services\Calculators\CartPriceCalculator;
use App\Plugins\CartEngine\src\Services\CartLineTypeRegistry;
use App\Plugins\CartEngine\src\Services\CartService;
use App\Plugins\CartEngine\src\Services\Contracts\CartRepositoryInterface;
use App\Plugins\CatalogProducts\src\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

final class FakeUnitTestCartLineHandler implements CartLineTypeHandler
{
    public bool $shouldThrowOnBuild = false;

    public function pluginId(): string
    {
        return 'cart-engine';
    }

    public function type(): string
    {
        return 'test-bundle';
    }

    public function prepare(array $payload): PreparedCartLine
    {
        $packageId = (int) ($payload['package_id'] ?? 1);
        $signature = (string) ($payload['signature'] ?? sha1((string) $packageId));

        $components = [];
        if (! empty($payload['components'])) {
            foreach ($payload['components'] as $c) {
                $components[] = new CartLineComponent(
                    sourceId: $c['source_id'] ?? 1,
                    groupName: 'Group',
                    groupNameAr: 'مجموعة',
                    productId: (int) $c['product_id'],
                    variantId: isset($c['variant_id']) ? (int) $c['variant_id'] : null,
                    productName: 'Component Product',
                    productNameAr: 'منتج المكون',
                    variantName: null,
                    variantNameAr: null,
                    quantity: (float) ($c['quantity'] ?? 1.0),
                    unitPriceAdjustment: (float) ($c['unit_price_adjustment'] ?? 0.0),
                    sortOrder: (int) ($c['sort_order'] ?? 0),
                );
            }
        }

        return new PreparedCartLine(
            type: 'test-bundle',
            referenceId: $packageId,
            signature: $signature,
            components: $components,
        );
    }

    public function buildLine(CartItem $item): CartLine
    {
        if ($this->shouldThrowOnBuild) {
            throw new RuntimeException('Catalog item no longer available.');
        }

        $components = [];
        foreach ($item->components as $c) {
            $components[] = new CartLineComponent(
                sourceId: $c->source_id,
                groupName: 'Group',
                groupNameAr: 'مجموعة',
                productId: (int) $c->product_id,
                variantId: $c->variant_id !== null ? (int) $c->variant_id : null,
                productName: $c->product?->name ?? 'Component Product',
                productNameAr: $c->product?->name_ar ?? 'منتج المكون',
                variantName: null,
                variantNameAr: null,
                quantity: (float) $c->quantity,
                unitPriceAdjustment: (float) $c->unit_price_adjustment,
                sortOrder: (int) $c->sort_order,
            );
        }

        $unitPrice = 75.0;
        $quantity = (float) $item->quantity;

        return new CartLine(
            itemId: (int) $item->id,
            productId: null,
            categoryId: null,
            variantId: null,
            weightOptionValueId: null,
            weightMultiplier: 1,
            quantity: $quantity,
            unitPrice: $unitPrice,
            extrasUnitTotal: 0.0,
            subtotal: $unitPrice * $quantity,
            productName: 'Test Package',
            productNameAr: 'باقة تجريبية',
            variantName: null,
            variantNameAr: null,
            weightLabel: null,
            notes: $item->notes,
            extras: [],
            productImage: null,
            sellByWeight: false,
            weightValue: null,
            type: 'test-bundle',
            referenceId: (int) $item->line_reference_id,
            components: $components,
            couponEligible: true,
        );
    }

    public function unavailableReason(CartItem $item): ?string
    {
        return null;
    }
}

final class CompositeCartLineUnitTest extends TestCase
{
    use RefreshDatabase;

    private CartService $cartService;

    private CartRepositoryInterface $repository;

    private FakeUnitTestCartLineHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = new FakeUnitTestCartLineHandler();
        $this->app->instance(FakeUnitTestCartLineHandler::class, $this->handler);
        $this->app->tag([FakeUnitTestCartLineHandler::class], 'cart-engine.line-types');

        $this->cartService = $this->app->make(CartService::class);
        $this->repository = $this->app->make(CartRepositoryInterface::class);
    }

    public function test_registry_resolves_handler_and_returns_null_for_unknown(): void
    {
        $registry = $this->app->make(CartLineTypeRegistry::class);

        $handler = $registry->get('test-bundle');
        $this->assertNotNull($handler);
        $this->assertSame('test-bundle', $handler->type());

        $this->assertNull($registry->get('unknown_type'));
    }

    public function test_add_composite_item_persists_to_repository_and_returns_lines(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_active' => true]);

        $lines = $this->cartService->addCompositeItem(
            user: $user,
            type: 'test-bundle',
            payload: [
                'package_id' => 15,
                'signature' => 'sig_test_1',
                'components' => [
                    ['source_id' => 10, 'product_id' => $product->id, 'quantity' => 1],
                ],
            ],
            quantity: 2,
            notes: 'Test notes'
        );

        $this->assertCount(1, $lines);
        $this->assertSame('test-bundle', $lines[0]->type);
        $this->assertSame(15, $lines[0]->referenceId);
        $this->assertSame(2.0, $lines[0]->quantity);
        $this->assertSame('Test notes', $lines[0]->notes);
        $this->assertCount(1, $lines[0]->components);
        $this->assertSame($product->id, $lines[0]->components[0]->productId);

        // Verify countCartItems reflects the item
        $cart = Cart::where('user_id', $user->id)->first();
        $this->assertSame(1, $this->repository->countCartItems($cart));
    }

    public function test_remove_purchased_lines_removes_matching_composite_line(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_active' => true]);

        $lines = $this->cartService->addCompositeItem(
            user: $user,
            type: 'test-bundle',
            payload: [
                'package_id' => 15,
                'signature' => 'sig_test_snap',
                'components' => [
                    ['source_id' => 10, 'product_id' => $product->id, 'quantity' => 1],
                ],
            ],
            quantity: 1,
            notes: null
        );

        $this->assertCount(1, $lines);

        // removePurchasedLines with the snapshot
        $remaining = $this->cartService->removePurchasedLines($user, $lines);

        $this->assertEmpty($remaining);
        $cart = Cart::where('user_id', $user->id)->first();
        $this->assertSame(0, $this->repository->countCartItems($cart));
    }

    public function test_remove_purchased_lines_retains_line_when_snapshot_mismatches(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_active' => true]);

        $lines = $this->cartService->addCompositeItem(
            user: $user,
            type: 'test-bundle',
            payload: [
                'package_id' => 15,
                'signature' => 'sig_test_snap',
                'components' => [
                    ['source_id' => 10, 'product_id' => $product->id, 'quantity' => 1],
                ],
            ],
            quantity: 1,
            notes: null
        );

        $mismatchedSnapshot = [
            new CartLine(
                itemId: $lines[0]->itemId,
                productId: null,
                categoryId: null,
                variantId: null,
                weightOptionValueId: null,
                weightMultiplier: 1,
                quantity: 99.0, // Different quantity
                unitPrice: 75.0,
                extrasUnitTotal: 0.0,
                subtotal: 75.0 * 99.0,
                productName: 'Test Package',
                productNameAr: 'باقة تجريبية',
                variantName: null,
                variantNameAr: null,
                weightLabel: null,
                notes: null,
                extras: [],
                productImage: null,
                sellByWeight: false,
                weightValue: null,
                type: 'test-bundle',
                referenceId: 15,
                components: [], // Different components
                couponEligible: true,
            ),
        ];

        $remaining = $this->cartService->removePurchasedLines($user, $mismatchedSnapshot);

        $this->assertCount(1, $remaining);
        $cart = Cart::where('user_id', $user->id)->first();
        $this->assertSame(1, $this->repository->countCartItems($cart));
    }

    public function test_build_line_throwing_deletes_item_on_read(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_active' => true]);

        $lines = $this->cartService->addCompositeItem(
            user: $user,
            type: 'test-bundle',
            payload: [
                'package_id' => 15,
                'signature' => 'sig_fail',
                'components' => [
                    ['source_id' => 10, 'product_id' => $product->id, 'quantity' => 1],
                ],
            ],
            quantity: 1,
            notes: null
        );
        $this->assertCount(1, $lines);

        // Instruct handler to throw during buildLine
        $this->handler->shouldThrowOnBuild = true;

        $readLines = $this->cartService->getLines($user);
        $this->assertEmpty($readLines);

        // Item should have been deleted from DB
        $cart = Cart::where('user_id', $user->id)->first();
        $this->assertSame(0, $this->repository->countCartItems($cart));
    }

    public function test_cart_price_calculator_does_not_crash_on_composite_item(): void
    {
        $calculator = new CartPriceCalculator();

        $cart = Cart::factory()->create();
        $item = CartItem::create([
            'cart_id' => $cart->id,
            'line_type' => 'test-bundle',
            'line_reference_id' => 10,
            'selection_signature' => 'sig_calc',
            'product_id' => null,
            'quantity' => '1',
            'weight_multiplier' => 1,
        ]);

        $this->assertSame(0.0, $calculator->getEffectivePrice($item));
        $this->assertSame(0.0, $calculator->calculateExtrasTotal($item));
        $this->assertSame(1.0, $calculator->calculateFinalQuantity($item));
        $this->assertSame(0.0, $calculator->calculateSubtotal($item));
    }
}
