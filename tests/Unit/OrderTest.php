<?php

declare(strict_types=1);

use App\Models\User;
use App\Plugins\GuestCheckout\src\Models\GuestUser;
use App\Plugins\OrderManagement\src\Models\Order;
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

    it('returns snapshot name for guest orders', function () {
        $guestUser = GuestUser::factory()->create();

        $order = Order::factory()->create([
            'customer_name' => 'Jane Guest',
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

    it('prioritizes snapshot name over user name when both exist', function () {
        $user = User::factory()->create(['name' => 'Registered User']);

        $order = Order::factory()->create([
            'customer_name' => 'Snapshot Customer',
            'user_id' => $user->id,
            'guest_user_id' => null,
        ]);

        expect($order->getCustomerName())->toBe('Snapshot Customer');
    });
});

describe('getCustomerPhone', function () {
    it('returns user phone for user orders', function () {
        $user = User::factory()->create();
        $user->phone = '1234567890';

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'guest_user_id' => null,
        ]);
        $order->setRelation('user', $user);

        expect($order->getCustomerPhone())->toBe('1234567890');
    });

    it('returns snapshot phone for guest orders', function () {
        $guestUser = GuestUser::factory()->create();

        $order = Order::factory()->create([
            'customer_phone' => '9876543210',
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

    it('prioritizes snapshot phone over user phone', function () {
        $user = User::factory()->create();
        $user->phone = '1111111111';

        $order = Order::factory()->create([
            'customer_phone' => '2222222222',
            'user_id' => $user->id,
            'guest_user_id' => null,
        ]);
        $order->setRelation('user', $user);

        expect($order->getCustomerPhone())->toBe('2222222222');
    });

    it('handles null phone gracefully', function () {
        $user = User::factory()->create();

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

    it('returns snapshot email for guest orders', function () {
        $guestUser = GuestUser::factory()->create();

        $order = Order::factory()->create([
            'customer_email' => 'guest@example.com',
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

    it('prioritizes snapshot email over user email', function () {
        $user = User::factory()->create(['email' => 'user@example.com']);

        $order = Order::factory()->create([
            'customer_email' => 'snapshot@example.com',
            'user_id' => $user->id,
            'guest_user_id' => null,
        ]);

        expect($order->getCustomerEmail())->toBe('snapshot@example.com');
    });

    it('handles null email gracefully for guest', function () {
        $guestUser = GuestUser::factory()->create();

        $order = Order::factory()->create([
            'customer_email' => null,
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

    it('getCustomerName works with snapshot column', function () {
        $guestUser = GuestUser::factory()->create();

        $order = Order::factory()->create([
            'customer_name' => 'Snapshot Guest',
            'guest_user_id' => $guestUser->id,
            'user_id' => null,
        ]);

        $loadedOrder = Order::find($order->id);

        expect($loadedOrder->getCustomerName())->toBe('Snapshot Guest');
    });

    it('getCustomerPhone works with snapshot column', function () {
        $guestUser = GuestUser::factory()->create();

        $order = Order::factory()->create([
            'customer_phone' => '5555555555',
            'guest_user_id' => $guestUser->id,
            'user_id' => null,
        ]);

        $freshOrder = Order::find($order->id);

        expect($freshOrder->getCustomerPhone())->toBe('5555555555');
    });
});
