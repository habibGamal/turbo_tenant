<?php

declare(strict_types=1);

use App\Models\User;
use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\ProductReviews\src\Filament\Resources\Pages\ListReviews;
use App\Plugins\ProductReviews\src\Filament\Resources\ReviewResource;
use App\Plugins\ProductReviews\src\Models\Review;

use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $this->category = Category::factory()->create();
    $this->product = Product::factory()->create([
        'category_id' => $this->category->id,
        'base_price' => 20.0,
    ]);
});

it('has correct navigation settings for reviews', function () {
    expect(ReviewResource::getNavigationGroup())->toBe('العملاء والتقييمات')
        ->and(ReviewResource::getNavigationSort())->toBe(3)
        ->and(ReviewResource::getNavigationLabel())->toBe('التقييمات والآراء');
});

it('renders reviews table with star ratings and approval columns', function () {
    $review = Review::factory()->create([
        'product_id' => $this->product->id,
        'rating' => 5,
        'comment' => 'Great product!',
        'is_approved' => false,
        'is_published' => true,
    ]);

    livewire(ListReviews::class)
        ->assertOk()
        ->assertTableColumnExists('rating')
        ->assertTableColumnExists('is_approved')
        ->assertTableColumnExists('is_published')
        ->assertTableColumnExists('comment')
        ->assertCanRenderTableColumn('rating')
        ->assertCanRenderTableColumn('is_approved')
        ->assertCanRenderTableColumn('is_published');
});

it('supports 1-click approval and rejection actions on reviews', function () {
    $review = Review::factory()->create([
        'product_id' => $this->product->id,
        'rating' => 4,
        'comment' => 'Very nice taste',
        'is_approved' => false,
    ]);

    livewire(ListReviews::class)
        ->callTableAction('approve', $review)
        ->assertHasNoTableActionErrors();

    expect($review->fresh()->is_approved)->toBeTrue();

    livewire(ListReviews::class)
        ->callTableAction('reject', $review)
        ->assertHasNoTableActionErrors();

    expect($review->fresh()->is_approved)->toBeFalse();
});
