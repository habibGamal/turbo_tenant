<?php

declare(strict_types=1);

use App\Models\User;
use App\Plugins\CartEngine\src\Services\Contracts\CartServiceInterface;
use App\Plugins\CatalogProducts\src\Models\ExtraOption;
use App\Plugins\CatalogProducts\src\Models\ExtraOptionItem;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\WeightOption;
use App\Plugins\CatalogProducts\src\Models\WeightOptionValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('can fetch cart via rest api GET /api/cart', function () {
    $response = $this->getJson('/api/cart');

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'status',
        'data' => [
            'items',
            'total',
        ],
    ]);
    expect($response->json('status'))->toBe('success');
});

it('can add item to cart via rest api POST /api/cart/items', function () {
    $product = Product::factory()->create(['base_price' => 25.00, 'is_active' => true]);

    $response = $this->postJson('/api/cart/items', [
        'product_id' => $product->id,
        'quantity' => '2',
    ]);

    $response->assertCreated();
    $response->assertJsonStructure([
        'status',
        'message',
        'data' => [
            'items',
            'total',
        ],
    ]);
    expect($response->json('status'))->toBe('success');
    expect($response->json('data.items'))->toHaveCount(1);
});

it('can update item via rest api PATCH /api/cart/items/{id}', function () {
    $product = Product::factory()->create(['base_price' => 15.00, 'is_active' => true]);

    $addResponse = $this->postJson('/api/cart/items', [
        'product_id' => $product->id,
        'quantity' => '1',
    ]);
    $itemId = $addResponse->json('data.items.0.id');

    $response = $this->patchJson("/api/cart/items/{$itemId}", [
        'quantity' => '4',
    ]);

    $response->assertSuccessful();
    expect($response->json('status'))->toBe('success');
    expect((float) ($response->json('data.items.0.quantity')))->toBe(4.0);
});

it('can remove item via rest api DELETE /api/cart/items/{id}', function () {
    $product = Product::factory()->create(['base_price' => 10.00, 'is_active' => true]);

    $addResponse = $this->postJson('/api/cart/items', [
        'product_id' => $product->id,
        'quantity' => '1',
    ]);
    $itemId = $addResponse->json('data.items.0.id');

    $response = $this->deleteJson("/api/cart/items/{$itemId}");

    $response->assertSuccessful();
    expect($response->json('status'))->toBe('success');
    expect($response->json('data.items'))->toHaveCount(0);
});

it('can clear cart via rest api DELETE /api/cart', function () {
    $product = Product::factory()->create(['base_price' => 30.00, 'is_active' => true]);

    $this->postJson('/api/cart/items', [
        'product_id' => $product->id,
        'quantity' => '3',
    ]);

    $response = $this->deleteJson('/api/cart');

    $response->assertSuccessful();
    expect($response->json('status'))->toBe('success');
    expect($response->json('data.items'))->toBeEmpty();
    expect($response->json('data.total'))->toBe(0);
});

