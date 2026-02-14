<?php

declare(strict_types=1);

use App\Models\Area;
use App\Models\GuestUser;
use App\Models\Order;
use App\Models\User;
use App\Services\GuestUserService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(GuestUserService::class);
});

describe('findOrCreate', function () {
    it('creates a new guest user when none exists', function () {
        $guestData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
            'phone_country_code' => '+20',
            'street' => 'Main St',
            'building' => '123',
            'floor' => '5',
            'apartment' => '10',
            'city' => 'Cairo',
        ];

        $guestUser = $this->service->findOrCreate($guestData);

        expect($guestUser)->toBeInstanceOf(GuestUser::class);
        expect($guestUser->name)->toBe('John Doe');
        expect($guestUser->email)->toBe('john@example.com');
        expect($guestUser->phone)->toBe('1234567890');
        expect($guestUser->phone_country_code)->toBe('+20');
        expect($guestUser->street)->toBe('Main St');

        $this->assertDatabaseHas('guest_users', [
            'name' => 'John Doe',
            'phone' => '1234567890',
            'phone_country_code' => '+20',
        ]);
    });

    it('finds existing guest user by phone and country code', function () {
        $existingGuest = GuestUser::factory()->create([
            'phone' => '1234567890',
            'phone_country_code' => '+20',
            'name' => 'Original Name',
            'email' => 'original@example.com',
        ]);

        $guestData = [
            'name' => 'Updated Name',
            'phone' => '1234567890',
            'phone_country_code' => '+20',
        ];

        $guestUser = $this->service->findOrCreate($guestData);

        expect($guestUser->id)->toBe($existingGuest->id);
        expect(GuestUser::count())->toBe(1);
    });

    it('updates existing guest user information when found', function () {
        $existingGuest = GuestUser::factory()->create([
            'phone' => '1234567890',
            'phone_country_code' => '+20',
            'name' => 'Original Name',
            'email' => 'original@example.com',
            'street' => 'Old Street',
        ]);

        $guestData = [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'phone' => '1234567890',
            'phone_country_code' => '+20',
            'street' => 'New Street',
            'building' => '456',
        ];

        $guestUser = $this->service->findOrCreate($guestData);

        expect($guestUser->id)->toBe($existingGuest->id);
        expect($guestUser->name)->toBe('Updated Name');
        expect($guestUser->email)->toBe('updated@example.com');
        expect($guestUser->street)->toBe('New Street');
        expect($guestUser->building)->toBe('456');
    });

    it('handles different country codes correctly', function () {
        $guest1 = GuestUser::factory()->egyptian()->create([
            'phone' => '1234567890',
        ]);

        $guest2 = GuestUser::factory()->saudi()->create([
            'phone' => '1234567890',
        ]);

        // Find Egyptian guest
        $foundGuest1 = $this->service->findOrCreate([
            'phone' => '1234567890',
            'phone_country_code' => '+20',
            'name' => 'Test',
        ]);

        // Find Saudi guest
        $foundGuest2 = $this->service->findOrCreate([
            'phone' => '1234567890',
            'phone_country_code' => '+966',
            'name' => 'Test',
        ]);

        expect($foundGuest1->id)->toBe($guest1->id);
        expect($foundGuest2->id)->toBe($guest2->id);
        expect($foundGuest1->id)->not->toBe($foundGuest2->id);
    });

    it('uses default country code +20 when not provided', function () {
        $guestData = [
            'name' => 'John Doe',
            'phone' => '1234567890',
        ];

        $guestUser = $this->service->findOrCreate($guestData);

        expect($guestUser->phone_country_code)->toBe('+20');
        $this->assertDatabaseHas('guest_users', [
            'phone' => '1234567890',
            'phone_country_code' => '+20',
        ]);
    });

    it('preserves existing data when updating with partial information', function () {
        $area = Area::factory()->create();
        $existingGuest = GuestUser::factory()->create([
            'phone' => '1234567890',
            'phone_country_code' => '+20',
            'name' => 'Original Name',
            'email' => 'original@example.com',
            'street' => 'Old Street',
            'building' => '100',
            'area_id' => $area->id,
        ]);

        // Update with partial data (only name)
        $guestData = [
            'name' => 'Updated Name',
            'phone' => '1234567890',
            'phone_country_code' => '+20',
        ];

        $guestUser = $this->service->findOrCreate($guestData);

        expect($guestUser->id)->toBe($existingGuest->id);
        expect($guestUser->name)->toBe('Updated Name');
        expect($guestUser->email)->toBe('original@example.com'); // Preserved
        expect($guestUser->street)->toBe('Old Street'); // Preserved
        expect($guestUser->building)->toBe('100'); // Preserved
        expect($guestUser->area_id)->toBe($area->id); // Preserved
    });
});

