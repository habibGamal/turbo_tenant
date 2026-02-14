<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Area;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GuestUser>
 */
final class GuestUserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->optional(0.7)->safeEmail(),
            'phone' => fake()->numerify('##########'),
            'phone_country_code' => fake()->randomElement(['+20', '+966', '+971']),
            'street' => fake()->optional(0.8)->streetName(),
            'building' => fake()->optional(0.8)->buildingNumber(),
            'floor' => fake()->optional(0.7)->numberBetween(1, 10),
            'apartment' => fake()->optional(0.7)->numberBetween(1, 20),
            'city' => fake()->optional(0.8)->city(),
            'area_id' => fake()->optional(0.6)->randomElement(Area::pluck('id')->toArray()),
        ];
    }

    /**
     * Indicate that the guest user has complete address information.
     */
    public function withCompleteAddress(): static
    {
        return $this->state(fn (array $attributes) => [
            'street' => fake()->streetName(),
            'building' => fake()->buildingNumber(),
            'floor' => fake()->numberBetween(1, 10),
            'apartment' => fake()->numberBetween(1, 20),
            'city' => fake()->city(),
            'area_id' => Area::factory(),
        ]);
    }

    /**
     * Indicate that the guest user is from Egypt with a +20 country code.
     */
    public function egyptian(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone_country_code' => '+20',
            'phone' => fake()->numerify('1#########'),
            'city' => fake()->randomElement(['Cairo', 'Alexandria', 'Giza', 'Sharm El Sheikh']),
        ]);
    }

    /**
     * Indicate that the guest user is from Saudi Arabia with a +966 country code.
     */
    public function saudi(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone_country_code' => '+966',
            'phone' => fake()->numerify('5########'),
            'city' => fake()->randomElement(['Riyadh', 'Jeddah', 'Mecca', 'Medina']),
        ]);
    }
}
