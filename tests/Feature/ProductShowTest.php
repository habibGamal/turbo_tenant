<?php

declare(strict_types=1);

use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\ExtraOption;
use App\Plugins\CatalogProducts\src\Models\ExtraOptionItem;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('displays product details page via REST API', function () {
    $category = Category::factory()->create(['name' => 'Pizza']);
    $product = Product::factory()->create([
        'name' => 'Margherita Pizza',
        'description' => 'Classic Italian pizza',
        'base_price' => 12.99,
        'category_id' => $category->id,
        'is_active' => true,
    ]);

    $response = $this->getJson(route('api.catalog-products.show', $product->id));

    $response->assertSuccessful()
        ->assertJson([
            'status' => 'success',
            'data' => [
                'id' => $product->id,
                'name' => 'Margherita Pizza',
                'base_price' => 12.99,
            ],
        ]);
});

it('loads product with category relationship via REST API', function () {
    $category = Category::factory()->create(['name' => 'Burgers']);
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'is_active' => true,
    ]);

    $response = $this->getJson(route('api.catalog-products.show', $product->id));

    $response->assertSuccessful()
        ->assertJsonPath('data.category.name', 'Burgers');
});

it('loads product with variants via REST API', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);
    ProductVariant::factory()->create([
        'product_id' => $product->id,
        'name' => 'Small',
        'price' => 8.99,
        'is_available' => true,
    ]);
    ProductVariant::factory()->create([
        'product_id' => $product->id,
        'name' => 'Large',
        'price' => 12.99,
        'is_available' => true,
    ]);

    $response = $this->getJson(route('api.catalog-products.show', $product->id));

    $response->assertSuccessful();
    expect($response->json('data.variants'))->toHaveCount(2);
});

it('loads product with extra options and items via REST API', function () {
    $category = Category::factory()->create();
    $extraOption = ExtraOption::factory()->create(['name' => 'Toppings']);
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'extra_option_id' => $extraOption->id,
        'is_active' => true,
    ]);

    ExtraOptionItem::factory()->create([
        'extra_option_id' => $extraOption->id,
        'name' => 'Extra Cheese',
        'price' => 2.00,
    ]);
    ExtraOptionItem::factory()->create([
        'extra_option_id' => $extraOption->id,
        'name' => 'Olives',
        'price' => 1.50,
    ]);

    $response = $this->getJson(route('api.catalog-products.show', $product->id));

    $response->assertSuccessful()
        ->assertJsonPath('data.extra_option.name', 'Toppings');
    expect($response->json('data.extra_option.items'))->toHaveCount(2);
});

it('displays reviews count and rating for the product via REST API', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);

    $response = $this->getJson(route('api.catalog-products.show', $product->id));

    $response->assertSuccessful()
        ->assertJsonStructure([
            'status',
            'data' => [
                'rating',
                'reviews_count',
            ],
        ])
        ->assertJsonMissing(['reviews']);
});

it('loads related products from the same category via REST API', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'is_active' => true,
    ]);

    Product::factory()->count(3)->create([
        'category_id' => $category->id,
        'is_active' => true,
    ]);

    $response = $this->getJson(route('api.catalog-products.show', $product->id));

    $response->assertSuccessful()
        ->assertJsonStructure(['status', 'related_products']);
    expect($response->json('related_products'))->toHaveCount(3);
});

it('loads promotional products with discounts via REST API', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'is_active' => true,
    ]);

    Product::factory()->count(2)->create([
        'category_id' => $category->id,
        'base_price' => 20.00,
        'price_after_discount' => 15.00,
        'is_active' => true,
    ]);

    $response = $this->getJson(route('api.catalog-products.show', $product->id));

    $response->assertSuccessful()
        ->assertJsonStructure(['status', 'promotional_products']);
    expect($response->json('promotional_products'))->toHaveCount(2);
});

it('excludes current product from related products via REST API', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'name' => 'Main Product',
        'is_active' => true,
    ]);

    Product::factory()->count(3)->create([
        'category_id' => $category->id,
        'is_active' => true,
    ]);

    $response = $this->getJson(route('api.catalog-products.show', $product->id));

    $response->assertSuccessful();
    $relatedProductIds = array_column($response->json('related_products'), 'id');
    expect($relatedProductIds)->not->toContain($product->id);
});

it('only shows available variants via REST API', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id, 'is_active' => true]);

    ProductVariant::factory()->create([
        'product_id' => $product->id,
        'name' => 'Available',
        'is_available' => true,
    ]);

    ProductVariant::factory()->create([
        'product_id' => $product->id,
        'name' => 'Unavailable',
        'is_available' => false,
    ]);

    $response = $this->getJson(route('api.catalog-products.show', $product->id));

    $response->assertSuccessful();
    expect($response->json('data.variants'))->toHaveCount(1);
    expect($response->json('data.variants.0.name'))->toBe('Available');
});

it('returns 404 for non-existent product via REST API', function () {
    $response = $this->getJson('/api/catalog-products/99999');

    $response->assertNotFound()
        ->assertJson([
            'status' => 'error',
            'message' => 'Product not found or unavailable.',
        ]);
});

it('displays discounted price when available via REST API', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'base_price' => 20.00,
        'price_after_discount' => 15.00,
        'is_active' => true,
    ]);

    $response = $this->getJson(route('api.catalog-products.show', $product->id));

    $response->assertSuccessful();
    expect((float) $response->json('data.base_price'))->toBe(20.00)
        ->and((float) $response->json('data.price_after_discount'))->toBe(15.00)
        ->and((float) $response->json('data.price'))->toBe(15.00);
});
