<?php

declare(strict_types=1);

use App\Models\User;
use App\Plugins\LocationsDelivery\src\Filament\Resources\Branches\BranchResource;
use App\Plugins\LocationsDelivery\src\Filament\Resources\Branches\Pages\ListBranches;
use App\Plugins\LocationsDelivery\src\Models\Branch;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('has correct navigation settings for branches', function () {
    expect(BranchResource::getNavigationGroup())->toBe('التوصيل والمناطق')
        ->and(BranchResource::getNavigationSort())->toBe(1)
        ->and(BranchResource::getNavigationLabel())->toBe('الفروع');
});

it('renders branches list page with areas_count, orders_count and is_active columns', function () {
    Branch::factory()->create([
        'name' => 'Main Branch',
        'link' => 'https://example.com/branch',
        'is_active' => true,
    ]);

    livewire(ListBranches::class)
        ->assertOk()
        ->assertTableColumnExists('areas_count')
        ->assertTableColumnExists('orders_count')
        ->assertTableColumnExists('is_active')
        ->assertTableColumnExists('link')
        ->assertCanRenderTableColumn('areas_count')
        ->assertCanRenderTableColumn('orders_count')
        ->assertCanRenderTableColumn('is_active')
        ->assertCanRenderTableColumn('link');
});
