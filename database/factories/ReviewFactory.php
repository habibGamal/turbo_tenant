<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Plugins\CatalogProducts\src\Models\Product;
use App\Plugins\ProductReviews\src\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
final class ReviewFactory extends Factory
{
    protected $model = Review::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'product_id' => Product::factory(),
            'guest_name' => fake()->name(),
            'guest_email' => fake()->safeEmail(),
            'rating' => fake()->numberBetween(3, 5),
            'comment' => fake()->paragraph(),
            'images' => [],
            'is_published' => true,
            'is_approved' => true,
        ];
    }

    public function forUser(?User $user = null): static
    {
        return $this->state(fn () => [
            'user_id' => $user?->id ?? User::factory(),
            'guest_name' => null,
            'guest_email' => null,
        ]);
    }

    public function guest(?string $name = null, ?string $email = null): static
    {
        return $this->state(fn () => [
            'user_id' => null,
            'guest_name' => $name ?? fake()->name(),
            'guest_email' => $email ?? fake()->safeEmail(),
        ]);
    }

    public function unpublished(): static
    {
        return $this->state(fn () => [
            'is_published' => false,
        ]);
    }
}
