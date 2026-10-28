<?php

declare(strict_types=1);

use App\Models\User;
use App\Plugins\CmsPages\src\Filament\Resources\Pages\PageResource;
use App\Plugins\CmsPages\src\Filament\Resources\Pages\Pages\CreatePage;
use App\Plugins\CmsPages\src\Filament\Resources\Pages\Pages\ListPages;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create([
        'is_admin' => true,
    ]));
});

it('has correct navigation and labels configured for CMS pages', function () {
    expect(PageResource::getNavigationGroup())->toBe('المحتوى والتسويق')
        ->and(PageResource::getNavigationLabel())->toBe('صفحات CMS')
        ->and(PageResource::getNavigationSort())->toBe(2)
        ->and(PageResource::getModelLabel())->toBe('صفحة')
        ->and(PageResource::getPluralModelLabel())->toBe('صفحات CMS');
});

it('can render page list and create pages', function () {
    livewire(ListPages::class)
        ->assertOk();

    livewire(CreatePage::class)
        ->assertOk();
});

it('has expected page table columns', function (string $column) {
    livewire(ListPages::class)
        ->assertTableColumnExists($column);
})->with([
    'title',
    'title_ar',
    'slug',
    'is_active',
]);
