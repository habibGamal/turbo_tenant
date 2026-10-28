<?php

declare(strict_types=1);

use App\Models\User;
use App\Plugins\CatalogProducts\src\Filament\Resources\Categories\CategoryResource;
use App\Plugins\CatalogProducts\src\Filament\Resources\Categories\Pages\CreateCategory;
use App\Plugins\CatalogProducts\src\Filament\Resources\Categories\Pages\EditCategory;
use App\Plugins\CatalogProducts\src\Filament\Resources\Categories\Pages\ListCategories;
use App\Plugins\CatalogProducts\src\Filament\Resources\ExtraOptions\ExtraOptionResource;
use App\Plugins\CatalogProducts\src\Filament\Resources\ExtraOptions\Pages\CreateExtraOption;
use App\Plugins\CatalogProducts\src\Filament\Resources\ExtraOptions\Pages\EditExtraOption;
use App\Plugins\CatalogProducts\src\Filament\Resources\ExtraOptions\Pages\ListExtraOptions;
use App\Plugins\CatalogProducts\src\Filament\Resources\Products\Pages\CreateProduct;
use App\Plugins\CatalogProducts\src\Filament\Resources\Products\Pages\EditProduct;
use App\Plugins\CatalogProducts\src\Filament\Resources\Products\Pages\ListProducts;
use App\Plugins\CatalogProducts\src\Filament\Resources\Products\ProductResource;
use App\Plugins\CatalogProducts\src\Filament\Resources\Products\RelationManagers\VariantsRelationManager;
use App\Plugins\CatalogProducts\src\Filament\Resources\Sections\Pages\CreateSection;
use App\Plugins\CatalogProducts\src\Filament\Resources\Sections\Pages\EditSection;
use App\Plugins\CatalogProducts\src\Filament\Resources\Sections\Pages\ListSections;
use App\Plugins\CatalogProducts\src\Filament\Resources\Sections\SectionResource;
use App\Plugins\CatalogProducts\src\Filament\Resources\WeightOptions\Pages\ListWeightOptions;
use App\Plugins\CatalogProducts\src\Filament\Resources\WeightOptions\WeightOptionResource;
use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\ExtraOption;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\Section;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('has standardized catalog navigation group and sort orders', function () {
    expect(ProductResource::getNavigationGroup())->toBe('الكتالوج والمنتجات')
        ->and(ProductResource::getNavigationSort())->toBe(1)
        ->and(ProductResource::getNavigationLabel())->toBe('المنتجات')
        ->and(CategoryResource::getNavigationGroup())->toBe('الكتالوج والمنتجات')
        ->and(CategoryResource::getNavigationSort())->toBe(2)
        ->and(CategoryResource::getNavigationLabel())->toBe('الفئات')
        ->and(ExtraOptionResource::getNavigationGroup())->toBe('الكتالوج والمنتجات')
        ->and(ExtraOptionResource::getNavigationSort())->toBe(3)
        ->and(ExtraOptionResource::getNavigationLabel())->toBe('خيارات إضافية')
        ->and(WeightOptionResource::getNavigationGroup())->toBe('الكتالوج والمنتجات')
        ->and(WeightOptionResource::getNavigationSort())->toBe(4)
        ->and(WeightOptionResource::getNavigationLabel())->toBe('خيارات الوزن')
        ->and(SectionResource::getNavigationGroup())->toBe('الكتالوج والمنتجات')
        ->and(SectionResource::getNavigationSort())->toBe(5)
        ->and(SectionResource::getNavigationLabel())->toBe('أقسام الواجهة');
});

it('configures global search on products resource', function () {
    expect(ProductResource::getRecordTitleAttribute())->toBe('name')
        ->and(ProductResource::getGloballySearchableAttributes())->toBe(['name', 'name_ar']);
});

it('can render products list and contains expected columns', function () {
    $category = Category::factory()->create();
    Product::factory()->create([
        'category_id' => $category->id,
        'base_price' => 100,
        'price_after_discount' => 80,
    ]);

    livewire(ListProducts::class)
        ->assertOk()
        ->assertTableColumnExists('image')
        ->assertTableColumnExists('name')
        ->assertTableColumnExists('name_ar')
        ->assertTableColumnExists('category.name')
        ->assertTableColumnExists('base_price')
        ->assertTableColumnExists('price_after_discount')
        ->assertTableColumnExists('variants_count')
        ->assertTableColumnExists('is_active');
});

it('can render products create and edit pages', function () {
    $category = Category::factory()->create();
    $extra = ExtraOption::factory()->create([
        'min_selections' => 1,
        'max_selections' => 3,
        'allow_multiple' => true,
    ]);

    $product = Product::factory()->create([
        'category_id' => $category->id,
        'extra_option_id' => $extra->id,
        'min_selections' => 2,
        'max_selections' => 5,
        'allow_multiple' => false,
    ]);

    livewire(CreateProduct::class)
        ->assertOk();

    livewire(EditProduct::class, [
        'record' => $product->id,
    ])
        ->assertOk()
        ->assertSchemaStateSet([
            'name' => $product->name,
            'category_id' => $category->id,
            'extra_option_id' => $extra->id,
            'min_selections' => 2,
            'max_selections' => 5,
            'allow_multiple' => false,
        ]);
});

