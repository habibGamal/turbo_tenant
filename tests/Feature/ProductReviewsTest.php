<?php

declare(strict_types=1);

use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\ProductReviews\src\Models\Review as LegacyReview;
use App\Models\User;
use App\Plugins\ProductReviews\src\Filament\Resources\ReviewResource;
use App\Plugins\ProductReviews\src\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    auth()->logout();
    $this->category = Category::factory()->create();
    $this->product = Product::factory()->create([
        'category_id' => $this->category->id,
        'base_price' => 25.00,
        'is_active' => true,
    ]);
});

it('creates a user review with factory defaults', function () {
    $user = User::factory()->create(['name' => 'Alice Johnson']);
    $review = Review::factory()->forUser($user)->create([
        'product_id' => $this->product->id,
        'rating' => 5,
        'comment' => 'Delicious pizza with crispy crust!',
    ]);

    expect($review->rating)->toBe(5)
        ->and($review->comment)->toBe('Delicious pizza with crispy crust!')
        ->and($review->user_id)->toBe($user->id)
        ->and($review->author_name)->toBe('Alice Johnson')
        ->and($review->is_published)->toBeTrue();
});

it('creates a guest review with guest name and email', function () {
    $review = Review::factory()->guest('Bob Guest', 'bob@example.com')->create([
        'product_id' => $this->product->id,
        'rating' => 4,
        'comment' => 'Great value meal for lunch.',
    ]);

    expect($review->rating)->toBe(4)
        ->and($review->comment)->toBe('Great value meal for lunch.')
        ->and($review->user_id)->toBeNull()
        ->and($review->guest_name)->toBe('Bob Guest')
        ->and($review->guest_email)->toBe('bob@example.com')
        ->and($review->author_name)->toBe('Bob Guest')
        ->and($review->is_published)->toBeTrue();
});

it('resolves legacy model and controller aliases', function () {
    expect(class_exists(LegacyReview::class))->toBeTrue();

    $legacyInstance = new LegacyReview();
    expect($legacyInstance)->toBeInstanceOf(Review::class);

    expect(class_exists(\App\Http\Controllers\ReviewController::class))->toBeTrue();
});

it('resolves dynamic reviews relationship on product model', function () {
    Review::factory()->count(3)->create([
        'product_id' => $this->product->id,
    ]);

    $this->product->refresh();
    expect($this->product->reviews)->toHaveCount(3);
});

it('allows authenticated users to submit reviews', function () {
    $user = User::factory()->create(['name' => 'Charlie']);

    $response = $this->actingAs($user)->postJson(route('reviews.store', $this->product), [
        'rating' => 5,
        'comment' => 'Outstanding taste and quick delivery!',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('review.rating', 5)
        ->assertJsonPath('review.user_id', $user->id)
        ->assertJsonPath('review.is_published', true);

    $this->assertDatabaseHas('reviews', [
        'product_id' => $this->product->id,
        'user_id' => $user->id,
        'rating' => 5,
        'comment' => 'Outstanding taste and quick delivery!',
        'is_published' => true,
    ]);
});

it('allows guests to submit reviews with name and optional email', function () {
    $response = $this->postJson(route('reviews.store', $this->product), [
        'guest_name' => 'Sara Guest',
        'guest_email' => 'sara@example.com',
        'rating' => 4,
        'comment' => 'Very satisfied with the meal!',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('review.rating', 4)
        ->assertJsonPath('review.guest_name', 'Sara Guest')
        ->assertJsonPath('review.user_id', null)
        ->assertJsonPath('review.is_published', true);

    $this->assertDatabaseHas('reviews', [
        'product_id' => $this->product->id,
        'guest_name' => 'Sara Guest',
        'guest_email' => 'sara@example.com',
        'user_id' => null,
        'rating' => 4,
        'is_published' => true,
    ]);
});

it('validates that guest name is required when not authenticated', function () {
    $response = $this->postJson(route('reviews.store', $this->product), [
        'rating' => 5,
        'comment' => 'Anonymous review without name',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['guest_name']);
});

it('lists published reviews for a product', function () {
    Review::factory()->create([
        'product_id' => $this->product->id,
        'comment' => 'Published review',
        'is_published' => true,
    ]);

    Review::factory()->unpublished()->create([
        'product_id' => $this->product->id,
        'comment' => 'Unpublished review',
    ]);

    $response = $this->getJson(route('reviews.index', $this->product));

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.comment', 'Published review');
});

it('configures filament resource methods properly', function () {
    expect(ReviewResource::getModel())->toBe(Review::class)
        ->and(ReviewResource::getPages())->toHaveKey('index');
});
