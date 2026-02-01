<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserNotification;
use NotificationChannels\Expo\ExpoPushToken;

beforeEach(function () {
    $this->user = User::factory()->create([
        'expo_token' => ExpoPushToken::make('ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]'),
    ]);
});

it('creates a user notification', function () {
    $notification = UserNotification::factory()->create([
        'user_id' => $this->user->id,
    ]);

    expect($notification->user_id)->toBe($this->user->id)
        ->and($notification->title)->not->toBeEmpty()
        ->and($notification->body)->not->toBeEmpty()
        ->and($notification->read)->toBeFalse()
        ->and($notification->read_at)->toBeNull();
});

it('user has many notifications', function () {
    UserNotification::factory()->count(5)->create([
        'user_id' => $this->user->id,
    ]);

    expect($this->user->notifications)->toHaveCount(5);
});

it('can mark notification as read', function () {
    $notification = UserNotification::factory()->unread()->create([
        'user_id' => $this->user->id,
    ]);

    expect($notification->read)->toBeFalse();

    $notification->markAsRead();

    expect($notification->fresh()->read)->toBeTrue()
        ->and($notification->fresh()->read_at)->not->toBeNull();
});

it('does not update read_at when already read', function () {
    $notification = UserNotification::factory()->read()->create([
        'user_id' => $this->user->id,
    ]);

    $originalReadAt = $notification->read_at;

    sleep(1);
    $notification->markAsRead();

    expect($notification->fresh()->read_at->timestamp)->toBe($originalReadAt->timestamp);
});

it('can mark notification as unread', function () {
    $notification = UserNotification::factory()->read()->create([
        'user_id' => $this->user->id,
    ]);

    expect($notification->read)->toBeTrue();

    $notification->markAsUnread();

    expect($notification->fresh()->read)->toBeFalse()
        ->and($notification->fresh()->read_at)->toBeNull();
});

it('scopes unread notifications', function () {
    UserNotification::factory()->count(3)->unread()->create([
        'user_id' => $this->user->id,
    ]);
    UserNotification::factory()->count(2)->read()->create([
        'user_id' => $this->user->id,
    ]);

    $unreadCount = UserNotification::unread()->count();

    expect($unreadCount)->toBe(3);
});

it('scopes read notifications', function () {
    UserNotification::factory()->count(3)->unread()->create([
        'user_id' => $this->user->id,
    ]);
    UserNotification::factory()->count(2)->read()->create([
        'user_id' => $this->user->id,
    ]);

    $readCount = UserNotification::read()->count();

    expect($readCount)->toBe(2);
});

it('user has unread notifications relationship', function () {
    UserNotification::factory()->count(3)->unread()->create([
        'user_id' => $this->user->id,
    ]);
    UserNotification::factory()->count(2)->read()->create([
        'user_id' => $this->user->id,
    ]);

    expect($this->user->unreadNotifications)->toHaveCount(3);
});

it('can store notification data as json', function () {
    $data = [
        'order_id' => 123,
        'order_number' => 'ORD-123456',
        'status' => 'delivered',
    ];

    $notification = UserNotification::factory()->create([
        'user_id' => $this->user->id,
        'data' => $data,
    ]);

    expect($notification->fresh()->data)->toBe($data);
});

it('belongs to a user', function () {
    $notification = UserNotification::factory()->create([
        'user_id' => $this->user->id,
    ]);

    expect($notification->user->id)->toBe($this->user->id);
});

it('cascades deletion when user is deleted', function () {
    $notification = UserNotification::factory()->create([
        'user_id' => $this->user->id,
    ]);

    $notificationId = $notification->id;

    $this->user->delete();

    expect(UserNotification::find($notificationId))->toBeNull();
});

it('creates notification with different types', function () {
    $generalNotification = UserNotification::factory()->create([
        'user_id' => $this->user->id,
        'type' => 'general',
    ]);

    $orderNotification = UserNotification::factory()->order()->create([
        'user_id' => $this->user->id,
    ]);

    $bulkNotification = UserNotification::factory()->bulk()->create([
        'user_id' => $this->user->id,
    ]);

    expect($generalNotification->type)->toBe('general')
        ->and($orderNotification->type)->toBe('order')
        ->and($bulkNotification->type)->toBe('bulk');
});
