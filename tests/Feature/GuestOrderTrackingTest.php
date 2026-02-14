<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\GuestUser;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can successfully track guest order with correct credentials', function () {
    $guestUser = GuestUser::factory()->create([
        'phone' => '1234567890',
        'phone_country_code' => '+20',
    ]);

    $order = Order::factory()->create([
        'order_number' => 'ORD-TEST123',
        'guest_user_id' => $guestUser->id,
        'user_id' => null,
    ]);

    $response = $this->postJson(route('orders.track'), [
        'order_number' => 'ORD-TEST123',
        'phone' => '1234567890',
        'phone_country_code' => '+20',
    ]);

    $response->assertSuccessful();
    $response->assertJson([
        'success' => true,
    ]);
    $response->assertJsonStructure([
        'success',
        'order' => [
            'id',
            'order_number',
            'status',
            'total',
        ],
    ]);

    expect($response->json('order.id'))->toBe($order->id);
    expect($response->json('order.order_number'))->toBe('ORD-TEST123');
});

it('tracking fails with wrong phone number', function () {
    $guestUser = GuestUser::factory()->create([
        'phone' => '1234567890',
        'phone_country_code' => '+20',
    ]);

    Order::factory()->create([
        'order_number' => 'ORD-TEST123',
        'guest_user_id' => $guestUser->id,
        'user_id' => null,
    ]);

    $response = $this->postJson(route('orders.track'), [
        'order_number' => 'ORD-TEST123',
        'phone' => '9999999999', // Wrong phone
        'phone_country_code' => '+20',
    ]);

    $response->assertNotFound();
    $response->assertJson([
        'success' => false,
        'error' => 'No orders found for this phone number',
    ]);
});

it('tracking fails with wrong order number', function () {
    $guestUser = GuestUser::factory()->create([
        'phone' => '1234567890',
        'phone_country_code' => '+20',
    ]);

    Order::factory()->create([
        'order_number' => 'ORD-TEST123',
        'guest_user_id' => $guestUser->id,
        'user_id' => null,
    ]);

    $response = $this->postJson(route('orders.track'), [
        'order_number' => 'ORD-WRONG999',
        'phone' => '1234567890',
        'phone_country_code' => '+20',
    ]);

    $response->assertNotFound();
    $response->assertJson([
        'success' => false,
        'error' => 'Order not found',
    ]);
});

it('tracking returns order with relationships', function () {
    $guestUser = GuestUser::factory()->create([
        'phone' => '1234567890',
        'phone_country_code' => '+20',
        'name' => 'Test Guest',
    ]);

    $branch = Branch::factory()->create(['name' => 'Main Branch']);
    $product = Product::factory()->create(['name' => 'Test Product']);

    $order = Order::factory()->create([
        'order_number' => 'ORD-REL123',
        'guest_user_id' => $guestUser->id,
        'user_id' => null,
        'branch_id' => $branch->id,
    ]);

    OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
    ]);

    $response = $this->postJson(route('orders.track'), [
        'order_number' => 'ORD-REL123',
        'phone' => '1234567890',
        'phone_country_code' => '+20',
    ]);

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'success',
        'order' => [
            'items',
            'branch' => ['id', 'name'],
            'guest_user' => ['id', 'name', 'phone'],
        ],
    ]);

    expect($response->json('order.branch.name'))->toBe('Main Branch');
    expect($response->json('order.guest_user.name'))->toBe('Test Guest');
    expect(count($response->json('order.items')))->toBe(1);
});

it('prevents cross-guest access to orders', function () {
    $guest1 = GuestUser::factory()->create([
        'phone' => '1111111111',
        'phone_country_code' => '+20',
    ]);

    $guest2 = GuestUser::factory()->create([
        'phone' => '2222222222',
        'phone_country_code' => '+20',
    ]);

    $order1 = Order::factory()->create([
        'order_number' => 'ORD-GUEST1',
        'guest_user_id' => $guest1->id,
        'user_id' => null,
    ]);

    Order::factory()->create([
        'order_number' => 'ORD-GUEST2',
        'guest_user_id' => $guest2->id,
        'user_id' => null,
    ]);

    // Guest 2 tries to access Guest 1's order
    $response = $this->postJson(route('orders.track'), [
        'order_number' => 'ORD-GUEST1',
        'phone' => '2222222222', // Guest 2's phone
        'phone_country_code' => '+20',
    ]);

    $response->assertNotFound();
    $response->assertJson([
        'success' => false,
        'error' => 'Order not found',
    ]);

    // Verify Guest 1 can still access their order
    $response2 = $this->postJson(route('orders.track'), [
        'order_number' => 'ORD-GUEST1',
        'phone' => '1111111111',
        'phone_country_code' => '+20',
    ]);

    $response2->assertSuccessful();
    expect($response2->json('order.id'))->toBe($order1->id);
});

