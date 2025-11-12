<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Coupon>
 */
final class CouponFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(['percentage', 'fixed']);

        return [
            'code' => fake()->unique()->lexify('COUPON????'),
            'type' => $type,
            'value' => $type === 'percentage' ? fake()->numberBetween(5, 50) : fake()->randomFloat(2, 5, 100),
            'expiry_date' => fake()->dateTimeBetween('now', '+1 year'),
            'is_active' => fake()->boolean(80),
            'max_usage' => fake()->boolean(50) ? fake()->numberBetween(10, 1000) : null,
            'usage_count' => 0,
            'total_consumed' => 0,
        ];
    }
}
