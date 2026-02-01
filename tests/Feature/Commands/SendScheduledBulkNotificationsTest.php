<?php

declare(strict_types=1);

use App\Console\Commands\SendScheduledBulkNotifications;
use App\Enums\BulkNotificationStatus;
use App\Jobs\SendBulkNotificationJob;
use App\Models\BulkNotification;
use Illuminate\Support\Facades\Queue;

it('sends scheduled notifications that are due', function () {
    Queue::fake();

    $dueNotifications = BulkNotification::factory()
        ->count(3)
        ->create([
            'status' => BulkNotificationStatus::SCHEDULED,
            'scheduled_at' => now()->subMinute(),
        ]);

    $futureNotifications = BulkNotification::factory()
        ->count(2)
        ->create([
            'status' => BulkNotificationStatus::SCHEDULED,
            'scheduled_at' => now()->addHours(2),
        ]);

    $this->artisan(SendScheduledBulkNotifications::class)
        ->assertSuccessful();

    Queue::assertPushed(SendBulkNotificationJob::class, 3);

    Queue::assertPushed(SendBulkNotificationJob::class, function ($job) use ($dueNotifications) {
        return $dueNotifications->contains('id', $job->bulkNotification->id);
    });
});

it('does not send notifications that are not scheduled', function () {
    Queue::fake();

    BulkNotification::factory()->create([
        'status' => BulkNotificationStatus::DRAFT,
    ]);

    BulkNotification::factory()->create([
        'status' => BulkNotificationStatus::SENT,
    ]);

    $this->artisan(SendScheduledBulkNotifications::class)
        ->assertSuccessful();

    Queue::assertNotPushed(SendBulkNotificationJob::class);
});

it('does not send future scheduled notifications', function () {
    Queue::fake();

    BulkNotification::factory()->create([
        'status' => BulkNotificationStatus::SCHEDULED,
        'scheduled_at' => now()->addHour(),
    ]);

    $this->artisan(SendScheduledBulkNotifications::class)
        ->assertSuccessful();

    Queue::assertNotPushed(SendBulkNotificationJob::class);
});

it('handles empty queue gracefully', function () {
    Queue::fake();

    $this->artisan(SendScheduledBulkNotifications::class)
        ->expectsOutput('No scheduled notifications to send.')
        ->assertSuccessful();

    Queue::assertNotPushed(SendBulkNotificationJob::class);
});

it('displays count of notifications being sent', function () {
    Queue::fake();

    BulkNotification::factory()
        ->count(5)
        ->create([
            'status' => BulkNotificationStatus::SCHEDULED,
            'scheduled_at' => now()->subMinute(),
        ]);

    $this->artisan(SendScheduledBulkNotifications::class)
        ->expectsOutput('Found 5 scheduled notifications to send.')
        ->assertSuccessful();
});

it('continues processing even if one notification fails', function () {
    Queue::fake();
    Queue::shouldReceive('push')->andThrow(new Exception('Queue error'));

    $notifications = BulkNotification::factory()
        ->count(2)
        ->create([
            'status' => BulkNotificationStatus::SCHEDULED,
            'scheduled_at' => now()->subMinute(),
        ]);

    // The command should still complete successfully even if individual jobs fail to dispatch
    $this->artisan(SendScheduledBulkNotifications::class)
        ->assertSuccessful();
});
