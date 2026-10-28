<?php

declare(strict_types=1);

use App\Models\User;
use App\Plugins\LocationsDelivery\src\Filament\Resources\Addresses\AddressResource;
use App\Plugins\LocationsDelivery\src\Filament\Resources\Governorates\GovernorateResource;
use App\Plugins\LocationsDelivery\src\Filament\Resources\Governorates\Pages\ListGovernorates;
use App\Plugins\LocationsDelivery\src\Models\Governorate;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('has correct navigation settings for governorates and addresses', function () {
    expect(GovernorateResource::getNavigationGroup())->toBe('التوصيل والمناطق')
        ->and(GovernorateResource::getNavigationSort())->toBe(2)
        ->and(GovernorateResource::getNavigationLabel())->toBe('المحافظات')
        ->and(AddressResource::shouldRegisterNavigation())->toBeFalse();
});

it('renders governorates list page with active toggle and areas count', function () {
    Governorate::factory()->create([
        'name' => 'Cairo',
        'name_ar' => 'القاهرة',
        'is_active' => true,
    ]);

    livewire(ListGovernorates::class)
        ->assertOk()
        ->assertTableColumnExists('is_active')
        ->assertTableColumnExists('areas_count')
        ->assertCanRenderTableColumn('is_active')
        ->assertCanRenderTableColumn('areas_count');
});
