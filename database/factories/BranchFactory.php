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
            // Deterministic: orders for inactive branches are rejected, so a random value makes order tests flaky.
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