it('can sync guest cart to user via rest api POST /api/cart/sync', function () {
    auth()->logout();
    $product = Product::factory()->create(['base_price' => 50.00, 'is_active' => true]);

    $this->postJson('/api/cart/items', [
        'product_id' => $product->id,
        'quantity' => '1',
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/cart/sync');

    $response->assertSuccessful();
    expect($response->json('status'))->toBe('success');
    expect($response->json('data.items'))->toHaveCount(1);
});

it('ensures cart JSON totals equal the getLines numbers', function () {
    $user = User::factory()->create();

    $extraOption = ExtraOption::factory()->create(['is_active' => true]);
    $extraItem = ExtraOptionItem::factory()->create([
        'extra_option_id' => $extraOption->id,
        'price' => 5.00,
    ]);

    $product1 = Product::factory()->create([
        'base_price' => 20.00,
        'is_active' => true,
        'extra_option_id' => $extraOption->id,
    ]);

    $product2 = Product::factory()->create([
        'base_price' => 35.00,
        'is_active' => true,
    ]);

    // Add item 1 with extras
    $post1 = $this->actingAs($user)->postJson('/api/cart/items', [
        'product_id' => $product1->id,
        'quantity' => '2',
        'extras' => [
            ['id' => $extraItem->id, 'quantity' => 1],
        ],
    ]);
    $post1->assertCreated();

    // Add item 2
    $post2 = $this->actingAs($user)->postJson('/api/cart/items', [
        'product_id' => $product2->id,
        'quantity' => '3',
    ]);
    $post2->assertCreated();

    $cartService = app(CartServiceInterface::class);
    $lines = $cartService->getLines($user);
    expect($lines)->toHaveCount(2);

    $response = $this->actingAs($user)->getJson('/api/cart');
    $response->assertSuccessful();

    $jsonData = $response->json('data');
    expect($jsonData['items'])->toHaveCount(count($lines));

    $expectedTotal = 0.0;
    foreach ($lines as $idx => $line) {
        $jsonItem = $jsonData['items'][$idx];
        expect((float) $jsonItem['price'])->toBe($line->unitPrice)
            ->and((float) $jsonItem['extras_total'])->toBe($line->extrasUnitTotal)
            ->and((float) $jsonItem['subtotal'])->toBe($line->subtotal)
            ->and((float) $jsonItem['quantity'])->toBe($line->quantity);
        $expectedTotal += $line->subtotal;
    }

    expect((float) $jsonData['total'])->toBe($expectedTotal);
});

it('ensures cart with 5 lines does not run a query per line', function () {
    $user = User::factory()->create();

    $weightOption = WeightOption::factory()->create(['unit' => 'kg']);
    $value = WeightOptionValue::factory()->create([
        'weight_option_id' => $weightOption->id,
        'value' => '0.5',
    ]);

    // Create 5 different products
    for ($i = 0; $i < 5; $i++) {
        $product = Product::factory()->create([
            'base_price' => 10.00 + $i,
            'is_active' => true,
            'sell_by_weight' => true,
            'weight_options_id' => $weightOption->id,
        ]);

        $this->actingAs($user)->postJson('/api/cart/items', [
            'product_id' => $product->id,
            'quantity' => '1',
            'weight_option_value_id' => $value->id,
            'weight_multiplier' => 1,
        ]);
    }

    // Now count queries on GET /api/cart
    DB::flushQueryLog();
    DB::enableQueryLog();

    $response = $this->actingAs($user)->getJson('/api/cart');
    $response->assertSuccessful();

    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($response->json('data.items'))->toHaveCount(5);

    // Filter queries specifically for products / weight options to ensure they are batched
    $productSelectQueries = array_filter($queries, function ($q) {
        return str_contains(mb_strtolower($q['query']), 'from "products"')
            || str_contains(mb_strtolower($q['query']), 'from `products`');
    });

    // In a batched lookup there should be at most 2 product select queries (the cart eager load + batch if any),
    // NEVER 5 product select queries!
    expect(count($productSelectQueries))->toBeLessThan(5);
});

it('returns 404 envelope for CartItemNotFound', function () {
    $user = User::factory()->create();

    // Trying to update non-existent cart item
    $response = $this->actingAs($user)->patchJson('/api/cart/items/999999', [
        'quantity' => '2',
    ]);

    $response->assertNotFound();
    $response->assertJson([
        'status' => 'error',
        'message' => 'Cart item not found',
    ]);

    // Trying to delete non-existent cart item
    $deleteResponse = $this->actingAs($user)->deleteJson('/api/cart/items/999999');

    $deleteResponse->assertNotFound();
    $deleteResponse->assertJson([
        'status' => 'error',
        'message' => 'Cart item not found',
    ]);
});

it('all add update remove clear and sync responses use the envelope', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create(['base_price' => 20.00, 'is_active' => true]);

    // Store (add)
    $storeResponse = $this->actingAs($user)->postJson('/api/cart/items', [
        'product_id' => $product->id,
        'quantity' => '1',
    ]);
    $storeResponse->assertCreated();
    $storeResponse->assertJsonStructure([
        'status',
        'message',
        'data' => ['items', 'total'],
    ]);
    expect($storeResponse->json('status'))->toBe('success');
    $itemId = $storeResponse->json('data.items.0.id');

    // Update
    $updateResponse = $this->actingAs($user)->patchJson("/api/cart/items/{$itemId}", [
        'quantity' => '3',
    ]);
    $updateResponse->assertSuccessful();
    $updateResponse->assertJsonStructure([
        'status',
        'message',
        'data' => ['items', 'total'],
    ]);
    expect($updateResponse->json('status'))->toBe('success');

    // Remove
    $removeResponse = $this->actingAs($user)->deleteJson("/api/cart/items/{$itemId}");
    $removeResponse->assertSuccessful();
    $removeResponse->assertJsonStructure([
        'status',
        'message',
        'data' => ['items', 'total'],
    ]);
    expect($removeResponse->json('status'))->toBe('success');

    // Clear
    $clearResponse = $this->actingAs($user)->deleteJson('/api/cart');
    $clearResponse->assertSuccessful();
    $clearResponse->assertJsonStructure([
        'status',
        'message',
        'data' => ['items', 'total'],
    ]);
    expect($clearResponse->json('status'))->toBe('success');

    // Sync
    $syncResponse = $this->actingAs($user)->postJson('/api/cart/sync');
    $syncResponse->assertSuccessful();
    $syncResponse->assertJsonStructure([
        'status',
        'message',
        'data' => ['items', 'total'],
    ]);
    expect($syncResponse->json('status'))->toBe('success');
});
