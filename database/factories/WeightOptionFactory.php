<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Plugins\CatalogProducts\src\Models\WeightOption>
 */
final class WeightOptionFactory extends Factory
{
    protected $model = \App\Plugins\CatalogProducts\src\Models\WeightOption::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'unit' => fake()->randomElement(['kg', 'g', 'lb']),
        ];
    }
}
