<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Plugins\CmsPages\src\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Page>
 */
final class PageFactory extends Factory
{
    protected $model = Page::class;

    public function definition(): array
    {
        $title = fake()->sentence(3);

        return [
            'title' => $title,
            'title_ar' => 'صفحة ' . fake()->word(),
            'slug' => Str::slug($title) . '-' . fake()->unique()->numberBetween(100, 9999),
            'content' => fake()->paragraphs(3, true),
            'content_ar' => 'محتوى تجريبي للصفحة باللغة العربية.',
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
