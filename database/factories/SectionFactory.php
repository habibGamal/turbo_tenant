<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Plugins\CatalogProducts\src\Models\Section>
 */
final class SectionFactory extends Factory
{
    protected $model = \App\Plugins\CatalogProducts\src\Models\Section::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->words(2, true),
            'location' => fake()->randomElement(['home', 'menu', 'featured']),
            'is_active' => fake()->boolean(90),
            'sort_order' => fake()->numberBetween(0, 100),
        ];
    }
}
