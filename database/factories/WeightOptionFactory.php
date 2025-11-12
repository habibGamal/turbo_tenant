<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WeightOption>
 */
final class WeightOptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'min_weight' => 0.5,
            'max_weight' => 5.0,
            'step' => 0.5,
            'unit' => fake()->randomElement(['kg', 'g', 'lb']),
        ];
    }
}