it('persists product-level extra option selection rules overrides', function () {
    $category = Category::factory()->create();
    $extra = ExtraOption::factory()->create([
        'min_selections' => 0,
        'max_selections' => 2,
        'allow_multiple' => true,
    ]);

    $product = Product::create([
        'name' => 'Burger Deluxe',
        'name_ar' => 'برجر ديلوكس',
        'category_id' => $category->id,
        'extra_option_id' => $extra->id,
        'base_price' => 150.00,
        'image' => 'products/burger.png',
        'is_active' => true,
        'min_selections' => 1,
        'max_selections' => 4,
        'allow_multiple' => false,
    ]);

    assertDatabaseHas('products', [
        'id' => $product->id,
        'min_selections' => 1,
        'max_selections' => 4,
        'allow_multiple' => 0,
    ]);
});

it('can render categories list and edit pages', function () {
    $category = Category::factory()->create();

    livewire(ListCategories::class)
        ->assertOk()
        ->assertTableColumnExists('name')
        ->assertTableColumnExists('products_count')
        ->assertTableColumnExists('is_active');

    livewire(CreateCategory::class)
        ->assertOk();

    livewire(EditCategory::class, [
        'record' => $category->id,
    ])
        ->assertOk();
});

it('can render extra options list and edit pages with selection rules', function () {
    $extra = ExtraOption::factory()->create([
        'min_selections' => 2,
        'max_selections' => 4,
        'allow_multiple' => true,
    ]);

    livewire(ListExtraOptions::class)
        ->assertOk()
        ->assertTableColumnExists('name')
        ->assertTableColumnExists('selection_rules')
        ->assertTableColumnExists('items_count')
        ->assertTableColumnExists('products_count')
        ->assertTableColumnExists('is_active');

    livewire(CreateExtraOption::class)
        ->assertOk();

    livewire(EditExtraOption::class, [
        'record' => $extra->id,
    ])
        ->assertOk()
        ->assertSchemaStateSet([
            'name' => $extra->name,
            'min_selections' => 2,
            'max_selections' => 4,
            'allow_multiple' => true,
        ]);
});

it('can render sections list and edit pages', function () {
    $section = Section::factory()->create(['location' => 'home']);

    livewire(ListSections::class)
        ->assertOk()
        ->assertTableColumnExists('title')
        ->assertTableColumnExists('location')
        ->assertTableColumnExists('products_count')
        ->assertTableColumnExists('is_active');

    livewire(CreateSection::class)
        ->assertOk();

    livewire(EditSection::class, [
        'record' => $section->id,
    ])
        ->assertOk();
});

it('can render weight options list', function () {
    livewire(ListWeightOptions::class)
        ->assertOk()
        ->assertTableColumnExists('name')
        ->assertTableColumnExists('unit')
        ->assertTableColumnExists('values_count')
        ->assertTableColumnExists('products_count');
});

it('exposes selection rules overrides in catalog products API', function () {
    $category = Category::factory()->create();
    $extra = ExtraOption::factory()->create([
        'min_selections' => 0,
        'max_selections' => 1,
        'allow_multiple' => false,
    ]);

    $product = Product::factory()->create([
        'name' => 'Custom Combo',
        'category_id' => $category->id,
        'extra_option_id' => $extra->id,
        'base_price' => 120.00,
        'is_active' => true,
        'min_selections' => 2,
        'max_selections' => 5,
        'allow_multiple' => true,
    ]);

    $response = $this->getJson(route('api.catalog-products.show', ['id' => $product->id]));

    $response->assertSuccessful();
    $response->assertJsonPath('data.min_selections', 2);
    $response->assertJsonPath('data.max_selections', 5);
    $response->assertJsonPath('data.allow_multiple', true);
    $response->assertJsonPath('data.extra_option.min_selections', 2);
    $response->assertJsonPath('data.extra_option.max_selections', 5);
    $response->assertJsonPath('data.extra_option.allow_multiple', true);
});

it('can render variants relation manager with arabic labels and toggle column', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create([
        'category_id' => $category->id,
    ]);

    livewire(VariantsRelationManager::class, [
        'ownerRecord' => $product,
        'pageClass' => EditProduct::class,
    ])
        ->assertOk()
        ->assertTableColumnExists('name')
        ->assertTableColumnExists('name_ar')
        ->assertTableColumnExists('price')
        ->assertTableColumnExists('is_available')
        ->assertTableColumnExists('sort_order');
});
