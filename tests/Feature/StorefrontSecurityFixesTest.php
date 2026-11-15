<?php

declare(strict_types=1);

use App\Core\Plugins\Enums\PluginStatus;
use App\Core\Plugins\Services\PluginManager;
use App\Models\TenantPlugin;
use App\Models\User;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\LocationsDelivery\src\Models\Address;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\OrderManagement\src\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

it('serves /payments/{id}/kashier via the SPA catch-all with 200', function () {
    $order = Order::factory()->create();

    $response = $this->get("/payments/{$order->id}/kashier");

    $response->assertOk();
    $response->assertViewIs('app');
});

it('denies access to payment status of guest order without session entry', function () {
    Auth::logout();
    $order = Order::factory()->create([
        'user_id' => null,
    ]);

    $response = $this->getJson("/api/orders/{$order->id}/payment-status");

    $response->assertStatus(403);
    $response->assertJson([
        'status' => 'error',
        'message' => 'Unauthorized access to order payment status',
    ]);
});

it('allows access to payment status of guest order with session entry', function () {
    Auth::logout();
    $order = Order::factory()->create([
        'user_id' => null,
    ]);

    $response = $this->withSession([
        'guest_order_ids' => [$order->id],
    ])->getJson("/api/orders/{$order->id}/payment-status");

    $response->assertOk();
    $response->assertJsonPath('status', 'success');
    $response->assertJsonPath('data.order.id', $order->id);
});

it('denies access to payment status of another users order', function () {
    $otherUser = User::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $otherUser->id,
    ]);

    // Request as current authenticated default user (different from $otherUser)
    $response = $this->getJson("/api/orders/{$order->id}/payment-status");

    $response->assertStatus(403);
});

it('denies access to kashier config of guest order without session entry', function () {
    Auth::logout();
    $order = Order::factory()->create([
        'user_id' => null,
    ]);

    $response = $this->getJson("/api/payments/{$order->id}/kashier-config");

    $response->assertStatus(403);
    $response->assertJson([
        'status' => 'error',
        'message' => 'Unauthorized',
    ]);
});

it('allows access to kashier config of guest order with session entry', function () {
    Auth::logout();
    $order = Order::factory()->create([
        'user_id' => null,
    ]);

    $response = $this->withSession([
        'guest_order_ids' => [$order->id],
    ])->getJson("/api/payments/{$order->id}/kashier-config");

    $response->assertOk();
    $response->assertJsonPath('status', 'success');
    $response->assertJsonPath('data.order.id', $order->id);
});

it('denies access to kashier config of another users order', function () {
    $otherUser = User::factory()->create();
    $order = Order::factory()->create([
        'user_id' => $otherUser->id,
    ]);

    // Request as current authenticated default user (different from $otherUser)
    $response = $this->getJson("/api/payments/{$order->id}/kashier-config");

    $response->assertStatus(403);
});

it('returns 422 when placing order with another users address_id', function () {
    $otherUser = User::factory()->create();
    $otherAddress = Address::factory()->create(['user_id' => $otherUser->id]);
    $branch = Branch::factory()->create();

    $response = $this->postJson('/api/orders/place', [
        'branch_id' => $branch->id,
        'payment_method' => 'cod',
        'type' => 'web_delivery',
        'address_id' => $otherAddress->id,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['address_id']);
});

it('returns 422 when guest sends an address_id', function () {
    Auth::logout();
    $address = Address::factory()->create();
    $branch = Branch::factory()->create();

    $response = $this->postJson('/api/orders/place', [
        'branch_id' => $branch->id,
        'payment_method' => 'cod',
        'type' => 'web_delivery',
        'address_id' => $address->id,
        'guest_data' => [
            'name' => 'Guest User',
            'phone' => '01012345678',
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['address_id']);
});

it('returns 422 when guest places order but guest-checkout plugin is inactive for tenant', function () {
    Auth::logout();

    TenantPlugin::where('tenant_id', (string) tenant()->id)
        ->where('plugin_id', 'guest-checkout')
        ->update(['status' => PluginStatus::DEACTIVATED]);

    app(PluginManager::class)->resetTenant(tenant());

    $branch = Branch::factory()->create();

    $response = $this->postJson('/api/orders/place', [
        'branch_id' => $branch->id,
        'payment_method' => 'cod',
        'type' => 'web_takeaway',
        'guest_data' => [
            'name' => 'Guest User',
            'phone' => '01012345678',
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJson([
        'status' => 'error',
        'errors' => [
            'guest_data' => ['Guest checkout is not permitted for this store. Please log in.'],
        ],
    ]);
});

it('pushes guest order id into session after guest places order', function () {
    Auth::logout();

    $product = Product::factory()->create([
        'is_active' => true,
        'base_price' => 50.00,
        'sell_by_weight' => false,
    ]);
    $branch = Branch::factory()->create(['is_active' => true]);

    // Add item to guest cart via REST API
    $this->postJson('/api/cart/items', [
        'product_id' => $product->id,
        'quantity' => '1',
    ])->assertCreated();

    $response = $this->postJson('/api/orders/place', [
        'branch_id' => $branch->id,
        'payment_method' => 'cod',
        'type' => 'web_takeaway',
        'guest_data' => [
            'name' => 'Guest User',
            'phone' => '01012345678',
        ],
    ]);

    $response->assertOk();
    $orderId = $response->json('data.order_id');
    expect($orderId)->not->toBeNull();

    $response->assertSessionHas('guest_order_ids', fn (array $ids) => in_array($orderId, $ids, false));
});
