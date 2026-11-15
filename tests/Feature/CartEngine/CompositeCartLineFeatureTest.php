<?php

declare(strict_types=1);

namespace Tests\Feature\CartEngine;

use App\Models\User;
use App\Plugins\CartEngine\src\Contracts\CartLineTypeHandler;
use App\Plugins\CartEngine\src\DTOs\CartLine;
use App\Plugins\CartEngine\src\DTOs\CartLineComponent;
use App\Plugins\CartEngine\src\DTOs\CartLineExtra;
use App\Plugins\CartEngine\src\DTOs\PreparedCartLine;
use App\Plugins\CartEngine\src\Models\Cart;
use App\Plugins\CartEngine\src\Models\CartItem;
use App\Plugins\CatalogProducts\src\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class FakePackageCartLineHandler implements CartLineTypeHandler
{
    public string $pluginId = 'cart-engine';

    public bool $shouldThrow = false;

    public ?string $unavailableReason = null;

    public function pluginId(): string
    {
        return $this->pluginId;
    }

    public function type(): string
    {
        return 'test-bundle';
    }

    public function prepare(array $payload): PreparedCartLine
    {
        if ($this->shouldThrow || ! empty($payload['should_fail'])) {
            throw ValidationException::withMessages([
                'package_id' => ['The selected package is invalid.'],
            ]);
        }

        $packageId = (int) ($payload['package_id'] ?? 42);
        $signature = (string) ($payload['signature'] ?? sha1((string) $packageId));

        $components = [];
        if (! empty($payload['components'])) {
            foreach ($payload['components'] as $c) {
                $extras = [];
                if (! empty($c['extras'])) {
                    foreach ($c['extras'] as $e) {
                        $extras[] = new CartLineExtra(
                            extraOptionItemId: (int) ($e['id'] ?? 1),
                            name: $e['name'] ?? 'Extra',
                            nameAr: $e['name_ar'] ?? 'إضافة',
                            unitPrice: (float) ($e['price'] ?? 5.0),
                            quantity: (int) ($e['quantity'] ?? 1),
                        );
                    }
                }

                $components[] = new CartLineComponent(
                    sourceId: $c['source_id'] ?? 101,
                    groupName: $c['group_name'] ?? 'Mains',
                    groupNameAr: $c['group_name_ar'] ?? 'الأطباق الرئيسية',
                    productId: (int) $c['product_id'],
                    variantId: isset($c['variant_id']) ? (int) $c['variant_id'] : null,
                    productName: $c['product_name'] ?? 'Cheeseburger',
                    productNameAr: $c['product_name_ar'] ?? 'برجر بالجبنة',
                    variantName: $c['variant_name'] ?? 'Double',
                    variantNameAr: $c['variant_name_ar'] ?? 'مزدوج',
                    quantity: (float) ($c['quantity'] ?? 1.0),
                    unitPriceAdjustment: (float) ($c['unit_price_adjustment'] ?? 5.0),
                    sortOrder: (int) ($c['sort_order'] ?? 0),
                    extras: $extras,
                    notes: $c['notes'] ?? null,
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
        $components = [];
        $adjustmentSum = 0.0;
        foreach ($item->components as $c) {
            $extras = [];
            if (! empty($c->extras) && is_array($c->extras)) {
                foreach ($c->extras as $e) {
                    $extras[] = new CartLineExtra(
                        extraOptionItemId: (int) ($e['id'] ?? 1),
                        name: (string) ($e['name'] ?? 'Extra Cheese'),
                        nameAr: (string) ($e['name_ar'] ?? 'جبنة إضافية'),
                        unitPrice: (float) ($e['price'] ?? 7.0),
                        quantity: (int) ($e['quantity'] ?? 1),
                    );
                }
            }

            $components[] = new CartLineComponent(
                sourceId: $c->source_id,
                groupName: 'Mains',
                groupNameAr: 'الأطباق الرئيسية',
                productId: (int) $c->product_id,
                variantId: $c->variant_id !== null ? (int) $c->variant_id : null,
                productName: $c->product?->name ?? 'Cheeseburger',
                productNameAr: $c->product?->name_ar ?? 'برجر بالجبنة',
                variantName: $c->variant?->name ?? 'Double',
                variantNameAr: $c->variant?->name_ar ?? 'مزدوج',
                quantity: (float) $c->quantity,
                unitPriceAdjustment: (float) $c->unit_price_adjustment,
                sortOrder: (int) $c->sort_order,
                extras: $extras,
                notes: $c->notes,
            );
            $adjustmentSum += (float) $c->unit_price_adjustment;
        }

        $unitPrice = 50.0 + $adjustmentSum;
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
            productName: 'Family Feast Combo',
            productNameAr: 'وجبة التوفير العائلية',
            variantName: null,
            variantNameAr: null,
            weightLabel: null,
            notes: $item->notes,
            extras: [],
            productImage: 'https://example.com/package.png',
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
        return $this->unavailableReason;
    }
}

final class CompositeCartLineFeatureTest extends TestCase
{
    use RefreshDatabase;

    private FakePackageCartLineHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = new FakePackageCartLineHandler();
        $this->app->instance(FakePackageCartLineHandler::class, $this->handler);
        $this->app->tag([FakePackageCartLineHandler::class], 'cart-engine.line-types');
    }

    public function test_add_composite_line_returns_201_and_new_json_keys(): void
    {
        $product = Product::factory()->create(['name' => 'Burger Item', 'is_active' => true]);

        $response = $this->postJson('/api/cart/items', [
            'type' => 'test-bundle',
            'quantity' => 2,
            'notes' => 'No onions please',
            'package_id' => 10,
            'components' => [
                [
                    'source_id' => 1,
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price_adjustment' => 5.0,
                ],
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.items.0.type', 'test-bundle');
        $response->assertJsonPath('data.items.0.reference_id', 10);
        $response->assertJsonPath('data.items.0.notes', 'No onions please');
        $response->assertJsonPath('data.items.0.product_id', null);
        $response->assertJsonPath('data.items.0.product.id', null);
        $response->assertJsonPath('data.items.0.product.name', 'Family Feast Combo');
        $response->assertJsonPath('data.items.0.product.image', 'https://example.com/package.png');
        $response->assertJsonPath('data.items.0.product.sell_by_weight', false);
        $response->assertJsonPath('data.items.0.product.weight_option', null);
        $response->assertJsonPath('data.items.0.variant', null);
        $response->assertJsonPath('data.items.0.weight_option_value', null);
        $response->assertJsonPath('data.items.0.extras', []);
        $this->assertSame(0.0, (float) $response->json('data.items.0.extras_total'));
        $this->assertSame(55.0, (float) $response->json('data.items.0.price'));
        $this->assertSame(110.0, (float) $response->json('data.items.0.subtotal'));
        $this->assertSame(2.0, (float) $response->json('data.items.0.quantity'));
        $response->assertJsonCount(1, 'data.items.0.components');
        $response->assertJsonPath('data.items.0.components.0.source_id', 1);
        $response->assertJsonPath('data.items.0.components.0.product_id', $product->id);
        $this->assertSame(5.0, (float) $response->json('data.items.0.components.0.unit_price_adjustment'));
    }

    public function test_identical_signature_and_notes_quantities_merge(): void
    {
        $product = Product::factory()->create(['name' => 'Burger Item', 'is_active' => true]);

        $payload = [
            'type' => 'test-bundle',
            'quantity' => 2,
            'notes' => 'Extra sauce',
            'package_id' => 10,
            'signature' => 'sig_abc_123',
            'components' => [
                [
                    'source_id' => 1,
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price_adjustment' => 0,
                ],
            ],
        ];

        $this->postJson('/api/cart/items', $payload)->assertStatus(201);

        $payload['quantity'] = 3;
        $response = $this->postJson('/api/cart/items', $payload);

        $response->assertStatus(201);
        $this->assertCount(1, $response->json('data.items'));
        $this->assertSame(5.0, (float) $response->json('data.items.0.quantity'));
    }

    public function test_different_notes_creates_separate_lines(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $payload1 = [
            'type' => 'test-bundle',
            'quantity' => 1,
            'notes' => 'No pickles',
            'package_id' => 10,
            'signature' => 'sig_same',
            'components' => [
                ['source_id' => 1, 'product_id' => $product->id, 'quantity' => 1, 'unit_price_adjustment' => 0],
            ],
        ];

        $payload2 = $payload1;
        $payload2['notes'] = 'Extra pickles';

        $this->postJson('/api/cart/items', $payload1)->assertStatus(201);
        $response = $this->postJson('/api/cart/items', $payload2);

        $response->assertStatus(201);
        $this->assertCount(2, $response->json('data.items'));
    }

    public function test_prepare_throwing_validation_exception_returns_422_with_keys(): void
    {
        $response = $this->postJson('/api/cart/items', [
            'type' => 'test-bundle',
            'quantity' => 1,
            'should_fail' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['package_id']);
    }

    public function test_unsupported_type_returns_422(): void
    {
        $response = $this->postJson('/api/cart/items', [
            'type' => 'mystery_box',
            'quantity' => 1,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['type']);
        $this->assertStringContainsString('Unsupported line type', (string) $response->json('errors.type.0'));
    }

    public function test_update_quantity_on_composite_line(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $addResponse = $this->postJson('/api/cart/items', [
            'type' => 'test-bundle',
            'quantity' => 1,
            'package_id' => 10,
            'components' => [
                ['source_id' => 1, 'product_id' => $product->id, 'quantity' => 1, 'unit_price_adjustment' => 0],
            ],
        ]);
        $itemId = $addResponse->json('data.items.0.id');

        // Integer update works
        $patchResponse = $this->patchJson("/api/cart/items/{$itemId}", [
            'quantity' => 3,
        ]);
        $patchResponse->assertSuccessful();
        $this->assertSame(3.0, (float) $patchResponse->json('data.items.0.quantity'));

        // Float quantity 1.5 gives 422
        $patchFloat = $this->patchJson("/api/cart/items/{$itemId}", [
            'quantity' => 1.5,
        ]);
        $patchFloat->assertStatus(422);

        // Weight fields give 422
        $patchWeightMultiplier = $this->patchJson("/api/cart/items/{$itemId}", [
            'weight_multiplier' => 2,
        ]);
        $patchWeightMultiplier->assertStatus(422);

        $patchWeightOption = $this->patchJson("/api/cart/items/{$itemId}", [
            'weight_option_value_id' => 5,
        ]);
        $patchWeightOption->assertStatus(422);
    }

    public function test_remove_composite_line(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        $addResponse = $this->postJson('/api/cart/items', [
            'type' => 'test-bundle',
            'quantity' => 1,
            'package_id' => 10,
            'components' => [
                ['source_id' => 1, 'product_id' => $product->id, 'quantity' => 1, 'unit_price_adjustment' => 0],
            ],
        ]);
        $itemId = $addResponse->json('data.items.0.id');

        $deleteResponse = $this->deleteJson("/api/cart/items/{$itemId}");
        $deleteResponse->assertSuccessful();
        $this->assertEmpty($deleteResponse->json('data.items'));
        $this->assertDatabaseMissing('cart_items', ['id' => $itemId]);
        $this->assertDatabaseMissing('cart_item_components', ['cart_item_id' => $itemId]);
    }

    public function test_guest_to_user_sync_copies_composite_lines_and_components(): void
    {
        auth()->logout();
        $product = Product::factory()->create(['is_active' => true]);
        $user = User::factory()->create();

        // Add as guest
        $this->postJson('/api/cart/items', [
            'type' => 'test-bundle',
            'quantity' => 2,
            'notes' => 'For family',
            'package_id' => 10,
            'signature' => 'sig_sync_1',
            'components' => [
                ['source_id' => 99, 'product_id' => $product->id, 'quantity' => 1, 'unit_price_adjustment' => 10],
            ],
        ])->assertStatus(201);

        // Sync to user
        $syncResponse = $this->actingAs($user)->postJson('/api/cart/sync');
        $syncResponse->assertSuccessful();

        $this->assertCount(1, $syncResponse->json('data.items'));
        $this->assertSame('test-bundle', $syncResponse->json('data.items.0.type'));
        $this->assertSame(2.0, (float) $syncResponse->json('data.items.0.quantity'));
        $this->assertSame(99, $syncResponse->json('data.items.0.components.0.source_id'));

        // Verify in database under user's cart
        $userCart = Cart::where('user_id', $user->id)->first();
        $this->assertNotNull($userCart);
        $this->assertDatabaseHas('cart_items', [
            'cart_id' => $userCart->id,
            'line_type' => 'test-bundle',
            'selection_signature' => 'sig_sync_1',
        ]);
        $userCartItem = CartItem::where('cart_id', $userCart->id)->first();
        $this->assertDatabaseHas('cart_item_components', [
            'cart_item_id' => $userCartItem->id,
            'source_id' => 99,
            'product_id' => $product->id,
        ]);
    }

    public function test_handler_missing_deletes_line_on_read(): void
    {
        $product = Product::factory()->create(['is_active' => true]);

        // Add a line with type 'test-bundle'
        $addResponse = $this->postJson('/api/cart/items', [
            'type' => 'test-bundle',
            'quantity' => 1,
            'package_id' => 10,
            'components' => [
                ['source_id' => 1, 'product_id' => $product->id, 'quantity' => 1, 'unit_price_adjustment' => 0],
            ],
        ]);
        $itemId = $addResponse->json('data.items.0.id');

        // Mark the handler's plugin as inactive so the registry does not resolve it
        $this->handler->pluginId = 'inactive-plugin';

        // Call GET /api/cart
        $getResponse = $this->getJson('/api/cart');
        $getResponse->assertSuccessful();
        $this->assertEmpty($getResponse->json('data.items'));

        // Verify the dangling item was deleted from DB
        $this->assertDatabaseMissing('cart_items', ['id' => $itemId]);
    }

    public function test_product_lines_remain_unchanged_with_composite_support(): void
    {
        $product = Product::factory()->create([
            'base_price' => 20.00,
            'price_after_discount' => null,
            'sell_by_weight' => false,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => '1',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.items.0.type', 'product');
        $response->assertJsonPath('data.items.0.reference_id', null);
        $response->assertJsonPath('data.items.0.components', []);
        $response->assertJsonPath('data.items.0.product_id', $product->id);
        $this->assertSame(20.0, (float) $response->json('data.items.0.price'));
    }

    public function test_cart_json_exposes_components_extras_and_notes_and_guest_user_sync_keeps_them(): void
    {
        $product = Product::factory()->create([
            'base_price' => 20.00,
            'price_after_discount' => null,
            'sell_by_weight' => false,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/cart/items', [
            'type' => 'test-bundle',
            'quantity' => 1,
            'package_id' => 10,
            'components' => [
                [
                    'source_id' => 1,
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price_adjustment' => 0,
                    'extras' => [
                        [
                            'id' => 55,
                            'name' => 'Extra Cheese',
                            'name_ar' => 'جبنة إضافية',
                            'price' => 7.0,
                            'quantity' => 2,
                        ],
                    ],
                    'notes' => 'No pickles please',
                ],
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.items.0.components.0.notes', 'No pickles please');
        $response->assertJsonPath('data.items.0.components.0.extras.0.id', 55);
        $response->assertJsonPath('data.items.0.components.0.extras.0.name', 'Extra Cheese');
        $this->assertEquals(7.0, $response->json('data.items.0.components.0.extras.0.price'));
        $response->assertJsonPath('data.items.0.components.0.extras.0.quantity', 2);

        // Guest to user sync
        $guestCart = Cart::latest('id')->first();
        $this->assertNotNull($guestCart);

        $user = User::factory()->create();
        $userCart = Cart::create(['user_id' => $user->id]);

        /** @var \App\Plugins\CartEngine\src\Services\Contracts\CartRepositoryInterface $cartRepo */
        $cartRepo = app(\App\Plugins\CartEngine\src\Services\Contracts\CartRepositoryInterface::class);
        $cartRepo->migrateCart($guestCart, $userCart);

        $userCartItem = CartItem::where('cart_id', $userCart->id)->first();
        $this->assertNotNull($userCartItem);
        $userComp = $userCartItem->components->first();
        $this->assertNotNull($userComp);
        $this->assertSame('No pickles please', $userComp->notes);
        $this->assertIsArray($userComp->extras);
        $this->assertSame(55, $userComp->extras[0]['id']);
        $this->assertSame(2, $userComp->extras[0]['quantity']);

        // Check GET /api/cart for authenticated user
        $getResponse = $this->actingAs($user)->getJson('/api/cart');
        $getResponse->assertSuccessful();
        $getResponse->assertJsonPath('data.items.0.components.0.notes', 'No pickles please');
        $getResponse->assertJsonPath('data.items.0.components.0.extras.0.id', 55);
        $this->assertEquals(7.0, $getResponse->json('data.items.0.components.0.extras.0.price'));
        $getResponse->assertJsonPath('data.items.0.components.0.extras.0.quantity', 2);
    }
}
