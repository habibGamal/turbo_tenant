<?php

declare(strict_types=1);

use App\Plugins\CmsPages\src\Models\Page as LegacyPage;
use App\Plugins\CmsPages\src\Filament\Resources\Pages\PageResource;
use App\Plugins\CmsPages\src\Models\Page;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('inertia.testing.page_paths', array_merge(
        config('inertia.testing.page_paths', []),
        [resource_path('js/themes/default/pages')]
    ));
});

it('creates a page with bilingual attributes using factory', function () {
    $page = Page::factory()->create([
        'title' => 'Terms of Service',
        'title_ar' => 'الشروط والأحكام',
        'slug' => 'terms-of-service',
        'content' => 'Standard terms and conditions.',
        'content_ar' => 'الشروط والأحكام القياسية.',
        'is_active' => true,
    ]);

    expect($page->title)->toBe('Terms of Service')
        ->and($page->title_ar)->toBe('الشروط والأحكام')
        ->and($page->slug)->toBe('terms-of-service')
        ->and($page->content)->toBe('Standard terms and conditions.')
        ->and($page->content_ar)->toBe('الشروط والأحكام القياسية.')
        ->and($page->is_active)->toBeTrue();
});

it('resolves legacy model alias to plugin page model', function () {
    expect(class_exists(LegacyPage::class))->toBeTrue();

    $legacyInstance = new LegacyPage();
    expect($legacyInstance)->toBeInstanceOf(Page::class);
});

it('resolves legacy filament resource alias to plugin page resource', function () {
    expect(class_exists(\App\Filament\Resources\Pages\PageResource::class))->toBeTrue();

    expect(is_subclass_of(\App\Filament\Resources\Pages\PageResource::class, \Filament\Resources\Resource::class))->toBeTrue();
    expect((new ReflectionClass(\App\Filament\Resources\Pages\PageResource::class))->getName())
        ->toBe(PageResource::class);
});

it('displays active page on storefront via inertia', function () {
    $page = Page::factory()->create([
        'title' => 'About Us',
        'slug' => 'about-us',
        'content' => 'We are a premier restaurant.',
        'is_active' => true,
    ]);

    $response = $this->get(route('pages.show', ['slug' => 'about-us']));

    $response->assertSuccessful()
        ->assertInertia(fn ($assert) => $assert
            ->component('Page')
            ->has('page')
            ->where('page.id', $page->id)
            ->where('page.slug', 'about-us')
            ->where('page.title', 'About Us')
        );
});

it('returns 404 when requesting an inactive page', function () {
    $page = Page::factory()->inactive()->create([
        'title' => 'Draft Policy',
        'slug' => 'draft-policy',
    ]);

    $response = $this->get(route('pages.show', ['slug' => 'draft-policy']));

    $response->assertNotFound();
});

it('returns 404 when page slug does not exist', function () {
    $response = $this->get(route('pages.show', ['slug' => 'non-existent-page-slug']));

    $response->assertNotFound();
});

it('configures filament resource methods properly', function () {
    expect(PageResource::getModel())->toBe(Page::class)
        ->and(PageResource::getPages())->toHaveKeys(['index', 'create', 'view', 'edit']);
});
