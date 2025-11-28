<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Address>
 */
class AddressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'area_id' => \App\Models\Area::factory(),
            'phone_number' => fake()->phoneNumber(),
            'street' => fake()->streetName(),
            'building' => fake()->buildingNumber(),
            'floor' => fake()->numberBetween(1, 10),
            'apartment' => fake()->numberBetween(1, 20),
            'full_address' => fake()->address(),
            'notes' => fake()->sentence(),
            'is_default' => false,
        ];
    }
}
