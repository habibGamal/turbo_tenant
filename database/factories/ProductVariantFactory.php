<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Plugins\CatalogProducts\src\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Plugins\CatalogProducts\src\Models\ProductVariant>
 */
final class ProductVariantFactory extends Factory
{
    protected $model = \App\Plugins\CatalogProducts\src\Models\ProductVariant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => fake()->randomElement(['Small', 'Medium', 'Large', 'Extra Large']),
            'price' => fake()->randomFloat(2, 5, 100),
            'is_available' => fake()->boolean(90),
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }
}
