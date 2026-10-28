<?php

declare(strict_types=1);

use App\Models\User;
use App\Plugins\LocationsDelivery\src\Filament\Resources\Areas\AreaResource;
use App\Plugins\LocationsDelivery\src\Filament\Resources\Areas\Pages\CreateArea;
use App\Plugins\LocationsDelivery\src\Filament\Resources\Areas\Pages\ListAreas;
use App\Plugins\LocationsDelivery\src\Models\Area;
use App\Plugins\LocationsDelivery\src\Models\Branch;
use App\Plugins\LocationsDelivery\src\Models\Governorate;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('has correct navigation settings for areas', function () {
    expect(AreaResource::getNavigationGroup())->toBe('التوصيل والمناطق')
        ->and(AreaResource::getNavigationSort())->toBe(3)
        ->and(AreaResource::getNavigationLabel())->toBe('المناطق');
});

it('renders area list page with shipping_cost and is_active columns', function () {
    $governorate = Governorate::factory()->create();
    $branch = Branch::factory()->create();
    Area::factory()->create([
        'governorate_id' => $governorate->id,
        'branch_id' => $branch->id,
        'shipping_cost' => 50,
        'is_active' => true,
    ]);

    livewire(ListAreas::class)
        ->assertOk()
        ->assertTableColumnExists('shipping_cost')
        ->assertTableColumnExists('is_active')
        ->assertTableColumnExists('name')
        ->assertCanRenderTableColumn('shipping_cost')
        ->assertCanRenderTableColumn('is_active');
});

it('filters areas by free shipping', function () {
    $governorate = Governorate::factory()->create();
    $branch = Branch::factory()->create();

    $freeArea = Area::factory()->create([
        'name' => 'Free Area',
        'governorate_id' => $governorate->id,
        'branch_id' => $branch->id,
        'shipping_cost' => 0,
    ]);

    $paidArea = Area::factory()->create([
        'name' => 'Paid Area',
        'governorate_id' => $governorate->id,
        'branch_id' => $branch->id,
        'shipping_cost' => 45,
    ]);

    livewire(ListAreas::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$freeArea, $paidArea])
        ->filterTable('free_shipping', true)
        ->assertCanSeeTableRecords([$freeArea])
        ->assertCanNotSeeTableRecords([$paidArea]);
});

it('renders create area form with governorate inline creation option form', function () {
    livewire(CreateArea::class)
        ->assertOk()
        ->assertSchemaComponentExists('governorate_id');
});
