<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Plugins\CatalogProducts\src\Models\ExtraOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Plugins\CatalogProducts\src\Models\ExtraOptionItem>
 */
final class ExtraOptionItemFactory extends Factory
{
    protected $model = \App\Plugins\CatalogProducts\src\Models\ExtraOptionItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'extra_option_id' => ExtraOption::factory(),
            'name' => fake()->word(),
            'price' => fake()->randomFloat(2, 0, 10),
            'is_default' => fake()->boolean(20),
            'sort_order' => fake()->numberBetween(0, 10),
        ];
    }
}
