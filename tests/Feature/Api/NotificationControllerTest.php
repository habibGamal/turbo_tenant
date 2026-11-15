<?php

declare(strict_types=1);

use App\Models\User;
use App\Plugins\PushNotifications\src\Models\UserNotification;
use NotificationChannels\Expo\ExpoPushToken;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->user = User::factory()->create([
        'expo_token' => ExpoPushToken::make('ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]'),
    ]);

    actingAs($this->user);
});

it('returns user notifications', function () {
    UserNotification::factory()->count(5)->create([
        'user_id' => $this->user->id,
    ]);

    $response = getJson('/api/notifications');

    $response->assertSuccessful()
        ->assertJsonPath('status', 'success')
        ->assertJsonStructure([
            'status',
            'data' => [
                '*' => ['id', 'title', 'body', 'read', 'type', 'created_at'],
            ],
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
            ],
        ])
        ->assertJsonCount(5, 'data');
});

it('returns only user own notifications', function () {
    $otherUser = User::factory()->create();

    UserNotification::factory()->count(3)->create([
        'user_id' => $this->user->id,
    ]);

    UserNotification::factory()->count(2)->create([
        'user_id' => $otherUser->id,
    ]);

    $response = getJson('/api/notifications');

    $response->assertSuccessful()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(3, 'data');
});

it('returns unread count', function () {
    UserNotification::factory()->count(3)->unread()->create([
        'user_id' => $this->user->id,
    ]);
    UserNotification::factory()->count(2)->read()->create([
        'user_id' => $this->user->id,
    ]);

    $response = getJson('/api/notifications/unread-count');

    $response->assertSuccessful()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.count', 3);
});

it('marks notification as read', function () {
    $notification = UserNotification::factory()->unread()->create([
        'user_id' => $this->user->id,
    ]);

    $response = postJson("/api/notifications/{$notification->id}/mark-as-read");

    $response->assertSuccessful()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Notification marked as read')
        ->assertJsonPath('data.read', true);

    expect($notification->fresh()->read)->toBeTrue();
});

it('cannot mark other user notification as read', function () {
    $otherUser = User::factory()->create();
    $notification = UserNotification::factory()->unread()->create([
        'user_id' => $otherUser->id,
    ]);

    $response = postJson("/api/notifications/{$notification->id}/mark-as-read");

    $response->assertNotFound()
        ->assertJsonPath('status', 'error');
});

it('marks all notifications as read', function () {
    UserNotification::factory()->count(5)->unread()->create([
        'user_id' => $this->user->id,
    ]);

    $response = postJson('/api/notifications/mark-all-as-read');

    $response->assertSuccessful()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'All notifications marked as read');

    expect($this->user->pushNotifications()->unread()->count())->toBe(0);
});

it('deletes a notification', function () {
    $notification = UserNotification::factory()->create([
        'user_id' => $this->user->id,
    ]);

    $response = deleteJson("/api/notifications/{$notification->id}");

    $response->assertSuccessful()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Notification deleted');

    expect(UserNotification::find($notification->id))->toBeNull();
});

it('cannot delete other user notification', function () {
    $otherUser = User::factory()->create();
    $notification = UserNotification::factory()->create([
        'user_id' => $otherUser->id,
    ]);

    $response = deleteJson("/api/notifications/{$notification->id}");

    $response->assertNotFound()
        ->assertJsonPath('status', 'error');
});

it('clears all read notifications', function () {
    UserNotification::factory()->count(3)->read()->create([
        'user_id' => $this->user->id,
    ]);
    UserNotification::factory()->count(2)->unread()->create([
        'user_id' => $this->user->id,
    ]);

    $response = postJson('/api/notifications/clear-read');

    $response->assertSuccessful()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('message', 'Read notifications cleared');

    expect($this->user->pushNotifications()->count())->toBe(2)
        ->and($this->user->pushNotifications()->read()->count())->toBe(0);
});

it('requires authentication for all endpoints', function () {
    auth()->logout();

    getJson('/api/notifications')->assertUnauthorized()->assertJsonPath('status', 'error');
    getJson('/api/notifications/unread-count')->assertUnauthorized()->assertJsonPath('status', 'error');
    postJson('/api/notifications/1/mark-as-read')->assertUnauthorized()->assertJsonPath('status', 'error');
    postJson('/api/notifications/mark-all-as-read')->assertUnauthorized()->assertJsonPath('status', 'error');
    deleteJson('/api/notifications/1')->assertUnauthorized()->assertJsonPath('status', 'error');
    postJson('/api/notifications/clear-read')->assertUnauthorized()->assertJsonPath('status', 'error');
});

it('limits notifications to 50', function () {
    UserNotification::factory()->count(100)->create([
        'user_id' => $this->user->id,
    ]);

    $response = getJson('/api/notifications');

    $response->assertSuccessful()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(50, 'data')
        ->assertJsonPath('meta.per_page', 50);
});

it('orders notifications by created_at desc', function () {
    $oldest = UserNotification::factory()->create([
        'user_id' => $this->user->id,
        'created_at' => now()->subDays(3),
    ]);

    $middle = UserNotification::factory()->create([
        'user_id' => $this->user->id,
        'created_at' => now()->subDays(2),
    ]);

    $newest = UserNotification::factory()->create([
        'user_id' => $this->user->id,
        'created_at' => now()->subDay(),
    ]);

    $response = getJson('/api/notifications');

    $response->assertSuccessful()
        ->assertJsonPath('status', 'success');

    $notifications = $response->json('data');

    expect($notifications[0]['id'])->toBe($newest->id)
        ->and($notifications[1]['id'])->toBe($middle->id)
        ->and($notifications[2]['id'])->toBe($oldest->id);
});

it('rejects non-integer id with 404 via typed route parameter', function () {
    postJson('/api/notifications/abc/mark-as-read')
        ->assertNotFound()
        ->assertJsonPath('status', 'error');

    deleteJson('/api/notifications/abc')
        ->assertNotFound()
        ->assertJsonPath('status', 'error');
});
