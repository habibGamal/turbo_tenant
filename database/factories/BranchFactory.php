<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Plugins\LocationsDelivery\src\Models\Branch>
 */
final class BranchFactory extends Factory
{
    protected $model = \App\Plugins\LocationsDelivery\src\Models\Branch::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'link' => fake()->url(),
            'is_active' => fake()->boolean(90),
        ];
    }
}
