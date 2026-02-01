<?php

declare(strict_types=1);

use App\Enums\BulkNotificationStatus;
use App\Filament\Resources\BulkNotifications\Pages\CreateBulkNotification;
use App\Filament\Resources\BulkNotifications\Pages\ListBulkNotifications;
use App\Filament\Resources\BulkNotifications\Pages\ViewBulkNotification;
use App\Jobs\SendBulkNotificationJob;
use App\Models\BulkNotification;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use NotificationChannels\Expo\ExpoPushToken;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->users = User::factory()
        ->count(3)
        ->create([
            'expo_token' => ExpoPushToken::make('ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]'),
        ]);
});

it('can render the index page', function () {
    livewire(ListBulkNotifications::class)
        ->assertOk();
});

it('can render the create page', function () {
    livewire(CreateBulkNotification::class)
        ->assertOk();
});

it('can render the view page', function () {
    $notification = BulkNotification::factory()->create();

    livewire(ViewBulkNotification::class, [
        'record' => $notification->id,
    ])
        ->assertOk();
});

it('has column', function (string $column) {
    livewire(ListBulkNotifications::class)
        ->assertTableColumnExists($column);
})->with(['id', 'title', 'status', 'total_recipients', 'successful_sends', 'scheduled_at']);

it('can render column', function (string $column) {
    livewire(ListBulkNotifications::class)
        ->assertCanRenderTableColumn($column);
})->with(['id', 'title', 'status', 'total_recipients', 'successful_sends']);

it('can sort column', function (string $column) {
    $records = BulkNotification::factory(5)->create();

    livewire(ListBulkNotifications::class)
        ->loadTable()
        ->sortTable($column)
        ->assertCanSeeTableRecords($records->sortBy($column), inOrder: true)
        ->sortTable($column, 'desc')
        ->assertCanSeeTableRecords($records->sortByDesc($column), inOrder: true);
})->with(['title', 'created_at']);

it('can search column', function (string $column) {
    $records = BulkNotification::factory(5)->create();

    $value = $records->first()->{$column};

    livewire(ListBulkNotifications::class)
        ->loadTable()
        ->searchTable($value)
        ->assertCanSeeTableRecords($records->where($column, $value))
        ->assertCanNotSeeTableRecords($records->where($column, '!=', $value));
})->with(['title']);

it('can create an immediate bulk notification', function () {
    Queue::fake();

    $notificationData = BulkNotification::factory()->make();

    livewire(CreateBulkNotification::class)
        ->fillForm([
            'title' => $notificationData->title,
            'body' => $notificationData->body,
            'send_type' => 'immediate',
            'send_to_all' => true,
        ])
        ->call('create')
        ->assertNotified();

    assertDatabaseHas(BulkNotification::class, [
        'title' => $notificationData->title,
        'body' => $notificationData->body,
        'status' => BulkNotificationStatus::DRAFT,
    ]);

    Queue::assertPushed(SendBulkNotificationJob::class);
});

it('can create a scheduled bulk notification', function () {
    Queue::fake();

    $notificationData = BulkNotification::factory()->make();
    $scheduledTime = now()->addHours(2);

    livewire(CreateBulkNotification::class)
        ->fillForm([
            'title' => $notificationData->title,
            'body' => $notificationData->body,
            'send_type' => 'scheduled',
            'scheduled_at' => $scheduledTime,
            'send_to_all' => true,
        ])
        ->call('create')
        ->assertNotified();

    assertDatabaseHas(BulkNotification::class, [
        'title' => $notificationData->title,
        'body' => $notificationData->body,
        'status' => BulkNotificationStatus::SCHEDULED,
    ]);

    Queue::assertPushed(SendBulkNotificationJob::class);
});

it('can create a notification with targeted users', function () {
    Queue::fake();

    $targetUsers = $this->users->take(2);
    $notificationData = BulkNotification::factory()->make();

    livewire(CreateBulkNotification::class)
        ->fillForm([
            'title' => $notificationData->title,
            'body' => $notificationData->body,
            'send_type' => 'immediate',
            'send_to_all' => false,
            'target_user_ids' => $targetUsers->pluck('id')->toArray(),
        ])
        ->call('create')
        ->assertNotified();

    $created = BulkNotification::where('title', $notificationData->title)->first();

    expect($created->target_user_ids)->toHaveCount(2)
        ->and($created->target_user_ids)->toContain($targetUsers->first()->id);
});

