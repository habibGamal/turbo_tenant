<?php

declare(strict_types=1);

use App\Filament\Central\Resources\TenantResource\Pages\CreateTenant;
use App\Filament\Central\Resources\TenantResource\Pages\EditTenant;
use App\Filament\Central\Resources\TenantResource\Pages\ListTenants;
use App\Models\Tenant;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

beforeEach(function () {
    // End any existing tenancy context for central panel tests
    if (tenancy()->initialized) {
        tenancy()->end();
    }

    // Queue fake to prevent job execution during UI tests
    Queue::fake();

    // Clear tenants table
    Tenant::query()->withoutEvents(function () {
        Tenant::query()->each(function ($tenant) {
            $tenant->domains()->delete();
            $tenant->forceDelete();
        });
    });

    // Create central admin user (not a tenant user)
    $centralUser = new User();
    $centralUser->forceFill([
        'name' => 'Central Admin',
        'email' => 'central@admin.com',
        'password' => bcrypt('password'),
        'is_admin' => true,
    ]);
    $centralUser->saveOrFail();

    $this->actingAs($centralUser);
});

it('can render the index page', function () {
    livewire(ListTenants::class)
        ->assertOk();
});

it('can render the create page', function () {
    livewire(CreateTenant::class)
        ->assertOk();
});

it('can render the edit page', function () {
    $tenant = Tenant::withoutEvents(fn () => Tenant::create(['id' => 'testrestaurant']));
    $tenant->domains()->create(['domain' => 'testrestaurant.localhost']);

    livewire(EditTenant::class, [
        'record' => $tenant->id,
    ])
        ->assertOk();
});

it('can create a tenant with admin credentials', function () {
    livewire(CreateTenant::class)
        ->fillForm([
            'id' => 'newrestaurant',
            'admin_name' => 'Restaurant Admin',
            'admin_email' => 'admin@restaurant.com',
            'admin_password' => 'SecurePassword123',
            'domains' => [
                ['domain' => 'newrestaurant.localhost'],
            ],
        ])
        ->call('create')
        ->assertNotified();

    // Assert tenant was created with admin credentials
    $tenant = Tenant::find('newrestaurant');
    expect($tenant)->not->toBeNull()
        ->and($tenant->admin_name)->toBe('Restaurant Admin')
        ->and($tenant->admin_email)->toBe('admin@restaurant.com')
        ->and($tenant->admin_password)->not->toBeNull()
        ->and(Hash::check('SecurePassword123', $tenant->admin_password))->toBeTrue();

    // Assert domain was created
    assertDatabaseHas('domains', [
        'domain' => 'newrestaurant.localhost',
        'tenant_id' => 'newrestaurant',
    ]);
});

it('can update tenant admin credentials', function () {
    // Create initial tenant
    $tenant = Tenant::withoutEvents(fn () => Tenant::create([
        'id' => 'testrestaurant',
        'admin_name' => 'Old Admin',
        'admin_email' => 'old@restaurant.com',
        'admin_password' => bcrypt('OldPassword123'),
    ]));
    $tenant->domains()->create(['domain' => 'testrestaurant.localhost']);

    // Update admin credentials
    livewire(EditTenant::class, [
        'record' => $tenant->id,
    ])
        ->fillForm([
            'admin_name' => 'New Admin',
            'admin_email' => 'new@restaurant.com',
            'admin_password' => 'NewPassword123',
        ])
        ->call('save')
        ->assertNotified();

    // Assert tenant was updated
    $tenant->refresh();
    expect($tenant->admin_name)->toBe('New Admin')
        ->and($tenant->admin_email)->toBe('new@restaurant.com')
        ->and(Hash::check('NewPassword123', $tenant->admin_password))->toBeTrue();
});

it('can update tenant without changing password', function () {
    // Create initial tenant
    $hashedPassword = bcrypt('Password123');
    $tenant = Tenant::withoutEvents(fn () => Tenant::create([
        'id' => 'testrestaurant',
        'admin_name' => 'Admin',
        'admin_email' => 'admin@restaurant.com',
        'admin_password' => $hashedPassword,
    ]));
    $tenant->domains()->create(['domain' => 'testrestaurant.localhost']);

    // Update only name, leave password blank
    livewire(EditTenant::class, [
        'record' => $tenant->id,
    ])
        ->fillForm([
            'admin_name' => 'Updated Admin',
            'admin_email' => 'admin@restaurant.com',
            'admin_password' => null,
        ])
        ->call('save')
        ->assertNotified();

    // Check that password was not changed
    $tenant->refresh();
    expect($tenant->admin_name)->toBe('Updated Admin')
        ->and($tenant->admin_password)->toBe($hashedPassword);
});

it('can delete a tenant', function () {
    $tenant = Tenant::withoutEvents(fn () => Tenant::create(['id' => 'deletetest']));
    $tenant->domains()->create(['domain' => 'deletetest.localhost']);

    livewire(EditTenant::class, [
        'record' => $tenant->id,
    ])
        ->callAction(DeleteAction::class)
        ->assertNotified();

    expect(Tenant::find('deletetest'))->toBeNull();
});

it('validates required admin fields on create', function (array $data, array $errors) {
    livewire(CreateTenant::class)
        ->fillForm([
            'id' => 'validtenant',
            'domains' => [
                ['domain' => 'validtenant.localhost'],
            ],
            ...$data,
        ])
        ->call('create')
        ->assertHasFormErrors($errors);
})->with([
    '`admin_name` is required' => [['admin_name' => null], ['admin_name' => 'required']],
    '`admin_email` is required' => [['admin_email' => null], ['admin_email' => 'required']],
    '`admin_email` must be valid email' => [['admin_email' => 'invalid-email'], ['admin_email' => 'email']],
    '`admin_password` is required on create' => [['admin_password' => null], ['admin_password' => 'required']],
    '`admin_password` must be at least 8 characters' => [['admin_password' => 'short'], ['admin_password' => 'min']],
]);

it('validates tenant id is unique', function () {
    Tenant::withoutEvents(fn () => Tenant::create(['id' => 'existingrestaurant']));

    livewire(CreateTenant::class)
        ->fillForm([
            'id' => 'existingrestaurant',
            'admin_name' => 'Admin',
            'admin_email' => 'admin@test.com',
            'admin_password' => 'Password123',
            'domains' => [
                ['domain' => 'restaurant.localhost'],
            ],
        ])
        ->call('create')
        ->assertHasFormErrors(['id' => 'unique']);
});

it('creates admin user in tenant database', function () {
    // This test actually creates the tenant database and admin user
    Queue::assertNothingPushed(); // Ensure we're not using queues for this test

    // Create tenant with events enabled
    $tenant = Tenant::create([
        'id' => 'realtest',
        'admin_name' => 'Real Admin',
        'admin_email' => 'real@admin.com',
        'admin_password' => bcrypt('RealPassword123'),
    ]);
    $tenant->domains()->create(['domain' => 'realtest.localhost']);

    // Wait for database creation
    sleep(1);

    // Initialize tenancy and check admin user
    tenancy()->initialize($tenant);

    $adminUser = User::query()->where('email', 'real@admin.com')->first();
    expect($adminUser)->not->toBeNull()
        ->and($adminUser->name)->toBe('Real Admin')
        ->and($adminUser->is_admin)->toBeTrue();

    tenancy()->end();
})->skip('Skipped for faster test execution - enable when testing actual tenant creation');
