<?php

declare(strict_types=1);

use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\CatalogProducts\src\Models\Section;

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

it('can list sections via REST API', function () {
    $section = Section::create([
        'title' => 'Featured Deals',
        'location' => 'home',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $response = $this->getJson(route('api.catalog-products.sections'));

    $response->assertSuccessful();
    $response->assertJsonFragment(['title' => 'Featured Deals']);
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
