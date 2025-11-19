<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Package>
 */
final class PackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $originalPrice = fake()->randomFloat(2, 30, 150);
        $discountPercentage = fake()->randomElement([10, 15, 20, 25, 30]);
        $price = $originalPrice * (1 - $discountPercentage / 100);

        $icons = ['gift', 'star', 'clock', 'percent'];
        $gradients = [
            'from-orange-500/10 via-red-500/5 to-pink-500/10',
            'from-blue-500/10 via-cyan-500/5 to-teal-500/10',
            'from-purple-500/10 via-violet-500/5 to-fuchsia-500/10',
            'from-green-500/10 via-emerald-500/5 to-teal-500/10',
        ];

        $badges = ['Best Value', 'Popular', 'Limited Time', 'New', 'Weekend Special'];

        return [
            'name' => fake()->words(3, true).' Package',
            'name_ar' => 'باقة '.fake()->words(2, true),
            'description' => fake()->sentence(12),
            'description_ar' => fake()->sentence(10),
            'price' => $price,
            'original_price' => $originalPrice,
            'discount_percentage' => $discountPercentage,
            'badge' => fake()->randomElement($badges),
            'badge_ar' => fake()->randomElement(['أفضل قيمة', 'شائع', 'وقت محدود', 'جديد', 'عرض نهاية الأسبوع']),
            'icon' => fake()->randomElement($icons),
            'gradient' => fake()->randomElement($gradients),
            'is_active' => fake()->boolean(90),
            'is_featured' => fake()->boolean(20),
            'sort_order' => fake()->numberBetween(0, 100),
            'valid_from' => fake()->boolean(30) ? now() : null,
            'valid_until' => fake()->boolean(30) ? now()->addDays(fake()->numberBetween(7, 90)) : null,
        ];
    }

    /**
     * Indicate that the package is featured.
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
            'sort_order' => 0,
        ]);
    }

    /**
     * Indicate that the package is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
