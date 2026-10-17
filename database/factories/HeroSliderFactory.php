<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Plugins\HeroBanners\src\Models\HeroSlider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HeroSlider>
 */
final class HeroSliderFactory extends Factory
{
    protected $model = HeroSlider::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'title_ar' => 'عرض خاص ' . fake()->word(),
            'subtitle' => fake()->sentence(6),
            'subtitle_ar' => 'أفضل العروض والخصومات على وجباتنا المميزة.',
            'badge' => 'SPECIAL OFFER',
            'badge_ar' => 'عرض خاص',
            'cta_text' => 'Order Now',
            'cta_text_ar' => 'اطلب الآن',
            'cta_link' => '/menu',
            'secondary_cta_text' => 'Learn More',
            'secondary_cta_text_ar' => 'المزيد',
            'secondary_cta_link' => '/pages/about-us',
            'image' => 'hero-sliders/sample.webp',
            'gradient' => 'from-orange-500/20 via-red-500/10 to-pink-500/20',
            'icon' => 'sparkles',
            'sort_order' => fake()->numberBetween(0, 10),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
