<?php

declare(strict_types=1);

use App\Models\User;
use App\Plugins\PushNotifications\src\Filament\Resources\BulkNotifications\BulkNotificationResource;
use App\Plugins\PushNotifications\src\Filament\Resources\BulkNotifications\Pages\CreateBulkNotification;
use App\Plugins\PushNotifications\src\Filament\Resources\BulkNotifications\Pages\ListBulkNotifications;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create([
        'is_admin' => true,
    ]));
});

it('has correct navigation and labels configured for push notifications', function () {
    expect(BulkNotificationResource::getNavigationGroup())->toBe('المحتوى والتسويق')
        ->and(BulkNotificationResource::getNavigationLabel())->toBe('إشعارات Push')
        ->and(BulkNotificationResource::getNavigationSort())->toBe(3)
        ->and(BulkNotificationResource::getModelLabel())->toBe('إشعار جماعي')
        ->and(BulkNotificationResource::getPluralModelLabel())->toBe('إشعارات Push');
});

it('can render bulk notification list and create pages', function () {
    livewire(ListBulkNotifications::class)
        ->assertOk();

    livewire(CreateBulkNotification::class)
        ->assertOk();
});

it('has expected bulk notification table columns', function (string $column) {
    livewire(ListBulkNotifications::class)
        ->assertTableColumnExists($column);
})->with([
    'id',
    'title',
    'body',
    'status',
    'total_recipients',
    'successful_sends',
    'success_rate',
    'scheduled_at',
]);