it('uses default country code +20 when not provided', function () {
    $guestUser = GuestUser::factory()->create([
        'phone' => '1234567890',
        'phone_country_code' => '+20',
    ]);

    $order = Order::factory()->create([
        'order_number' => 'ORD-DEFAULT',
        'guest_user_id' => $guestUser->id,
        'user_id' => null,
    ]);

    $response = $this->postJson(route('orders.track'), [
        'order_number' => 'ORD-DEFAULT',
        'phone' => '1234567890',
        // phone_country_code not provided
    ]);

    $response->assertSuccessful();
    expect($response->json('order.id'))->toBe($order->id);
});

it('validates order_number is required', function () {
    $response = $this->postJson(route('orders.track'), [
        // Missing order_number
        'phone' => '1234567890',
        'phone_country_code' => '+20',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['order_number']);
});

it('validates phone is required', function () {
    $response = $this->postJson(route('orders.track'), [
        'order_number' => 'ORD-TEST123',
        // Missing phone
        'phone_country_code' => '+20',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['phone']);
});

it('can track order with different country codes correctly', function () {
    $egyptianGuest = GuestUser::factory()->egyptian()->create([
        'phone' => '1234567890',
    ]);

    $saudiGuest = GuestUser::factory()->saudi()->create([
        'phone' => '1234567890', // Same phone, different country
    ]);

    $egyptianOrder = Order::factory()->create([
        'order_number' => 'ORD-EGYPT',
        'guest_user_id' => $egyptianGuest->id,
        'user_id' => null,
    ]);

    $saudiOrder = Order::factory()->create([
        'order_number' => 'ORD-SAUDI',
        'guest_user_id' => $saudiGuest->id,
        'user_id' => null,
    ]);

    // Track Egyptian order
    $response1 = $this->postJson(route('orders.track'), [
        'order_number' => 'ORD-EGYPT',
        'phone' => '1234567890',
        'phone_country_code' => '+20',
    ]);

    $response1->assertSuccessful();
    expect($response1->json('order.id'))->toBe($egyptianOrder->id);

    // Track Saudi order
    $response2 = $this->postJson(route('orders.track'), [
        'order_number' => 'ORD-SAUDI',
        'phone' => '1234567890',
        'phone_country_code' => '+966',
    ]);

    $response2->assertSuccessful();
    expect($response2->json('order.id'))->toBe($saudiOrder->id);

    // Egyptian guest cannot access Saudi order
    $response3 = $this->postJson(route('orders.track'), [
        'order_number' => 'ORD-SAUDI',
        'phone' => '1234567890',
        'phone_country_code' => '+20',
    ]);

    $response3->assertNotFound();
});

it('can track multiple orders for same guest', function () {
    $guestUser = GuestUser::factory()->create([
        'phone' => '1234567890',
        'phone_country_code' => '+20',
    ]);

    $order1 = Order::factory()->create([
        'order_number' => 'ORD-FIRST',
        'guest_user_id' => $guestUser->id,
        'user_id' => null,
    ]);

    $order2 = Order::factory()->create([
        'order_number' => 'ORD-SECOND',
        'guest_user_id' => $guestUser->id,
        'user_id' => null,
    ]);

    // Track first order
    $response1 = $this->postJson(route('orders.track'), [
        'order_number' => 'ORD-FIRST',
        'phone' => '1234567890',
        'phone_country_code' => '+20',
    ]);

    $response1->assertSuccessful();
    expect($response1->json('order.id'))->toBe($order1->id);

    // Track second order
    $response2 = $this->postJson(route('orders.track'), [
        'order_number' => 'ORD-SECOND',
        'phone' => '1234567890',
        'phone_country_code' => '+20',
    ]);

    $response2->assertSuccessful();
    expect($response2->json('order.id'))->toBe($order2->id);
});

it('cannot track user orders as guest', function () {
    $user = App\Models\User::factory()->create();

    $userOrder = Order::factory()->create([
        'order_number' => 'ORD-USER',
        'user_id' => $user->id,
        'guest_user_id' => null,
    ]);

    // Try to track with any phone
    $response = $this->postJson(route('orders.track'), [
        'order_number' => 'ORD-USER',
        'phone' => '9999999999',
        'phone_country_code' => '+20',
    ]);

    $response->assertNotFound();
    $response->assertJson([
        'success' => false,
    ]);
});

it('returns order with correct status information', function () {
    $guestUser = GuestUser::factory()->create([
        'phone' => '1234567890',
        'phone_country_code' => '+20',
    ]);

    $order = Order::factory()->create([
        'order_number' => 'ORD-STATUS',
        'guest_user_id' => $guestUser->id,
        'user_id' => null,
        'status' => 'processing',
        'payment_status' => 'completed',
        'total' => 150.50,
    ]);

    $response = $this->postJson(route('orders.track'), [
        'order_number' => 'ORD-STATUS',
        'phone' => '1234567890',
        'phone_country_code' => '+20',
    ]);

    $response->assertSuccessful();
    expect($response->json('order.status'))->toBe('processing');
    expect($response->json('order.payment_status'))->toBe('completed');
    expect($response->json('order.total'))->toBe(150.50);
});

it('tracking page is accessible', function () {
    $response = $this->get(route('orders.track.page'));

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('Track'));
});
