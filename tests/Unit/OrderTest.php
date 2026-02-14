<?php

declare(strict_types=1);

use App\Models\GuestUser;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('isGuestOrder', function () {
    it('returns true for guest orders', function () {
        $guestUser = GuestUser::factory()->create();

        $order = Order::factory()->create([
            'guest_user_id' => $guestUser->id,
            'user_id' => null,
        ]);

        expect($order->isGuestOrder())->toBeTrue();
    });

    it('returns false for user orders', function () {
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'guest_user_id' => null,
        ]);

        expect($order->isGuestOrder())->toBeFalse();
    });

    it('returns false when both user_id and guest_user_id are null', function () {
        $order = Order::factory()->create([
            'user_id' => null,
            'guest_user_id' => null,
        ]);

        expect($order->isGuestOrder())->toBeFalse();
    });
});

describe('getCustomerName', function () {
    it('returns user name for user orders', function () {
        $user = User::factory()->create(['name' => 'John Doe']);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'guest_user_id' => null,
        ]);

        expect($order->getCustomerName())->toBe('John Doe');
    });

    it('returns guest user name for guest orders', function () {
        $guestUser = GuestUser::factory()->create(['name' => 'Jane Guest']);

        $order = Order::factory()->create([
            'guest_user_id' => $guestUser->id,
            'user_id' => null,
        ]);

        expect($order->getCustomerName())->toBe('Jane Guest');
    });

    it('returns Unknown when both user and guest are null', function () {
        $order = Order::factory()->create([
            'user_id' => null,
            'guest_user_id' => null,
        ]);

        expect($order->getCustomerName())->toBe('Unknown');
    });

    it('prioritizes user name over guest name when both exist', function () {
        $user = User::factory()->create(['name' => 'Registered User']);
        $guestUser = GuestUser::factory()->create(['name' => 'Guest User']);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'guest_user_id' => $guestUser->id, // Should not happen, but testing priority
        ]);

        expect($order->getCustomerName())->toBe('Registered User');
    });
});

describe('getCustomerPhone', function () {
    it('returns user phone for user orders', function () {
        $user = User::factory()->create(['phone' => '1234567890']);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'guest_user_id' => null,
        ]);

        expect($order->getCustomerPhone())->toBe('1234567890');
    });

    it('returns guest user phone for guest orders', function () {
        $guestUser = GuestUser::factory()->create(['phone' => '9876543210']);

        $order = Order::factory()->create([
            'guest_user_id' => $guestUser->id,
            'user_id' => null,
        ]);

        expect($order->getCustomerPhone())->toBe('9876543210');
    });

    it('returns null when both user and guest are null', function () {
        $order = Order::factory()->create([
            'user_id' => null,
            'guest_user_id' => null,
        ]);

        expect($order->getCustomerPhone())->toBeNull();
    });

    it('prioritizes user phone over guest phone', function () {
        $user = User::factory()->create(['phone' => '1111111111']);
        $guestUser = GuestUser::factory()->create(['phone' => '2222222222']);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'guest_user_id' => $guestUser->id,
        ]);

        expect($order->getCustomerPhone())->toBe('1111111111');
    });

    it('handles null phone gracefully', function () {
        $user = User::factory()->create(['phone' => null]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'guest_user_id' => null,
        ]);

        expect($order->getCustomerPhone())->toBeNull();
    });
});

describe('getCustomerEmail', function () {
    it('returns user email for user orders', function () {
        $user = User::factory()->create(['email' => 'user@example.com']);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'guest_user_id' => null,
        ]);

        expect($order->getCustomerEmail())->toBe('user@example.com');
    });

    it('returns guest user email for guest orders', function () {
        $guestUser = GuestUser::factory()->create(['email' => 'guest@example.com']);

        $order = Order::factory()->create([
            'guest_user_id' => $guestUser->id,
            'user_id' => null,
        ]);

        expect($order->getCustomerEmail())->toBe('guest@example.com');
    });

    it('returns null when both user and guest are null', function () {
        $order = Order::factory()->create([
            'user_id' => null,
            'guest_user_id' => null,
        ]);

        expect($order->getCustomerEmail())->toBeNull();
    });

    it('prioritizes user email over guest email', function () {
        $user = User::factory()->create(['email' => 'user@example.com']);
        $guestUser = GuestUser::factory()->create(['email' => 'guest@example.com']);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'guest_user_id' => $guestUser->id,
        ]);

        expect($order->getCustomerEmail())->toBe('user@example.com');
    });

    it('handles null email gracefully for guest', function () {
        $guestUser = GuestUser::factory()->create(['email' => null]);

        $order = Order::factory()->create([
            'guest_user_id' => $guestUser->id,
            'user_id' => null,
        ]);

        expect($order->getCustomerEmail())->toBeNull();
    });
});

describe('relationships', function () {
    it('has a user relationship', function () {
        $user = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $user->id,
        ]);

        expect($order->user)->toBeInstanceOf(User::class);
        expect($order->user->id)->toBe($user->id);
    });

    it('has a guestUser relationship', function () {
        $guestUser = GuestUser::factory()->create();

        $order = Order::factory()->create([
            'guest_user_id' => $guestUser->id,
        ]);

        expect($order->guestUser)->toBeInstanceOf(GuestUser::class);
        expect($order->guestUser->id)->toBe($guestUser->id);
    });

    it('can eager load user relationship', function () {
        $user = User::factory()->create(['name' => 'Test User']);

        $order = Order::factory()->create([
            'user_id' => $user->id,
        ]);

        $loadedOrder = Order::with('user')->find($order->id);

        expect($loadedOrder->user)->not->toBeNull();
        expect($loadedOrder->user->name)->toBe('Test User');
    });

    it('can eager load guestUser relationship', function () {
        $guestUser = GuestUser::factory()->create(['name' => 'Test Guest']);

        $order = Order::factory()->create([
            'guest_user_id' => $guestUser->id,
        ]);

        $loadedOrder = Order::with('guestUser')->find($order->id);

        expect($loadedOrder->guestUser)->not->toBeNull();
        expect($loadedOrder->guestUser->name)->toBe('Test Guest');
    });
});

describe('helper methods with relationships', function () {
    it('getCustomerName works with eager loaded user', function () {
        $user = User::factory()->create(['name' => 'Eager User']);

        $order = Order::factory()->create([
            'user_id' => $user->id,
        ]);

        $loadedOrder = Order::with('user')->find($order->id);

        expect($loadedOrder->getCustomerName())->toBe('Eager User');
    });

    it('getCustomerName works with eager loaded guestUser', function () {
        $guestUser = GuestUser::factory()->create(['name' => 'Eager Guest']);

        $order = Order::factory()->create([
            'guest_user_id' => $guestUser->id,
        ]);

        $loadedOrder = Order::with('guestUser')->find($order->id);

        expect($loadedOrder->getCustomerName())->toBe('Eager Guest');
    });

    it('getCustomerPhone works without eager loading', function () {
        $guestUser = GuestUser::factory()->create(['phone' => '5555555555']);

        $order = Order::factory()->create([
            'guest_user_id' => $guestUser->id,
        ]);

        // Fresh query without eager loading
        $freshOrder = Order::find($order->id);

        expect($freshOrder->getCustomerPhone())->toBe('5555555555');
    });
});
