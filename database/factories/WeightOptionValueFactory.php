<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WeightOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WeightOptionValue>
 */
final class WeightOptionValueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $value = fake()->randomElement(['0.1', '0.25', '0.5', '0.75', '1.0', '1.5', '2.0', '3.0', '5.0']);

        return [
            'weight_option_id' => WeightOption::factory(),
            'value' => $value,
            'label' => null,
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }
}
