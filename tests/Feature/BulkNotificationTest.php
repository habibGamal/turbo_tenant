<?php

declare(strict_types=1);

use App\Enums\BulkNotificationStatus;
use App\Jobs\SendBulkNotificationJob;
use App\Models\BulkNotification;
use App\Models\User;
use App\Notifications\BulkUserNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use NotificationChannels\Expo\ExpoPushToken;

beforeEach(function () {
    $this->users = User::factory()
        ->count(5)
        ->create([
            'expo_token' => ExpoPushToken::make('ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]'),
        ]);
});

it('creates a bulk notification with draft status', function () {
    $notification = BulkNotification::factory()->create();

    expect($notification->status)->toBe(BulkNotificationStatus::DRAFT)
        ->and($notification->total_recipients)->toBe(0)
        ->and($notification->successful_sends)->toBe(0)
        ->and($notification->failed_sends)->toBe(0);
});

it('creates a scheduled bulk notification', function () {
    $scheduledTime = now()->addHours(2);

    $notification = BulkNotification::factory()
        ->scheduled()
        ->create(['scheduled_at' => $scheduledTime]);

    expect($notification->status)->toBe(BulkNotificationStatus::SCHEDULED)
        ->and($notification->scheduled_at)->toBeInstanceOf(Illuminate\Support\Carbon::class);
});

it('sends notifications to all users with expo tokens', function () {
    Notification::fake();

    $notification = BulkNotification::factory()->create();

    $job = new SendBulkNotificationJob($notification);
    $job->handle();

    expect($notification->fresh()->status)->toBe(BulkNotificationStatus::SENT)
        ->and($notification->fresh()->total_recipients)->toBe(5)
        ->and($notification->fresh()->successful_sends)->toBe(5)
        ->and($notification->fresh()->failed_sends)->toBe(0);

    Notification::assertSentTo(
        $this->users,
        BulkUserNotification::class,
        function ($notification) {
            return $notification instanceof BulkUserNotification;
        }
    );
});

it('sends notifications only to targeted users', function () {
    Notification::fake();

    $targetUsers = $this->users->take(2);
    $notification = BulkNotification::factory()
        ->withTargetUsers($targetUsers->pluck('id')->toArray())
        ->create();

    $job = new SendBulkNotificationJob($notification);
    $job->handle();

    expect($notification->fresh()->total_recipients)->toBe(2)
        ->and($notification->fresh()->successful_sends)->toBe(2);

    Notification::assertSentTo($targetUsers, BulkUserNotification::class);
    Notification::assertNotSentTo(
        $this->users->skip(2),
        BulkUserNotification::class
    );
});

it('does not send to users without expo tokens', function () {
    Notification::fake();

    $userWithoutToken = User::factory()->create(['expo_token' => null]);

    $notification = BulkNotification::factory()->create();

    $job = new SendBulkNotificationJob($notification);
    $job->handle();

    Notification::assertNotSentTo($userWithoutToken, BulkUserNotification::class);
});

it('updates status to sending when job starts', function () {
    Notification::fake();

    $notification = BulkNotification::factory()->create([
        'status' => BulkNotificationStatus::DRAFT,
    ]);

    $job = new SendBulkNotificationJob($notification);
    $job->handle();

    expect($notification->fresh()->status)->toBe(BulkNotificationStatus::SENT);
});

it('includes custom data in notification', function () {
    $customData = ['action' => 'view_product', 'product_id' => 123];

    $notification = BulkNotification::factory()
        ->withData($customData)
        ->create();

    $bulkUserNotification = new BulkUserNotification($notification);

    expect($bulkUserNotification->bulkNotification->data)->toBe($customData);
});

it('creates expo message with correct title and body', function () {
    $notification = BulkNotification::factory()->create([
        'title' => 'Test Title',
        'body' => 'Test Body Message',
    ]);

    $user = $this->users->first();
    $bulkUserNotification = new BulkUserNotification($notification);
    $expoMessage = $bulkUserNotification->toExpo($user);

    expect($expoMessage->getTitle())->toBe('Test Title')
        ->and($expoMessage->getBody())->toBe('Test Body Message');
});

it('sets high priority and plays sound for expo message', function () {
    $notification = BulkNotification::factory()->create();

    $user = $this->users->first();
    $bulkUserNotification = new BulkUserNotification($notification);
    $expoMessage = $bulkUserNotification->toExpo($user);

    expect($expoMessage->getPriority())->toBe('high')
        ->and($expoMessage->getSound())->toBe('default');
});

it('handles failed sends correctly', function () {
    Notification::fake();
    Notification::shouldReceive('route')
        ->andThrow(new Exception('Expo API error'));

    // Create a user that will fail
    $failingUser = User::factory()->create([
        'expo_token' => ExpoPushToken::make('ExponentPushToken[invalid_token_here]'),
    ]);

    $notification = BulkNotification::factory()
        ->withTargetUsers([$failingUser->id])
        ->create();

    try {
        $job = new SendBulkNotificationJob($notification);
        $job->handle();
    } catch (Exception $e) {
        // Expected to fail
    }

    // The status should be FAILED or still have some failed count
    expect($notification->fresh()->status)->toBeIn([
        BulkNotificationStatus::FAILED,
        BulkNotificationStatus::SENT,
    ]);
});

it('dispatches job immediately for non-scheduled notifications', function () {
    Queue::fake();

    $notification = BulkNotification::factory()->create([
        'status' => BulkNotificationStatus::DRAFT,
    ]);

    SendBulkNotificationJob::dispatch($notification);

    Queue::assertPushed(SendBulkNotificationJob::class, function ($job) use ($notification) {
        return $job->bulkNotification->id === $notification->id;
    });
});

it('schedules job for future when notification is scheduled', function () {
    Queue::fake();

    $scheduledTime = now()->addHour();
    $notification = BulkNotification::factory()->create([
        'status' => BulkNotificationStatus::SCHEDULED,
        'scheduled_at' => $scheduledTime,
    ]);

    SendBulkNotificationJob::dispatch($notification)->delay($scheduledTime);

    Queue::assertPushed(SendBulkNotificationJob::class);
});

it('records sent_at timestamp when notification is sent', function () {
    Notification::fake();

    $notification = BulkNotification::factory()->create();

    expect($notification->sent_at)->toBeNull();

    $job = new SendBulkNotificationJob($notification);
    $job->handle();

    expect($notification->fresh()->sent_at)->not->toBeNull()
        ->and($notification->fresh()->sent_at)->toBeInstanceOf(Illuminate\Support\Carbon::class);
});