describe('convertToUser', function () {
    it('transfers all guest orders to user account', function () {
        $guestUser = GuestUser::factory()->create();
        $user = User::factory()->create();

        // Create orders for guest user
        $order1 = Order::factory()->create(['guest_user_id' => $guestUser->id, 'user_id' => null]);
        $order2 = Order::factory()->create(['guest_user_id' => $guestUser->id, 'user_id' => null]);
        $order3 = Order::factory()->create(['guest_user_id' => $guestUser->id, 'user_id' => null]);

        $this->service->convertToUser($guestUser, $user);

        // Verify all orders now belong to the user, not the guest
        $this->assertDatabaseHas('orders', [
            'id' => $order1->id,
            'user_id' => $user->id,
            'guest_user_id' => null,
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order2->id,
            'user_id' => $user->id,
            'guest_user_id' => null,
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order3->id,
            'user_id' => $user->id,
            'guest_user_id' => null,
        ]);
    });

    it('handles multiple orders correctly', function () {
        $guestUser = GuestUser::factory()->create();
        $user = User::factory()->create();

        // Create 5 orders for guest user
        $orderIds = Order::factory()
            ->count(5)
            ->create(['guest_user_id' => $guestUser->id, 'user_id' => null])
            ->pluck('id');

        expect($guestUser->orders()->count())->toBe(5);
        expect($user->orders()->count())->toBe(0);

        $this->service->convertToUser($guestUser, $user);

        $guestUser->refresh();
        $user->refresh();

        expect($guestUser->orders()->count())->toBe(0);
        expect($user->orders()->count())->toBe(5);

        foreach ($orderIds as $orderId) {
            $this->assertDatabaseHas('orders', [
                'id' => $orderId,
                'user_id' => $user->id,
                'guest_user_id' => null,
            ]);
        }
    });

    it('clears guest_user_id and sets user_id correctly', function () {
        $guestUser = GuestUser::factory()->create();
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'guest_user_id' => $guestUser->id,
            'user_id' => null,
        ]);

        expect($order->guest_user_id)->toBe($guestUser->id);
        expect($order->user_id)->toBeNull();

        $this->service->convertToUser($guestUser, $user);

        $order->refresh();

        expect($order->user_id)->toBe($user->id);
        expect($order->guest_user_id)->toBeNull();
    });

    it('does not affect orders from other guests', function () {
        $guestUser1 = GuestUser::factory()->create();
        $guestUser2 = GuestUser::factory()->create();
        $user = User::factory()->create();

        $order1 = Order::factory()->create(['guest_user_id' => $guestUser1->id, 'user_id' => null]);
        $order2 = Order::factory()->create(['guest_user_id' => $guestUser2->id, 'user_id' => null]);

        $this->service->convertToUser($guestUser1, $user);

        // Guest 1's order transferred
        $this->assertDatabaseHas('orders', [
            'id' => $order1->id,
            'user_id' => $user->id,
            'guest_user_id' => null,
        ]);

        // Guest 2's order unchanged
        $this->assertDatabaseHas('orders', [
            'id' => $order2->id,
            'user_id' => null,
            'guest_user_id' => $guestUser2->id,
        ]);
    });

    it('handles guest with no orders gracefully', function () {
        $guestUser = GuestUser::factory()->create();
        $user = User::factory()->create();

        expect($guestUser->orders()->count())->toBe(0);

        // Should not throw exception
        $this->service->convertToUser($guestUser, $user);

        expect($user->orders()->count())->toBe(0);
    });
});
