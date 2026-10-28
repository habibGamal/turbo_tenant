<?php

declare(strict_types=1);

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Plugins\GuestCheckout\src\Filament\Resources\GuestUsers\GuestUserResource;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('has correct navigation settings for users and guest users', function () {
    expect(UserResource::getNavigationGroup())->toBe('العملاء والتقييمات')
        ->and(UserResource::getNavigationSort())->toBe(1)
        ->and(UserResource::getNavigationLabel())->toBe('المستخدمين')
        ->and(GuestUserResource::getNavigationGroup())->toBe('العملاء والتقييمات')
        ->and(GuestUserResource::getNavigationSort())->toBe(2)
        ->and(GuestUserResource::getNavigationLabel())->toBe('زوار المتجر');
});

it('renders user list with tabs for customers and admins', function () {
    $customer = User::factory()->create([
        'name' => 'Regular Customer',
        'is_admin' => false,
    ]);

    $admin = User::factory()->create([
        'name' => 'Admin Manager',
        'is_admin' => true,
    ]);

    livewire(ListUsers::class)
        ->assertOk()
        ->assertTableColumnExists('addresses.phone_number')
        ->assertTableColumnExists('orders_count')
        ->assertCanSeeTableRecords([$customer])
        ->assertCanNotSeeTableRecords([$admin])
        ->set('activeTab', 'admins')
        ->assertCanSeeTableRecords([$admin])
        ->assertCanNotSeeTableRecords([$customer]);
});

it('renders user form with guarded is_admin toggle', function () {
    livewire(CreateUser::class)
        ->assertOk()
        ->assertSchemaComponentExists('is_admin');
});

it('prevents an admin from removing their own admin status in edit page', function () {
    $currentAdmin = auth()->user();

    livewire(EditUser::class, [
        'record' => $currentAdmin->id,
    ])
        ->fillForm([
            'is_admin' => false,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($currentAdmin->fresh()->is_admin)->toBeTrue();
});
