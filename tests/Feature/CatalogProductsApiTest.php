<?php

declare(strict_types=1);

use App\Core\Plugins\Enums\PluginStatus;
use App\Core\Plugins\Services\PluginManager;
use App\Models\TenantPlugin;
use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\Section;
use App\Plugins\ProductReviews\src\Models\Review;

it('can list catalog products via REST API', function () {
    Product::factory()->count(2)->create(['is_active' => true]);

    $response = $this->getJson(route('api.catalog-products.index'));

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'status',
        'data' => [
            '*' => [
                'id',
                'name',
                'price',
                'base_price',
                'is_active',
            ],
        ],
        'meta' => [
            'current_page',
            'last_page',
            'per_page',
            'total',
        ],
    ]);
});

it('can fetch specific product details via REST API', function () {
    $product = Product::factory()->create([
        'name' => 'Signature Burger',
        'is_active' => true,
        'base_price' => 120.00,
    ]);

    $response = $this->getJson(route('api.catalog-products.show', $product->id));

    $response->assertSuccessful();
    $response->assertJson([
        'status' => 'success',
        'data' => [
            'id' => $product->id,
            'name' => 'Signature Burger',
        ],
    ]);
});

it('can list categories via REST API', function () {
    Category::factory()->create([
        'name' => 'Desserts',
        'is_active' => true,
    ]);

    $response = $this->getJson(route('api.catalog-products.categories'));

    $response->assertSuccessful();
    $response->assertJsonFragment(['name' => 'Desserts']);
});

it('limits products per section to at most 12', function () {
    $section1 = Section::create(['title' => 'S1', 'location' => 'home', 'is_active' => true, 'sort_order' => 1]);
    $section2 = Section::create(['title' => 'S2', 'location' => 'home', 'is_active' => true, 'sort_order' => 2]);

    $products = Product::factory()->count(25)->create(['is_active' => true]);
    foreach ($products as $i => $product) {
        $section1->products()->attach($product->id, ['sort_order' => $i]);
        $section2->products()->attach($product->id, ['sort_order' => $i]);
    }

    $response = $this->getJson(route('api.catalog-products.sections'));
    $response->assertSuccessful();

    expect(count($response->json('data.0.products')))->toBeLessThanOrEqual(12)
        ->and(count($response->json('data.1.products')))->toBeLessThanOrEqual(12);
});

it('can return search suggestions', function () {
    Product::factory()->create([
        'name' => 'Pepperoni Pizza',
        'is_active' => true,
    ]);

    $response = $this->getJson(route('api.catalog-products.suggestions', ['q' => 'Pep']));

    $response->assertSuccessful();
    $response->assertJsonFragment(['name' => 'Pepperoni Pizza']);
});

it('returns null rating in product summaries', function () {
    Product::factory()->create(['is_active' => true]);

    $response = $this->getJson(route('api.catalog-products.index'));

    $response->assertSuccessful();
    expect($response->json('data.0.rating'))->toBeNull();
});

it('returns null rating and zero reviews count when product has no reviews', function () {
    $product = Product::factory()->create(['is_active' => true]);

    $response = $this->getJson(route('api.catalog-products.show', $product->id));

    $response->assertSuccessful();
    expect($response->json('data.rating'))->toBeNull()
        ->and($response->json('data.reviews_count'))->toBe(0);
});

it('returns real average rating when product has approved and published reviews', function () {
    $product = Product::factory()->create(['is_active' => true]);

    Review::factory()->create([
        'product_id' => $product->id,
        'rating' => 4,
        'is_approved' => true,
        'is_published' => true,
    ]);
    Review::factory()->create([
        'product_id' => $product->id,
        'rating' => 5,
        'is_approved' => true,
        'is_published' => true,
    ]);
    Review::factory()->create([
        'product_id' => $product->id,
        'rating' => 1,
        'is_approved' => false,
        'is_published' => true,
    ]);

    $response = $this->getJson(route('api.catalog-products.show', $product->id));

    $response->assertSuccessful();
    expect($response->json('data.rating'))->toBe(4.5)
        ->and($response->json('data.reviews_count'))->toBe(2);
});

