<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\ExtraOption;
use App\Models\ExtraOptionItem;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('displays product details page', function () {
    $category = Category::factory()->create(['name' => 'Pizza']);
    $product = Product::factory()->create([
        'name' => 'Margherita Pizza',
        'description' => 'Classic Italian pizza',
        'base_price' => 12.99,
        'category_id' => $category->id,
        'is_active' => true,
    ]);

    $response = $this->get(route('products.show', $product));

    $response->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('ProductShow')
            ->has('product')
            ->where('product.id', $product->id)
            ->where('product.name', 'Margherita Pizza')
            ->where('product.base_price', 12.99)
        );
});

it('loads product with category relationship', function () {
    $category = Category::factory()->create(['name' => 'Burgers']);
    $product = Product::factory()->create([
        'category_id' => $category->id,
    ]);

    $response = $this->get(route('products.show', $product));

    $response->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('product.category')
            ->where('product.category.name', 'Burgers')
        );
});

it('loads product with variants', function () {
    $product = Product::factory()->create();
    $smallVariant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'name' => 'Small',
        'price' => 8.99,
        'is_available' => true,
    ]);
    $largeVariant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'name' => 'Large',
        'price' => 12.99,
        'is_available' => true,
    ]);

    $response = $this->get(route('products.show', $product));

    $response->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('product.variants', 2)
        );
});

it('loads product with extra options and items', function () {
    $extraOption = ExtraOption::factory()->create(['name' => 'Toppings']);
    $product = Product::factory()->create([
        'extra_option_id' => $extraOption->id,
    ]);

    $extraItem1 = ExtraOptionItem::factory()->create([
        'extra_option_id' => $extraOption->id,
        'name' => 'Extra Cheese',
        'price' => 2.00,
    ]);
    $extraItem2 = ExtraOptionItem::factory()->create([
        'extra_option_id' => $extraOption->id,
        'name' => 'Olives',
        'price' => 1.50,
    ]);

    $response = $this->get(route('products.show', $product));

    $response->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('product.extraOption')
            ->where('product.extraOption.name', 'Toppings')
            ->has('product.extraOption.items', 2)
        );
});

it('displays reviews for the product', function () {
    $product = Product::factory()->create();

    $response = $this->get(route('products.show', $product));

    $response->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('reviews')
        );
});

it('loads related products from the same category', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'is_active' => true,
    ]);

    // Create related products in the same category
    $relatedProducts = Product::factory()->count(3)->create([
        'category_id' => $category->id,
        'is_active' => true,
    ]);

    $response = $this->get(route('products.show', $product));

    $response->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('relatedProducts')
        );
});

it('loads promotional products with discounts', function () {
    $product = Product::factory()->create([
        'is_active' => true,
    ]);

    // Create promotional products with discounts
    $promoProducts = Product::factory()->count(2)->create([
        'base_price' => 20.00,
        'price_after_discount' => 15.00,
        'is_active' => true,
    ]);

    $response = $this->get(route('products.show', $product));

    $response->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('promotionalProducts')
        );
});

it('excludes current product from related products', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'name' => 'Main Product',
        'is_active' => true,
    ]);

    // Create related products in the same category
    Product::factory()->count(3)->create([
        'category_id' => $category->id,
        'is_active' => true,
    ]);

    $response = $this->get(route('products.show', $product));

    $response->assertSuccessful()
        ->assertInertia(function ($page) use ($product) {
            $relatedProducts = $page->toArray()['props']['relatedProducts'];
            $relatedProductIds = array_column($relatedProducts, 'id');

            expect($relatedProductIds)->not->toContain($product->id);

            return $page;
        });
});

it('only shows available variants', function () {
    $product = Product::factory()->create();

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

    $response = $this->get(route('products.show', $product));

    $response->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->has('product.variants', 1)
        );
});

it('returns 404 for non-existent product', function () {
    $response = $this->get('/products/99999');

    $response->assertNotFound();
});

it('handles product without category gracefully', function () {
    $product = Product::factory()->create([
        'category_id' => null,
        'is_active' => true,
    ]);

    $response = $this->get(route('products.show', $product));

    $response->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('product.category', null)
        );
});

it('displays discounted price when available', function () {
    $product = Product::factory()->create([
        'base_price' => 20.00,
        'price_after_discount' => 15.00,
        'is_active' => true,
    ]);

    $response = $this->get(route('products.show', $product));

    $response->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->where('product.base_price', 20.00)
            ->where('product.price_after_discount', 15.00)
            ->where('product.price', 15.00)
        );
});
