<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Plugins\CatalogProducts\src\Models\Category;
use App\Plugins\CatalogProducts\src\Models\ExtraOption;
use App\Plugins\CatalogProducts\src\Models\WeightOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Plugins\CatalogProducts\src\Models\Product>
 */
final class ProductFactory extends Factory
{
    protected $model = \App\Plugins\CatalogProducts\src\Models\Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $basePrice = fake()->randomFloat(2, 5, 100);

        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'image' => fake()->imageUrl(640, 480, 'food'),
            'base_price' => $basePrice,
            'price_after_discount' => fake()->boolean(30) ? $basePrice * 0.9 : null,
            'extra_option_id' => fake()->boolean(50) ? ExtraOption::factory() : null,
            'category_id' => Category::factory(),
            'is_active' => fake()->boolean(90),
            'sell_by_weight' => fake()->boolean(20),
            'weight_options_id' => fake()->boolean(20) ? WeightOption::factory() : null,
        ];
    }
}