it('returns null rating and zero reviews count when product-reviews plugin is deactivated even if reviews exist', function () {
    $product = Product::factory()->create(['is_active' => true]);

    Review::factory()->create([
        'product_id' => $product->id,
        'rating' => 5,
        'is_approved' => true,
        'is_published' => true,
    ]);

    $tenant = static::$testTenant;
    TenantPlugin::where('tenant_id', (string) $tenant->id)
        ->where('plugin_id', 'product-reviews')
        ->update(['status' => PluginStatus::DEACTIVATED]);
    app(PluginManager::class)->resetTenant($tenant);

    $response = $this->getJson(route('api.catalog-products.show', $product->id));

    $response->assertSuccessful();
    expect($response->json('data.rating'))->toBeNull()
        ->and($response->json('data.reviews_count'))->toBe(0);
});

it('fails validation when per_page is below 1 or above 50', function () {
    Product::factory()->count(15)->create(['is_active' => true]);

    $responseNeg = $this->getJson(route('api.catalog-products.index', ['per_page' => -1]));
    $responseNeg->assertStatus(422)
        ->assertJson([
            'status' => 'error',
        ])
        ->assertJsonValidationErrors(['per_page']);

    $responseZero = $this->getJson(route('api.catalog-products.index', ['per_page' => 0]));
    $responseZero->assertStatus(422)
        ->assertJsonValidationErrors(['per_page']);

    $responseOver = $this->getJson(route('api.catalog-products.index', ['per_page' => 51]));
    $responseOver->assertStatus(422)
        ->assertJsonValidationErrors(['per_page']);
});

it('fails validation when suggestions limit is below 1 or above 20, or q is missing', function () {
    $responseZero = $this->getJson(route('api.catalog-products.suggestions', ['q' => 'Burg', 'limit' => 0]));
    $responseZero->assertStatus(422)
        ->assertJsonValidationErrors(['limit']);

    $responseOver = $this->getJson(route('api.catalog-products.suggestions', ['q' => 'Burg', 'limit' => 21]));
    $responseOver->assertStatus(422)
        ->assertJsonValidationErrors(['limit']);

    $responseMissing = $this->getJson(route('api.catalog-products.suggestions'));
    $responseMissing->assertStatus(422)
        ->assertJsonValidationErrors(['q']);
});

it('validates by-ids ids requiring an array capped at 50 integers', function () {
    $responseMissing = $this->getJson(route('api.catalog-products.by-ids'));
    $responseMissing->assertStatus(422)
        ->assertJsonValidationErrors(['ids']);

    $responseNonInt = $this->getJson(route('api.catalog-products.by-ids', ['ids' => ['not-an-int']]));
    $responseNonInt->assertStatus(422)
        ->assertJsonValidationErrors(['ids.0']);

    $ids51 = range(1, 51);
    $responseOverCap = $this->getJson(route('api.catalog-products.by-ids', ['ids' => $ids51]));
    $responseOverCap->assertStatus(422)
        ->assertJsonValidationErrors(['ids']);

    $ids50 = range(1, 50);
    $responseAtCap = $this->getJson(route('api.catalog-products.by-ids', ['ids' => $ids50]));
    $responseAtCap->assertSuccessful();
});

it('escapes search wildcards so percent does not match everything', function () {
    Product::factory()->create(['name' => 'Burger Regular', 'is_active' => true]);
    Product::factory()->create(['name' => 'Pizza 100% Beef', 'is_active' => true]);

    $results = Product::query()->search('%')->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->name)->toBe('Pizza 100% Beef');

    $response = $this->getJson(route('api.catalog-products.index', ['search' => '%']));
    $response->assertSuccessful();
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.name'))->toBe('Pizza 100% Beef');
});

it('filters products by category_ids', function () {
    $catA = Category::factory()->create(['name' => 'Category A']);
    $catB = Category::factory()->create(['name' => 'Category B']);
    $catC = Category::factory()->create(['name' => 'Category C']);

    $prodA = Product::factory()->create(['category_id' => $catA->id, 'is_active' => true]);
    $prodB = Product::factory()->create(['category_id' => $catB->id, 'is_active' => true]);
    $prodC = Product::factory()->create(['category_id' => $catC->id, 'is_active' => true]);

    $response = $this->getJson(route('api.catalog-products.index', [
        'category_ids' => [$catA->id, $catB->id],
    ]));

    $response->assertSuccessful();
    $returnedIds = array_column($response->json('data'), 'id');
    expect($returnedIds)->toContain($prodA->id, $prodB->id)
        ->and($returnedIds)->not->toContain($prodC->id);
});

