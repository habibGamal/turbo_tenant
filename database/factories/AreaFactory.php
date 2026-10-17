<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Plugins\LocationsDelivery\src\Models\Area>
 */
final class AreaFactory extends Factory
{
    protected $model = \App\Plugins\LocationsDelivery\src\Models\Area::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->city(),
            'name_ar' => fake()->city(),
            'shipping_cost' => fake()->randomFloat(2, 10, 50),
            'governorate_id' => \App\Plugins\LocationsDelivery\src\Models\Governorate::factory(),
            'branch_id' => \App\Plugins\LocationsDelivery\src\Models\Branch::factory(),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(1, 100),
        ];
    }
}
