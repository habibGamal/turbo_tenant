<?php

declare(strict_types=1);

use App\Models\User;
use App\Plugins\ComboPackages\src\Filament\Resources\Packages\PackageResource;
use App\Plugins\ComboPackages\src\Filament\Resources\Packages\Pages\CreatePackage;
use App\Plugins\ComboPackages\src\Filament\Resources\Packages\Pages\ListPackages;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create([
        'is_admin' => true,
    ]));
});

it('has correct navigation and labels configured for packages', function () {
    expect(PackageResource::getNavigationGroup())->toBe('العروض والخصومات')
        ->and(PackageResource::getNavigationLabel())->toBe('باقات التوفير')
        ->and(PackageResource::getNavigationSort())->toBe(2)
        ->and(PackageResource::getModelLabel())->toBe('باقة')
        ->and(PackageResource::getPluralModelLabel())->toBe('باقات التوفير');
});

it('can render package list and create pages', function () {
    livewire(ListPackages::class)
        ->assertOk();

    livewire(CreatePackage::class)
        ->assertOk();
});

it('has expected package table columns', function (string $column) {
    livewire(ListPackages::class)
        ->assertTableColumnExists($column);
})->with([
    'name',
    'name_ar',
    'price',
    'original_price',
    'discount_percentage',
    'validity_status',
    'badge',
    'is_active',
    'is_featured',
    'groups_count',
    'items_count',
]);