it('can create a notification with custom data', function () {
    Queue::fake();

    $notificationData = BulkNotification::factory()->make();
    $customData = ['action' => 'view_product', 'product_id' => 123];

    livewire(CreateBulkNotification::class)
        ->fillForm([
            'title' => $notificationData->title,
            'body' => $notificationData->body,
            'send_type' => 'immediate',
            'send_to_all' => true,
            'data' => $customData,
        ])
        ->call('create')
        ->assertNotified();

    $created = BulkNotification::where('title', $notificationData->title)->first();

    expect($created->data)->toBe($customData);
});

it('filters by status', function () {
    $draftNotifications = BulkNotification::factory()->count(2)->create([
        'status' => BulkNotificationStatus::DRAFT,
    ]);

    $sentNotifications = BulkNotification::factory()->count(3)->sent()->create();

    livewire(ListBulkNotifications::class)
        ->loadTable()
        ->filterTable('status', BulkNotificationStatus::DRAFT->value)
        ->assertCanSeeTableRecords($draftNotifications)
        ->assertCanNotSeeTableRecords($sentNotifications);
});

it('validates required fields', function (array $data, array $errors) {
    livewire(CreateBulkNotification::class)
        ->fillForm([
            'send_type' => 'immediate',
            'send_to_all' => true,
            ...$data,
        ])
        ->call('create')
        ->assertHasFormErrors($errors)
        ->assertNotNotified();
})->with([
    '`title` is required' => [['title' => null], ['title' => 'required']],
    '`body` is required' => [['body' => null], ['body' => 'required']],
    '`title` is max 255 characters' => [['title' => str_repeat('a', 256)], ['title' => 'max']],
]);

it('requires scheduled_at when send_type is scheduled', function () {
    $notificationData = BulkNotification::factory()->make();

    livewire(CreateBulkNotification::class)
        ->fillForm([
            'title' => $notificationData->title,
            'body' => $notificationData->body,
            'send_type' => 'scheduled',
            'send_to_all' => true,
            'scheduled_at' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['scheduled_at' => 'required'])
        ->assertNotNotified();
});

it('requires target_user_ids when send_to_all is false', function () {
    $notificationData = BulkNotification::factory()->make();

    livewire(CreateBulkNotification::class)
        ->fillForm([
            'title' => $notificationData->title,
            'body' => $notificationData->body,
            'send_type' => 'immediate',
            'send_to_all' => false,
            'target_user_ids' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['target_user_ids' => 'required'])
        ->assertNotNotified();
});

it('can resend a failed notification', function () {
    Queue::fake();

    $notification = BulkNotification::factory()->failed()->create();

    livewire(ViewBulkNotification::class, [
        'record' => $notification->id,
    ])
        ->callAction('resend')
        ->assertNotified();

    expect($notification->fresh()->status)->toBe(BulkNotificationStatus::DRAFT);

    Queue::assertPushed(SendBulkNotificationJob::class);
});

it('displays statistics correctly on view page', function () {
    $notification = BulkNotification::factory()->sent()->create([
        'total_recipients' => 50,
        'successful_sends' => 48,
        'failed_sends' => 2,
    ]);

    livewire(ViewBulkNotification::class, [
        'record' => $notification->id,
    ])
        ->assertSee('50')
        ->assertSee('48')
        ->assertSee('2');
});

it('redirects to view page after creating notification', function () {
    Queue::fake();

    $notificationData = BulkNotification::factory()->make();

    $component = livewire(CreateBulkNotification::class)
        ->fillForm([
            'title' => $notificationData->title,
            'body' => $notificationData->body,
            'send_type' => 'immediate',
            'send_to_all' => true,
        ])
        ->call('create');

    $record = BulkNotification::where('title', $notificationData->title)->first();

    $component->assertRedirect(ViewBulkNotification::getUrl(['record' => $record->id]));
});
