<?php

declare(strict_types=1);

use App\Models\User;
use App\Plugins\HeroBanners\src\Filament\Resources\HeroSliderResource;
use App\Plugins\HeroBanners\src\Filament\Resources\Pages\CreateHeroSlider;
use App\Plugins\HeroBanners\src\Filament\Resources\Pages\ListHeroSliders;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create([
        'is_admin' => true,
    ]));
});

it('has correct navigation and labels configured for hero slider', function () {
    expect(HeroSliderResource::getNavigationGroup())->toBe('المحتوى والتسويق')
        ->and(HeroSliderResource::getNavigationLabel())->toBe('سلايدر')
        ->and(HeroSliderResource::getNavigationSort())->toBe(1)
        ->and(HeroSliderResource::getModelLabel())->toBe('سلايدر')
        ->and(HeroSliderResource::getPluralModelLabel())->toBe('سلايدر');
});

it('can render hero slider list and create pages', function () {
    livewire(ListHeroSliders::class)
        ->assertOk();

    livewire(CreateHeroSlider::class)
        ->assertOk();
});

it('has expected hero slider table columns', function (string $column) {
    livewire(ListHeroSliders::class)
        ->assertTableColumnExists($column);
})->with([
    'image',
    'title_ar',
    'title',
    'subtitle_ar',
    'cta_link',
    'is_active',
    'sort_order',
]);
