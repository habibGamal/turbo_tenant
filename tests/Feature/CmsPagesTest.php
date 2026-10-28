<?php

declare(strict_types=1);

use App\Plugins\CmsPages\src\Filament\Resources\Pages\PageResource;
use App\Plugins\CmsPages\src\Models\Page;
use App\Plugins\CmsPages\src\Models\Page as LegacyPage;

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
    expect(class_exists(App\Filament\Resources\Pages\PageResource::class))->toBeTrue();

    expect(is_subclass_of(App\Filament\Resources\Pages\PageResource::class, Filament\Resources\Resource::class))->toBeTrue();
    expect((new ReflectionClass(App\Filament\Resources\Pages\PageResource::class))->getName())
        ->toBe(PageResource::class);
});

it('configures filament resource methods properly', function () {
    expect(PageResource::getModel())->toBe(Page::class)
        ->and(PageResource::getPages())->toHaveKeys(['index', 'create', 'view', 'edit']);
});

it('returns active pages list via decoupled REST API', function () {
    Page::factory()->create([
        'title' => 'Active Page 1',
        'slug' => 'active-page-1',
        'is_active' => true,
    ]);

    Page::factory()->create([
        'title' => 'Active Page 2',
        'slug' => 'active-page-2',
        'is_active' => true,
    ]);

    Page::factory()->inactive()->create([
        'title' => 'Draft Inactive Page',
        'slug' => 'draft-inactive-page',
    ]);

    $response = $this->getJson('/api/cms-pages');

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
        ])
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['slug' => 'active-page-1'])
        ->assertJsonFragment(['slug' => 'active-page-2'])
        ->assertJsonMissing(['slug' => 'draft-inactive-page']);
});

it('returns page details by slug via REST API', function () {
    $page = Page::factory()->create([
        'title' => 'Terms of Service',
        'title_ar' => 'الشروط والأحكام',
        'slug' => 'terms-of-service',
        'content' => 'Full terms text here.',
        'content_ar' => 'نص الشروط هنا.',
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/cms-pages/terms-of-service');

    $response->assertOk()
        ->assertJson([
            'status' => 'success',
            'data' => [
                'id' => $page->id,
                'title' => 'Terms of Service',
                'title_ar' => 'الشروط والأحكام',
                'slug' => 'terms-of-service',
                'content' => 'Full terms text here.',
                'content_ar' => 'نص الشروط هنا.',
            ],
        ]);
});

it('returns 404 via REST API when page is inactive or not found', function () {
    Page::factory()->inactive()->create([
        'slug' => 'secret-page',
    ]);

    $this->getJson('/api/cms-pages/secret-page')
        ->assertNotFound()
        ->assertJson([
            'status' => 'error',
            'message' => 'Page not found.',
        ]);

    $this->getJson('/api/cms-pages/does-not-exist')
        ->assertNotFound()
        ->assertJson([
            'status' => 'error',
            'message' => 'Page not found.',
        ]);
});
