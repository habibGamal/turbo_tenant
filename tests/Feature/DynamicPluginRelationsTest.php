<?php

declare(strict_types=1);

use App\Models\User;
use App\Plugins\LocationsDelivery\src\Models\Address;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\OrderManagement\src\Models\Order;
use App\Plugins\PushNotifications\src\Models\UserNotification;

it('resolves user addresses via dynamic relation registration', function () {
    $user = User::factory()->create();

    $address = Address::factory()->create([
        'user_id' => $user->id,
    ]);

    expect($user->addresses)->toHaveCount(1)
        ->and($user->addresses->first()->id)->toBe($address->id);
});

it('resolves user orders via dynamic relation registration', function () {
    $user = User::factory()->create();

    $order = Order::factory()->create([
        'user_id' => $user->id,
    ]);

    expect($user->orders)->toHaveCount(1)
        ->and($user->orders->first()->id)->toBe($order->id);
});

it('resolves branch orders via dynamic relation registration', function () {
    $branch = Branch::factory()->create();

    $order = Order::factory()->create([
        'branch_id' => $branch->id,
    ]);

    expect($branch->orders)->toHaveCount(1)
        ->and($branch->orders->first()->id)->toBe($order->id);
});

it('resolves user pushNotifications and unreadPushNotifications via dynamic relations', function () {
    $user = User::factory()->create();

    UserNotification::factory()->create([
        'user_id' => $user->id,
        'read' => false,
    ]);
    UserNotification::factory()->create([
        'user_id' => $user->id,
        'read' => true,
    ]);

    expect($user->pushNotifications)->toHaveCount(2)
        ->and($user->unreadPushNotifications)->toHaveCount(1);
});