it('sorts products by effective price taking discount into account', function () {
    $prodRegular = Product::factory()->create([
        'name' => 'Regular Price Product',
        'base_price' => 100.00,
        'price_after_discount' => null,
        'is_active' => true,
    ]);

    $prodDiscounted = Product::factory()->create([
        'name' => 'Discounted Product',
        'base_price' => 120.00,
        'price_after_discount' => 80.00,
        'is_active' => true,
    ]);

    $responseAsc = $this->getJson(route('api.catalog-products.index', [
        'sort_by' => 'price',
        'sort_order' => 'asc',
    ]));
    $responseAsc->assertSuccessful();
    $idsAsc = array_column($responseAsc->json('data'), 'id');
    expect($idsAsc[0])->toBe($prodDiscounted->id)
        ->and($idsAsc[1])->toBe($prodRegular->id);

    $responseDesc = $this->getJson(route('api.catalog-products.index', [
        'sort_by' => 'price',
        'sort_order' => 'desc',
    ]));
    $responseDesc->assertSuccessful();
    $idsDesc = array_column($responseDesc->json('data'), 'id');
    expect($idsDesc[0])->toBe($prodRegular->id)
        ->and($idsDesc[1])->toBe($prodDiscounted->id);
});

it('orders promotional products by most recently updated first in show', function () {
    $main = Product::factory()->create(['is_active' => true]);

    $promoOld = Product::factory()->create([
        'is_active' => true,
        'base_price' => 50.00,
        'price_after_discount' => 40.00,
        'updated_at' => now()->subDays(5),
    ]);

    $promoNew = Product::factory()->create([
        'is_active' => true,
        'base_price' => 60.00,
        'price_after_discount' => 45.00,
        'updated_at' => now()->subMinute(),
    ]);

    $response = $this->getJson(route('api.catalog-products.show', $main->id));
    $response->assertSuccessful();

    $promoIds = array_column($response->json('data.promotional_products'), 'id');
    expect($promoIds[0])->toBe($promoNew->id)
        ->and($promoIds[1])->toBe($promoOld->id);
});

it('does not run N+1 queries when loading suggestions or products by ids', function () {
    $category = Category::factory()->create();
    $products = Product::factory()->count(10)->create([
        'name' => 'Searchable Item',
        'category_id' => $category->id,
        'is_active' => true,
    ]);

    foreach ($products as $product) {
        App\Plugins\CatalogProducts\src\Models\ProductVariant::factory()->create([
            'product_id' => $product->id,
            'is_available' => true,
        ]);
    }

    Illuminate\Support\Facades\DB::flushQueryLog();
    Illuminate\Support\Facades\DB::enableQueryLog();

    $responseSuggestions = $this->getJson(route('api.catalog-products.suggestions', ['q' => 'Searchable', 'limit' => 10]));
    $responseSuggestions->assertSuccessful();
    $suggestionsQueries = count(Illuminate\Support\Facades\DB::getQueryLog());

    // Should be at most 2 queries (products + categories)
    expect($suggestionsQueries)->toBeLessThanOrEqual(3);

    Illuminate\Support\Facades\DB::flushQueryLog();

    $responseByIds = $this->getJson(route('api.catalog-products.by-ids', ['ids' => $products->pluck('id')->all()]));
    $responseByIds->assertSuccessful();
    $byIdsQueries = count(Illuminate\Support\Facades\DB::getQueryLog());

    // Should be at most 2 queries (products + categories), not 1 query per product
    expect($byIdsQueries)->toBeLessThanOrEqual(3);
});

it('does not emit top-level selection rules while extra_option contains effective ones in show JSON', function () {
    $category = Category::factory()->create();
    $extra = App\Plugins\CatalogProducts\src\Models\ExtraOption::factory()->create([
        'min_selections' => 1,
        'max_selections' => 2,
        'allow_multiple' => false,
    ]);

    $product = Product::factory()->create([
        'name' => 'Custom Pizza',
        'category_id' => $category->id,
        'extra_option_id' => $extra->id,
        'base_price' => 90.00,
        'is_active' => true,
        'min_selections' => 2,
        'max_selections' => 4,
        'allow_multiple' => true,
    ]);

    $response = $this->getJson(route('api.catalog-products.show', $product->id));

    $response->assertSuccessful();
    $data = $response->json('data');

    expect($data)->not->toHaveKey('min_selections')
        ->and($data)->not->toHaveKey('max_selections')
        ->and($data)->not->toHaveKey('allow_multiple')
        ->and($response->json('data.extra_option.min_selections'))->toBe(2)
        ->and($response->json('data.extra_option.max_selections'))->toBe(4)
        ->and($response->json('data.extra_option.allow_multiple'))->toBe(true);
});
