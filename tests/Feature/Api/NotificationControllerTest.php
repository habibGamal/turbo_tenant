<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserNotification;
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
        ->assertJsonStructure([
            'notifications' => [
                '*' => ['id', 'title', 'body', 'read', 'type', 'created_at'],
            ],
            'unread_count',
        ])
        ->assertJsonCount(5, 'notifications');
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
        ->assertJsonCount(3, 'notifications');
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
        ->assertJson(['count' => 3]);
});

it('marks notification as read', function () {
    $notification = UserNotification::factory()->unread()->create([
        'user_id' => $this->user->id,
    ]);

    $response = postJson("/api/notifications/{$notification->id}/mark-as-read");

    $response->assertSuccessful()
        ->assertJson([
            'message' => 'Notification marked as read',
        ]);

    expect($notification->fresh()->read)->toBeTrue();
});

it('cannot mark other user notification as read', function () {
    $otherUser = User::factory()->create();
    $notification = UserNotification::factory()->unread()->create([
        'user_id' => $otherUser->id,
    ]);

    $response = postJson("/api/notifications/{$notification->id}/mark-as-read");

    $response->assertNotFound();
});

it('marks all notifications as read', function () {
    UserNotification::factory()->count(5)->unread()->create([
        'user_id' => $this->user->id,
    ]);

    $response = postJson('/api/notifications/mark-all-as-read');

    $response->assertSuccessful()
        ->assertJson([
            'message' => 'All notifications marked as read',
        ]);

    expect($this->user->notifications()->unread()->count())->toBe(0);
});

it('deletes a notification', function () {
    $notification = UserNotification::factory()->create([
        'user_id' => $this->user->id,
    ]);

    $response = deleteJson("/api/notifications/{$notification->id}");

    $response->assertSuccessful()
        ->assertJson([
            'message' => 'Notification deleted',
        ]);

    expect(UserNotification::find($notification->id))->toBeNull();
});

it('cannot delete other user notification', function () {
    $otherUser = User::factory()->create();
    $notification = UserNotification::factory()->create([
        'user_id' => $otherUser->id,
    ]);

    $response = deleteJson("/api/notifications/{$notification->id}");

    $response->assertNotFound();
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
        ->assertJson([
            'message' => 'Read notifications cleared',
        ]);

    expect($this->user->notifications()->count())->toBe(2)
        ->and($this->user->notifications()->read()->count())->toBe(0);
});

it('requires authentication for all endpoints', function () {
    auth()->logout();

    getJson('/api/notifications')->assertUnauthorized();
    getJson('/api/notifications/unread-count')->assertUnauthorized();
    postJson('/api/notifications/1/mark-as-read')->assertUnauthorized();
    postJson('/api/notifications/mark-all-as-read')->assertUnauthorized();
    deleteJson('/api/notifications/1')->assertUnauthorized();
    postJson('/api/notifications/clear-read')->assertUnauthorized();
});

it('limits notifications to 50', function () {
    UserNotification::factory()->count(100)->create([
        'user_id' => $this->user->id,
    ]);

    $response = getJson('/api/notifications');

    $response->assertSuccessful()
        ->assertJsonCount(50, 'notifications');
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

    $response->assertSuccessful();

    $notifications = $response->json('notifications');

    expect($notifications[0]['id'])->toBe($newest->id)
        ->and($notifications[1]['id'])->toBe($middle->id)
        ->and($notifications[2]['id'])->toBe($oldest->id);
});
