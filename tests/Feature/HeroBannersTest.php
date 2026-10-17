<?php

declare(strict_types=1);

use App\Plugins\HeroBanners\src\Models\HeroSlider as LegacyHeroSlider;
use App\Plugins\HeroBanners\src\Filament\Resources\HeroSliderResource;
use App\Plugins\HeroBanners\src\Models\HeroSlider;
use App\Plugins\HeroBanners\src\Services\HeroBannerService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a hero slider with bilingual fields using factory', function () {
    $slider = HeroSlider::factory()->create([
        'title' => 'Big Burger Combo',
        'title_ar' => 'كومبو البرجر العملاق',
        'subtitle' => 'Save 30% this weekend only!',
        'subtitle_ar' => 'وفر ٣٠٪ نهاية هذا الأسبوع فقط!',
        'cta_text' => 'Order Now',
        'cta_link' => '/menu',
        'sort_order' => 1,
        'is_active' => true,
    ]);

    expect($slider->title)->toBe('Big Burger Combo')
        ->and($slider->title_ar)->toBe('كومبو البرجر العملاق')
        ->and($slider->subtitle)->toBe('Save 30% this weekend only!')
        ->and($slider->subtitle_ar)->toBe('وفر ٣٠٪ نهاية هذا الأسبوع فقط!')
        ->and($slider->cta_text)->toBe('Order Now')
        ->and($slider->cta_link)->toBe('/menu')
        ->and($slider->sort_order)->toBe(1)
        ->and($slider->is_active)->toBeTrue();
});

it('resolves legacy model alias to plugin hero slider model', function () {
    expect(class_exists(LegacyHeroSlider::class))->toBeTrue();

    $legacyInstance = new LegacyHeroSlider();
    expect($legacyInstance)->toBeInstanceOf(HeroSlider::class);
});

it('resolves legacy filament resource alias to plugin hero slider resource', function () {
    expect(class_exists(\App\Filament\Resources\HeroSliderResource::class))->toBeTrue();

    expect(is_subclass_of(\App\Filament\Resources\HeroSliderResource::class, \Filament\Resources\Resource::class))->toBeTrue();
    expect((new ReflectionClass(\App\Filament\Resources\HeroSliderResource::class))->getName())
        ->toBe(HeroSliderResource::class);
});

it('returns active hero sliders sorted by sort_order via service', function () {
    HeroSlider::factory()->create(['title' => 'Third Slide', 'sort_order' => 3, 'is_active' => true]);
    HeroSlider::factory()->create(['title' => 'First Slide', 'sort_order' => 1, 'is_active' => true]);
    HeroSlider::factory()->create(['title' => 'Inactive Slide', 'sort_order' => 0, 'is_active' => false]);
    HeroSlider::factory()->create(['title' => 'Second Slide', 'sort_order' => 2, 'is_active' => true]);

    $service = app(HeroBannerService::class);
    $activeSliders = $service->getActiveSliders();

    expect($activeSliders)->toHaveCount(3)
        ->and($activeSliders->pluck('title')->toArray())->toBe([
            'First Slide',
            'Second Slide',
            'Third Slide',
        ]);
});

it('serves active hero sliders via api endpoint', function () {
    HeroSlider::factory()->create(['title' => 'Promo 1', 'sort_order' => 1, 'is_active' => true]);
    HeroSlider::factory()->create(['title' => 'Promo Inactive', 'sort_order' => 2, 'is_active' => false]);

    $response = $this->getJson(route('api.hero-sliders.index'));

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Promo 1');
});

it('configures filament resource methods properly', function () {
    expect(HeroSliderResource::getModel())->toBe(HeroSlider::class)
        ->and(HeroSliderResource::getPages())->toHaveKeys(['index', 'create', 'edit']);
});
